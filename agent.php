<?php
/**
 * Agent IA - Gestion des interactions avec l'IA Cloudflare
 * Version avec gestion d'erreurs robuste
 */

require_once 'config.php';

/**
 * Envoie une requête à l'API Cloudflare AI avec gestion d'erreurs améliorée
 */
function query_cloudflare_ai($prompt, $max_tokens = MAX_TOKENS, $max_retries = MAX_RETRIES) {
    $system_prompt = load_system_prompt();
    
    // Charge l'historique des conversations récentes formaté correctement
    $recent_conversations = get_recent_conversations(4);
    $conversation_messages = [];
    
    // Ajoute le prompt système
    $conversation_messages[] = [
        'role' => 'system',
        'content' => $system_prompt
    ];
    
    // Ajoute l'historique des conversations
    foreach ($recent_conversations as $conv) {
        $conversation_messages[] = [
            'role' => 'user',
            'content' => $conv['prompt']
        ];
        
        // Formate la réponse précédente en JSON
        $previous_response = [
            'response' => $conv['response'],
            'commands' => $conv['commands_executed'] ? array_map(function($cmd) {
                return [
                    'type' => $cmd['type'],
                    'parameters' => $cmd['parameters']
                ];
            }, $conv['commands_executed']) : []
        ];
        
        $conversation_messages[] = [
            'role' => 'assistant', 
            'content' => json_encode($previous_response, JSON_UNESCAPED_UNICODE)
        ];
    }
    
    // Ajoute le message actuel
    $conversation_messages[] = [
        'role' => 'user',
        'content' => $prompt
    ];
    
    $url = "https://api.cloudflare.com/client/v4/accounts/" . 
           CLOUDFLARE_ACCOUNT_ID . "/ai/run/" . 
           CLOUDFLARE_MODEL;
    
    $request_data = [
        'messages' => $conversation_messages,
        'max_tokens' => $max_tokens,
        'stream' => false,
        'temperature' => 0.1
    ];
    
    $json_data = json_encode($request_data, JSON_UNESCAPED_UNICODE);
    
    for ($attempt = 1; $attempt <= $max_retries + 1; $attempt++) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => $json_data,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . CLOUDFLARE_AUTH_TOKEN,
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        
        // Vérifie si la réponse contient du HTML (erreur)
        if ($response && strpos($response, '<!DOCTYPE') !== false || strpos($response, '<html') !== false) {
            error_log("Cloudflare API returned HTML instead of JSON. HTTP Code: $http_code");
            
            if ($attempt <= $max_retries) {
                sleep(3);
                continue;
            } else {
                // Après tous les essais, retourne une réponse d'erreur structurée
                return json_encode([
                    'response' => '❌ Erreur: L\'API Cloudflare a retourné une réponse HTML au lieu de JSON. Vérifiez vos identifiants API.',
                    'commands' => []
                ], JSON_UNESCAPED_UNICODE);
            }
        }
        
        if (!$error && $http_code === 200) {
            $result = json_decode($response, true);
            
            if (isset($result['result']['response']) && !empty(trim($result['result']['response']))) {
                return $result['result']['response'];
            } elseif (isset($result['errors'])) {
                // Gestion des erreurs de l'API Cloudflare
                $error_msg = "Erreur Cloudflare: ";
                foreach ($result['errors'] as $err) {
                    $error_msg .= $err['message'] . ' ';
                }
                error_log($error_msg);
                
                if ($attempt <= $max_retries) {
                    sleep(2);
                    continue;
                }
            }
        } else if ($http_code >= 400) {
            error_log("Cloudflare API HTTP Error: $http_code - $error");
            
            if ($attempt <= $max_retries) {
                sleep(2);
                continue;
            }
        }
        
        if ($attempt <= $max_retries) {
            sleep(2);
        }
    }
    
    // Si on arrive ici, toutes les tentatives ont échoué
    return json_encode([
        'response' => '❌ Erreur: Impossible de communiquer avec l\'API Cloudflare après ' . ($max_retries + 1) . ' tentatives',
        'commands' => []
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Parse la réponse JSON de l'IA et extrait les commandes avec validation robuste
 */
function parse_ai_response($ai_response) {
    // Si la réponse est déjà un tableau, on la retourne directement
    if (is_array($ai_response)) {
        return $ai_response;
    }
    
    // Nettoie la réponse des éventuels caractères non-JSON
    $clean_response = trim($ai_response);
    
    // Essaie de parser le JSON directement
    $parsed = json_decode($clean_response, true);
    
    if (json_last_error() === JSON_ERROR_NONE) {
        // Valide la structure de base
        if (isset($parsed['response']) || isset($parsed['commands'])) {
            return $parsed;
        }
    }
    
    // Si le JSON est invalide, essaie d'extraire le JSON de la réponse
    preg_match('/\{(?:[^{}]|(?R))*\}/s', $clean_response, $matches);
    if (!empty($matches)) {
        $parsed = json_decode($matches[0], true);
        if (json_last_error() === JSON_ERROR_NONE && (isset($parsed['response']) || isset($parsed['commands']))) {
            return $parsed;
        }
    }
    
    // Fallback: crée une structure valide avec la réponse brute
    return [
        'response' => $clean_response,
        'commands' => []
    ];
}

/**
 * Traite une tâche en attente avec gestion d'erreurs complète
 */
function process_pending_task($task_id) {
    $tasks_data = load_tasks();
    $task_to_process = null;
    
    foreach ($tasks_data['tasks'] as $task) {
        if ($task['id'] == $task_id && $task['status'] == 'pending') {
            $task_to_process = $task;
            break;
        }
    }
    
    if (!$task_to_process) {
        return false;
    }
    
    update_task_status($task_id, 'processing');
    
    try {
        // Appelle l'IA avec le prompt de la tâche
        $ai_response = query_cloudflare_ai($task_to_process['prompt']);
        
        // Parse la réponse JSON de l'IA
        $parsed_response = parse_ai_response($ai_response);
        
        $response_text = $parsed_response['response'] ?? 'Aucune réponse générée';
        $commands = $parsed_response['commands'] ?? [];
        
        // Exécute les commandes si elles existent
        $command_results = [];
        if (!empty($commands) && is_array($commands)) {
            foreach ($commands as $index => $command) {
                if (!isset($command['type']) || !isset($command['parameters'])) {
                    $command_results[] = [
                        'type' => 'invalid',
                        'parameters' => $command,
                        'result' => '❌ Commande invalide: structure incorrecte'
                    ];
                    continue;
                }
                
                try {
                    $result = execute_command($command);
                    $command_results[] = [
                        'type' => $command['type'],
                        'parameters' => $command['parameters'],
                        'result' => $result
                    ];
                    
                    // Petite pause entre les commandes
                    usleep(50000); // 50ms
                    
                } catch (Exception $cmd_error) {
                    error_log("Command execution error [{$command['type']}]: " . $cmd_error->getMessage());
                    $command_results[] = [
                        'type' => $command['type'],
                        'parameters' => $command['parameters'],
                        'result' => '❌ Erreur d\'exécution: ' . $cmd_error->getMessage()
                    ];
                }
            }
        }
        
        // Met à jour le statut avec la réponse et les résultats des commandes
        update_task_status($task_id, 'completed', $response_text, null, $command_results);
        
        // Sauvegarde dans la mémoire
        add_conversation_to_memory($task_to_process['prompt'], $response_text, $command_results);
        
        return [
            'response' => $response_text,
            'commands_executed' => $command_results
        ];
        
    } catch (Exception $e) {
        // Log l'erreur complète
        error_log("Task processing error for task #$task_id: " . $e->getMessage());
        
        update_task_status($task_id, 'failed', null, $e->getMessage());
        return false;
    }
}

/**
 * Traite les tâches en attente
 */
function process_pending_tasks() {
    $tasks_data = load_tasks();
    $tasks_processed = 0;
    
    foreach ($tasks_data['tasks'] as $task) {
        if ($task['status'] === 'pending') {
            $result = process_pending_task($task['id']);
            $tasks_processed++;
            
            if ($result && !empty($result['commands_executed'])) {
                error_log("Tâche #{$task['id']} traitée avec " . count($result['commands_executed']) . " commandes");
            }
            
            // Une tâche à la fois pour plus de stabilité
            break;
        }
    }
    
    return $tasks_processed;
}

/**
 * Teste la connexion à l'API Cloudflare
 */
function test_cloudflare_connection() {
    try {
        $test_response = query_cloudflare_ai("Test de connexion - réponds uniquement avec {\"response\": \"OK\", \"commands\": []}");
        $parsed = parse_ai_response($test_response);
        return isset($parsed['response']) && $parsed['response'] === 'OK';
    } catch (Exception $e) {
        error_log("Connection test failed: " . $e->getMessage());
        return false;
    }
}
?>