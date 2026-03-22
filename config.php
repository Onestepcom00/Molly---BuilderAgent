<?php
/**
 * Configuration et fonctions utilitaires pour l'agent IA
 * Version scalable avec système de fonctions dynamiques
 */

// === CONFIGURATION DE L'APPLICATION ===
define('DATA_DIR', __DIR__ . '/data');
define('TASKS_FILE', DATA_DIR . '/tasks.json');
define('MEMORY_FILE', DATA_DIR . '/memory.json');
define('SYSTEM_FILE', __DIR__ . '/system.txt');
define('FUNCTIONS_DIR', __DIR__ . '/fnc');
define('CORE_DIR', __DIR__ . '/core');
define('TMP_DIR', __DIR__ . '/tmp');

// === CONFIGURATION CLOUDFLARE AI ===
define('CLOUDFLARE_ACCOUNT_ID', 'PLACE_YOUR_CLOUDFLARE_ID');
define('CLOUDFLARE_AUTH_TOKEN', 'PLACE_YOUT_CLOUDFLARE_AUTH');
define('CLOUDFLARE_MODEL', '@cf/deepseek-ai/deepseek-r1-distill-qwen-32b');

// === PARAMÈTRES DE L'IA ===
define('MAX_TOKENS', 5000);
define('MAX_RETRIES', 2);
define('REQUEST_TIMEOUT', 90);

// === INITIALISATION ===
if (!file_exists(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
if (!file_exists(FUNCTIONS_DIR)) mkdir(FUNCTIONS_DIR, 0755, true);
if (!file_exists(CORE_DIR)) mkdir(CORE_DIR, 0755, true);
if (!file_exists(TMP_DIR)) mkdir(TMP_DIR, 0755, true);

/**
 * Ajoute une conversation à la mémoire avec format amélioré
 */
function add_conversation_to_memory($prompt, $response, $commands = []) {
    $memory_data = load_memory();
    
    $conversation = [
        'id' => $memory_data['last_conversation_id'] + 1,
        'timestamp' => date('Y-m-d H:i:s'),
        'prompt' => $prompt,
        'response' => $response,
        'commands_executed' => $commands
    ];
    
    $memory_data['conversations'][] = $conversation;
    $memory_data['last_conversation_id'] = $conversation['id'];
    
    // Garde seulement les 50 dernières conversations
    if (count($memory_data['conversations']) > 50) {
        $memory_data['conversations'] = array_slice($memory_data['conversations'], -50);
    }
    
    save_memory($memory_data);
    
    return $conversation['id'];
}

/**
 * Récupère l'historique des conversations récentes formaté pour l'IA
 */
function get_recent_conversations($limit = 6) {
    $memory_data = load_memory();
    $conversations = $memory_data['conversations'] ?? [];
    
    return array_slice($conversations, -$limit);
}

/**
 * Lit le contenu d'un fichier de manière sécurisée
 */
function read_file_content($filePath) {
    if (!file_exists($filePath)) return "";
    if (!is_readable($filePath)) return "❌ Permission refusée : $filePath";
    
    $content = file_get_contents($filePath);
    return $content === false ? "" : $content;
}

/**
 * Écrit du contenu dans un fichier de manière sécurisée
 */
function write_file_content($filePath, $content) {
    $dir = dirname($filePath);
    if (!file_exists($dir)) mkdir($dir, 0755, true);
    
    return file_put_contents($filePath, $content) !== false;
}

/**
 * Charge les tâches depuis le fichier JSON
 */
function load_tasks() {
    $content = read_file_content(TASKS_FILE);
    if (empty($content) || strpos($content, '❌') !== false) {
        return ['tasks' => [], 'last_id' => 0, 'created_at' => date('Y-m-d H:i:s')];
    }
    
    $data = json_decode($content, true);
    return $data ?: ['tasks' => [], 'last_id' => 0, 'created_at' => date('Y-m-d H:i:s')];
}

/**
 * Sauvegarde les tâches dans le fichier JSON
 */
function save_tasks($tasks_data) {
    $json_content = json_encode($tasks_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return write_file_content(TASKS_FILE, $json_content);
}

/**
 * Charge la mémoire (historique des conversations)
 */
function load_memory() {
    $content = read_file_content(MEMORY_FILE);
    if (empty($content) || strpos($content, '❌') !== false) {
        return ['conversations' => [], 'last_conversation_id' => 0];
    }
    
    $data = json_decode($content, true);
    return $data ?: ['conversations' => [], 'last_conversation_id' => 0];
}

/**
 * Sauvegarde la mémoire dans le fichier JSON
 */
function save_memory($memory_data) {
    $json_content = json_encode($memory_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return write_file_content(MEMORY_FILE, $json_content);
}

/**
 * Ajoute une nouvelle tâche à la file d'attente
 */
function add_task($prompt) {
    $tasks_data = load_tasks();
    
    $new_task = [
        'id' => $tasks_data['last_id'] + 1,
        'prompt' => trim($prompt),
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'started_at' => null,
        'completed_at' => null,
        'result' => null,
        'error' => null,
        'commands' => []
    ];
    
    $tasks_data['tasks'][] = $new_task;
    $tasks_data['last_id'] = $new_task['id'];
    save_tasks($tasks_data);
    
    return $new_task['id'];
}

/**
 * Met à jour le statut d'une tâche
 */
function update_task_status($task_id, $status, $result = null, $error = null, $commands = null) {
    $tasks_data = load_tasks();
    $task_found = false;
    
    foreach ($tasks_data['tasks'] as &$task) {
        if ($task['id'] == $task_id) {
            $task_found = true;
            $task['status'] = $status;
            
            if ($status === 'processing' && $task['started_at'] === null) {
                $task['started_at'] = date('Y-m-d H:i:s');
            } elseif (in_array($status, ['completed', 'failed'])) {
                $task['completed_at'] = date('Y-m-d H:i:s');
            }
            
            if ($result !== null) $task['result'] = $result;
            if ($error !== null) $task['error'] = $error;
            if ($commands !== null) $task['commands'] = $commands;
            
            break;
        }
    }
    
    if ($task_found) {
        save_tasks($tasks_data);
        return true;
    }
    
    return false;
}

/**
 * Récupère les statistiques des tâches
 */
function get_tasks_stats() {
    $tasks_data = load_tasks();
    $tasks = $tasks_data['tasks'];
    
    $stats = [
        'total' => count($tasks),
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0
    ];
    
    foreach ($tasks as $task) {
        if (isset($stats[$task['status']])) {
            $stats[$task['status']]++;
        }
    }
    
    return $stats;
}

/**
 * Charge le prompt système depuis le fichier
 */
function load_system_prompt() {
    if (!file_exists(SYSTEM_FILE)) {
        return "Tu es un assistant IA qui répond UNIQUEMENT en JSON. Format: {\"response\": \"texte\", \"commands\": []}";
    }
    
    $content = read_file_content(SYSTEM_FILE);
    return strpos($content, '❌') !== false ? 
        "Tu es un assistant IA qui répond UNIQUEMENT en JSON. Format: {\"response\": \"texte\", \"commands\": []}" : 
        $content;
}

/**
 * Charge toutes les fonctions disponibles
 */
function load_available_functions() {
    $functions = [];
    
    if (!file_exists(FUNCTIONS_DIR)) return $functions;
    
    $function_dirs = scandir(FUNCTIONS_DIR);
    
    foreach ($function_dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;
        
        $config_file = FUNCTIONS_DIR . '/' . $dir . '/config.json';
        
        if (file_exists($config_file)) {
            $config_content = read_file_content($config_file);
            $config = json_decode($config_content, true);
            
            if ($config) {
                $functions[$dir] = $config;
            }
        }
    }
    
    return $functions;
}

/**
 * Corrige les commentaires PHP mal formatés
 */
function fix_php_comments($content) {
    // Corrige les commentaires de bloc mal formatés
    $content = preg_replace('/\/\\\\\n(.*?)\n\s*\/\\\\/s', "/**\n * $1\n */", $content);
    $content = preg_replace('/\/\\*(.*?)\\*\/\s*\/\\\\/s', "/**$1*/", $content);
    
    return $content;
}

/**
 * Exécute une commande en utilisant le système de fonctions dynamiques
 * Version avec correction automatique du code PHP
 */
function execute_command($command) {
    $command_type = $command['type'];
    $params = $command['parameters'];
    
    // Vérifie si la fonction existe
    $function_dir = FUNCTIONS_DIR . '/' . $command_type;
    $function_file = $function_dir . '/function.php';
    
    if (!file_exists($function_file)) {
        return "❌ Fonction non trouvée: $command_type";
    }
    
    // Charge la configuration de la fonction
    $config_file = $function_dir . '/config.json';
    $config_content = read_file_content($config_file);
    $config = json_decode($config_content, true);
    
    if (!$config) {
        return "❌ Configuration invalide pour: $command_type";
    }
    
    // Vérifie les paramètres requis
    $required_params = $config['parameters'] ?? [];
    foreach ($required_params as $param => $param_config) {
        if (($param_config['required'] ?? false) && !isset($params[$param])) {
            return "❌ Paramètre manquant: $param";
        }
    }
    
    // Inclut et exécute la fonction avec gestion d'erreurs
    try {
        // Capture toute sortie HTML
        ob_start();
        include_once $function_file;
        $output = ob_get_clean();
        
        if (!function_exists('execute_' . $command_type)) {
            return "❌ Fonction d'exécution non trouvée: execute_$command_type";
        }
        
        // Pour create_file, corrige automatiquement le code PHP
        if ($command_type === 'create_file' && isset($params['content'])) {
            $params['content'] = fix_php_comments($params['content']);
        }
        
        $result = call_user_func('execute_' . $command_type, $params);
        
        return $result;
        
    } catch (Exception $e) {
        // Log l'erreur pour le débogage
        error_log("Command execution error [$command_type]: " . $e->getMessage());
        return "❌ Erreur d'exécution: " . $e->getMessage();
    }
}


/**
 * Nettoie les tâches terminées
 */
function cleanup_completed_tasks() {
    $tasks_data = load_tasks();
    $initial_count = count($tasks_data['tasks']);
    
    $tasks_data['tasks'] = array_filter($tasks_data['tasks'], function($task) {
        return $task['status'] !== 'completed';
    });
    
    $tasks_data['tasks'] = array_values($tasks_data['tasks']);
    $final_count = count($tasks_data['tasks']);
    
    save_tasks($tasks_data);
    
    return $initial_count - $final_count;
}

/**
 * Scanne les routes API existantes
 */
function scan_existing_routes() {
    $routes = [];
    $routes_dir = CORE_DIR . '/routes';
    
    if (!file_exists($routes_dir)) return $routes;
    
    $modules = scandir($routes_dir);
    
    foreach ($modules as $module) {
        if ($module === '.' || $module === '..') continue;
        
        $module_dir = $routes_dir . '/' . $module;
        $index_file = $module_dir . '/index.php';
        
        if (file_exists($index_file)) {
            $content = read_file_content($index_file);
            
            // Détection basique de la méthode HTTP
            $method = 'GET';
            if (strpos($content, "require_method('POST')") !== false) {
                $method = 'POST';
            } elseif (strpos($content, "require_method('PUT')") !== false) {
                $method = 'PUT';
            } elseif (strpos($content, "require_method('DELETE')") !== false) {
                $method = 'DELETE';
            }
            
            $routes[] = [
                'module' => $module,
                'endpoint' => '/api/' . $module,
                'method' => $method,
                'created_at' => date('Y-m-d H:i:s', filemtime($index_file))
            ];
        }
    }
    
    return $routes;
}

/**
 * Efface toute la mémoire des conversations
 */
function clear_memory() {
    $memory_data = ['conversations' => [], 'last_conversation_id' => 0];
    return save_memory($memory_data);
}