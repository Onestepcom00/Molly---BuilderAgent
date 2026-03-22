<?php
/**
 * Interface Builder Agent - Créateur d'API PHP
 * Version scalable avec système de fonctions dynamiques
 */

require_once 'config.php';
require_once 'agent.php';

// Traitement des requêtes AJAX
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'add_task':
            if (isset($_POST['prompt']) && !empty(trim($_POST['prompt']))) {
                $task_id = add_task(trim($_POST['prompt']));
                
                // Démarre le traitement automatique
                process_pending_tasks();
                
                echo json_encode([
                    'success' => true, 
                    'task_id' => $task_id,
                    'message' => 'Tâche ajoutée et en cours de traitement'
                ]);
            } else {
                echo json_encode([
                    'success' => false, 
                    'error' => 'Le message ne peut pas être vide'
                ]);
            }
            exit;
            
        case 'get_tasks':
            $tasks_data = load_tasks();
            echo json_encode($tasks_data);
            exit;
            
        case 'get_stats':
            $stats = get_tasks_stats();
            echo json_encode($stats);
            exit;
            
        case 'get_conversations':
            $conversations = get_recent_conversations(20);
            echo json_encode($conversations);
            exit;
            
        case 'get_routes':
            $routes = scan_existing_routes();
            echo json_encode($routes);
            exit;
            
        case 'get_functions':
            $functions = load_available_functions();
            echo json_encode($functions);
            exit;
            
        case 'process_tasks':
            $processed = process_pending_tasks();
            echo json_encode([
                'success' => true, 
                'processed' => $processed,
                'message' => $processed . ' tâche(s) traitée(s)'
            ]);
            exit;
            
        case 'clear_completed':
            $deleted = cleanup_completed_tasks();
            echo json_encode([
                'success' => true, 
                'deleted' => $deleted,
                'message' => $deleted . ' tâche(s) terminée(s) supprimée(s)'
            ]);
            exit;
            
        case 'clear_memory':
            $result = clear_memory();
            echo json_encode(['success' => $result, 'message' => 'Mémoire effacée']);
            exit;
    }
}

// Initialisation des données pour l'interface
$conversations = get_recent_conversations(10);
$tasks_stats = get_tasks_stats();
$routes = scan_existing_routes();
$functions = load_available_functions();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Builder Agent - Créateur d'API PHP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/json.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/languages/xml.min.js"></script>
    <style>
        :root {
            --primary: #8b5cf6;
            --primary-dark: #7c3aed;
            --primary-light: #a78bfa;
            --secondary: #0f0f23;
            --accent: #10b981;
            --background: #000000;
            --surface: #0f0f23;
            --glass: rgba(15, 15, 35, 0.7);
            --text-primary: #f9fafb;
            --text-secondary: #d1d5db;
            --border: #2a2a4a;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background);
            color: var(--text-primary);
            overflow: hidden;
        }
        
        .glass {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar {
            transition: all 0.3s ease;
        }
        
        .chat-message {
            animation: fadeIn 0.3s ease;
        }
        
        .loading-bar {
            animation: pulse 1.5s infinite;
        }
        
        .think-effect {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .think-effect span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: var(--primary);
            animation: bounce 1.4s infinite ease-in-out both;
        }
        
        .think-effect span:nth-child(1) { animation-delay: -0.32s; }
        .think-effect span:nth-child(2) { animation-delay: -0.16s; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes pulse {
            0% { opacity: 0.6; }
            50% { opacity: 1; }
            100% { opacity: 0.6; }
        }
        
        @keyframes bounce {
            0%, 80%, 100% { 
                transform: scale(0);
            } 40% { 
                transform: scale(1.0);
            }
        }
        
        .code-editor {
            font-family: 'Courier New', monospace;
        }
        
        .route-item {
            transition: all 0.2s ease;
        }
        
        .route-item:hover {
            background-color: rgba(139, 92, 246, 0.1);
        }
        
        .api-preview {
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .response-area {
            background-color: #1e1e1e;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
        }
        
        .send-btn {
            opacity: 0;
            transform: scale(0.8);
            transition: all 0.2s ease;
        }
        
        .send-btn.visible {
            opacity: 1;
            transform: scale(1);
        }
        
        .think-card {
            background: rgba(139, 92, 246, 0.1);
            border: 1px solid rgba(139, 92, 246, 0.3);
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 0.8rem;
            margin: 8px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: var(--surface);
            border-radius: 12px;
            padding: 24px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }
        
        .modal-overlay.active .modal-content {
            transform: scale(1);
        }
        
        .prose-invert pre {
            background: #1a1a2e !important;
            border-radius: 8px;
            padding: 16px;
            overflow-x: auto;
            margin: 16px 0;
        }
        
        .prose-invert code {
            background: rgba(139, 92, 246, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.875em;
        }
        
        .prose-invert pre code {
            background: transparent;
            padding: 0;
            border-radius: 0;
        }
        
        .chat-input-container {
            position: relative;
        }
        
        .chat-input-container:focus-within {
            box-shadow: 0 0 0 2px rgba(139, 92, 246, 0.5);
        }
        
        .logo-glow {
            filter: drop-shadow(0 0 10px rgba(139, 92, 246, 0.5));
        }
        
        .scrollbar-thin::-webkit-scrollbar {
            width: 6px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: rgba(139, 92, 246, 0.5);
            border-radius: 10px;
        }
        
        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: rgba(139, 92, 246, 0.7);
        }

        .api-preview-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
        }

        .api-response-container {
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        .api-response-content {
            height: 100%;
            overflow-y: auto;
        }
        
        .task-indicator {
            transition: all 0.3s ease;
        }
        
        .function-badge {
            background: rgba(139, 92, 246, 0.2);
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        /* Styles Markdown améliorés */
        .markdown-content h1, .markdown-content h2, .markdown-content h3 {
            margin-top: 1.5em;
            margin-bottom: 0.5em;
            font-weight: 600;
        }
        
        .markdown-content h1 { font-size: 1.5em; }
        .markdown-content h2 { font-size: 1.3em; }
        .markdown-content h3 { font-size: 1.1em; }
        
        .markdown-content p {
            margin-bottom: 1em;
            line-height: 1.6;
        }
        
        .markdown-content ul, .markdown-content ol {
            margin-bottom: 1em;
            padding-left: 1.5em;
        }
        
        .markdown-content li {
            margin-bottom: 0.5em;
        }
        
        .markdown-content blockquote {
            border-left: 4px solid var(--primary);
            padding-left: 1em;
            margin: 1em 0;
            font-style: italic;
        }
        
        .markdown-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1em 0;
        }
        
        .markdown-content th, .markdown-content td {
            border: 1px solid var(--border);
            padding: 0.5em;
            text-align: left;
        }
        
        .markdown-content th {
            background: rgba(139, 92, 246, 0.1);
        }
    </style>
</head>
<body class="h-screen flex overflow-hidden">
    <!-- Sidebar -->
    <div class="sidebar w-80 glass flex flex-col h-full">
        <!-- Logo et titre -->
        <div class="p-6 border-b border-gray-800">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-purple-800 flex items-center justify-center logo-glow">
                    <i data-lucide="code-2" class="text-white"></i>
                </div>
                <div>
                    <h1 class="text-xl font-bold text-white">Builder Agent</h1>
                    <p class="text-xs text-purple-300">API Creator</p>
                </div>
            </div>
        </div>
        
        <!-- Actions principales -->
        <div class="p-4 space-y-3">
            <button id="auto-process-btn" class="w-full flex items-center space-x-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white py-3 px-4 rounded-xl transition-all duration-200 shadow-lg">
                <i data-lucide="play" class="w-5 h-5"></i>
                <span class="font-medium">Traitement Auto</span>
            </button>
            <button id="process-tasks-btn" class="w-full flex items-center space-x-3 bg-purple-600 hover:bg-purple-700 text-white py-3 px-4 rounded-xl transition-all duration-200">
                <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                <span class="font-medium">Traiter Tâches</span>
            </button>
        </div>
        
        <!-- Statistiques -->
        <div class="p-4 border-y border-gray-800">
            <div class="grid grid-cols-2 gap-3">
                <div class="text-center p-3 bg-gray-800 rounded-xl">
                    <div class="text-lg font-bold text-purple-400" id="stat-total"><?php echo $tasks_stats['total']; ?></div>
                    <div class="text-xs text-gray-400">Total</div>
                </div>
                <div class="text-center p-3 bg-gray-800 rounded-xl">
                    <div class="text-lg font-bold text-yellow-400" id="stat-pending"><?php echo $tasks_stats['pending']; ?></div>
                    <div class="text-xs text-gray-400">En attente</div>
                </div>
                <div class="text-center p-3 bg-gray-800 rounded-xl">
                    <div class="text-lg font-bold text-blue-400" id="stat-processing"><?php echo $tasks_stats['processing']; ?></div>
                    <div class="text-xs text-gray-400">En cours</div>
                </div>
                <div class="text-center p-3 bg-gray-800 rounded-xl">
                    <div class="text-lg font-bold text-green-400" id="stat-completed"><?php echo $tasks_stats['completed']; ?></div>
                    <div class="text-xs text-gray-400">Terminées</div>
                </div>
            </div>
        </div>
        
        <!-- Routes existantes -->
        <div class="flex-1 overflow-y-auto p-4 scrollbar-thin">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Routes API</h2>
                <span class="text-xs bg-purple-900 text-purple-200 px-2 py-1 rounded-full" id="routes-count"><?php echo count($routes); ?></span>
            </div>
            <div class="space-y-2" id="routes-list">
                <?php foreach ($routes as $route): ?>
                <div class="route-item flex items-center justify-between p-3 rounded-lg cursor-pointer glass border border-transparent hover:border-purple-500/30">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-lg bg-green-900/30 flex items-center justify-center">
                            <i data-lucide="route" class="w-4 h-4 text-green-400"></i>
                        </div>
                        <div>
                            <span class="text-sm font-medium"><?php echo $route['endpoint']; ?></span>
                            <div class="text-xs text-gray-400"><?php echo $route['method']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($routes)): ?>
                <div class="text-center py-4 text-gray-400">
                    <i data-lucide="folder" class="w-8 h-8 mx-auto mb-2"></i>
                    <p class="text-sm">Aucune route créée</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Fonctions disponibles -->
        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-400 uppercase tracking-wider">Fonctions</h2>
                <span class="text-xs bg-blue-900 text-blue-200 px-2 py-1 rounded-full" id="functions-count"><?php echo count($functions); ?></span>
            </div>
            <div class="flex flex-wrap gap-2" id="functions-list">
                <?php foreach ($functions as $name => $config): ?>
                <span class="function-badge text-xs text-purple-300 px-2 py-1 rounded-full">
                    <?php echo $name; ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Contenu principal -->
    <div class="flex-1 flex flex-col h-full">
        <!-- En-tête -->
        <header class="h-16 glass border-b border-gray-800 flex items-center justify-between px-6">
            <div class="flex items-center space-x-4">
                <h2 class="text-lg font-semibold flex items-center">
                    <i data-lucide="bot" class="w-5 h-5 text-purple-400 mr-2"></i>
                    Assistant Builder Agent
                </h2>
                <div class="h-6 w-px bg-gray-700"></div>
                <div class="text-sm text-gray-400">
                    Statut: <span class="text-green-400 font-medium" id="global-status">Actif</span>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <button id="clear-memory-btn" class="flex items-center space-x-2 bg-gray-800 hover:bg-gray-700 text-white py-2 px-3 rounded-lg transition-colors text-sm">
                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                    <span>Effacer Mémoire</span>
                </button>
                <button id="refresh-all-btn" class="flex items-center space-x-2 bg-gray-800 hover:bg-gray-700 text-white py-2 px-3 rounded-lg transition-colors text-sm">
                    <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                    <span>Actualiser</span>
                </button>
            </div>
        </header>
        
        <!-- Conteneur principal -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Zone de chat -->
            <div class="w-1/2 flex flex-col border-r border-gray-800">
                <!-- Messages du chat -->
                <div class="flex-1 overflow-y-auto p-6 space-y-6 scrollbar-thin" id="chat-messages">
                    <!-- Message de bienvenue -->
                    <div class="chat-message">
                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-purple-800 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="bot" class="w-5 h-5 text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="prose prose-invert max-w-none markdown-content">
                                    <p>👋 Bonjour ! Je suis votre assistant Builder Agent.</p>
                                    <p>Je peux vous aider à créer des APIs PHP complètes avec le format StructureOne.</p>
                                    <p>Que souhaitez-vous créer aujourd'hui ?</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Historique des conversations (ordre chronologique) -->
                    <?php foreach ($conversations as $conv): ?>
                    <div class="chat-message">
                        <div class="flex items-start space-x-4 justify-end">
                            <div class="flex-1 min-w-0 flex justify-end">
                                <div class="bg-gradient-to-r from-purple-600 to-purple-800 rounded-xl p-4 max-w-[80%]">
                                    <p><?php echo htmlspecialchars($conv['prompt']); ?></p>
                                </div>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="user" class="w-5 h-5 text-white"></i>
                            </div>
                        </div>
                    </div>
                    <div class="chat-message">
                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-purple-800 flex items-center justify-center flex-shrink-0">
                                <i data-lucide="bot" class="w-5 h-5 text-white"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="prose prose-invert max-w-none markdown-content">
                                    <?php echo formatMarkdownResponse($conv['response']); ?>
                                    <?php if (!empty($conv['commands_executed'])): ?>
                                    <div class="mt-3 p-3 bg-gray-800 rounded-lg">
                                        <p class="text-sm text-gray-400 mb-2">Actions exécutées :</p>
                                        <?php foreach ($conv['commands_executed'] as $cmd): ?>
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-blue-400"><?php echo htmlspecialchars($cmd['type']); ?></span>
                                            <span class="text-gray-500"><?php echo htmlspecialchars($cmd['result']); ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Zone de saisie -->
                <div class="p-6 border-t border-gray-800">
                    <div class="chat-input-container glass rounded-xl p-1 pl-4 flex items-center">
                        <input type="text" id="message-input" placeholder="Décrivez votre demande (ex: Crée une API pour gérer les utilisateurs)..." class="flex-1 bg-transparent text-white placeholder-gray-500 focus:outline-none py-3">
                        <button id="send-btn" class="send-btn bg-gradient-to-r from-purple-600 to-purple-800 text-white rounded-lg p-3 transition-all duration-200 ml-2">
                            <i data-lucide="send" class="w-5 h-5"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Aperçu des résultats -->
            <div class="w-1/2 flex flex-col api-preview-container">
                <!-- En-tête de l'aperçu -->
                <div class="h-14 glass border-b border-gray-800 flex items-center justify-between px-6 flex-shrink-0">
                    <h2 class="text-lg font-semibold flex items-center">
                        <i data-lucide="code-2" class="w-5 h-5 text-purple-400 mr-2"></i>
                        Résultats & Exécution
                    </h2>
                    <div class="flex space-x-2">
                        <button id="clear-completed-btn" class="flex items-center space-x-2 bg-gray-800 hover:bg-gray-700 text-white py-2 px-3 rounded-lg transition-colors text-sm">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                            <span>Nettoyer</span>
                        </button>
                    </div>
                </div>
                
                <!-- Contenu des résultats -->
                <div class="api-response-container p-6">
                    <div class="api-preview h-full glass rounded-xl overflow-hidden">
                        <div class="api-response-content h-full rounded-xl p-6 overflow-y-auto scrollbar-thin space-y-6">
                            <!-- Section tâches actives -->
                            <div>
                                <h3 class="text-lg font-semibold mb-3 flex items-center">
                                    <i data-lucide="list" class="w-5 h-5 text-yellow-400 mr-2"></i>
                                    Tâches Actives
                                </h3>
                                <div class="space-y-3" id="active-tasks">
                                    <!-- Les tâches actives apparaîtront ici -->
                                </div>
                            </div>
                            
                            <!-- Section fichiers créés -->
                            <div>
                                <h3 class="text-lg font-semibold mb-3 flex items-center">
                                    <i data-lucide="folder" class="w-5 h-5 text-green-400 mr-2"></i>
                                    Fichiers Créés
                                </h3>
                                <div class="space-y-2" id="created-files">
                                    <!-- Les fichiers créés apparaîtront ici -->
                                </div>
                            </div>
                            
                            <!-- Section logs -->
                            <div>
                                <h3 class="text-lg font-semibold mb-3 flex items-center">
                                    <i data-lucide="terminal" class="w-5 h-5 text-blue-400 mr-2"></i>
                                    Logs d'Exécution
                                </h3>
                                <div class="bg-gray-900 rounded-lg p-4">
                                    <div class="space-y-2 text-sm font-mono" id="execution-logs">
                                        <!-- Les logs apparaîtront ici -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialiser les icônes Lucide
        lucide.createIcons();
        
        // Initialiser la coloration syntaxique
        hljs.highlightAll();
        
        // Variables d'état
        let autoProcessEnabled = true;
        let isProcessing = false;
        let currentTaskId = null;
        
        // Éléments DOM
        const chatMessages = document.getElementById('chat-messages');
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        const activeTasks = document.getElementById('active-tasks');
        const createdFiles = document.getElementById('created-files');
        const executionLogs = document.getElementById('execution-logs');
        const globalStatus = document.getElementById('global-status');
        
        // Boutons de contrôle
        const autoProcessBtn = document.getElementById('auto-process-btn');
        const processTasksBtn = document.getElementById('process-tasks-btn');
        const clearMemoryBtn = document.getElementById('clear-memory-btn');
        const refreshAllBtn = document.getElementById('refresh-all-btn');
        const clearCompletedBtn = document.getElementById('clear-completed-btn');
        
        // Statistiques
        const statTotal = document.getElementById('stat-total');
        const statPending = document.getElementById('stat-pending');
        const statProcessing = document.getElementById('stat-processing');
        const statCompleted = document.getElementById('stat-completed');
        const routesCount = document.getElementById('routes-count');
        const routesList = document.getElementById('routes-list');
        
        // Chargement initial
        loadAllData();
        
        // Actualisation automatique
        setInterval(() => {
            if (autoProcessEnabled) {
                loadAllData();
                processTasks();
            }
        }, 3000);
        
        // Afficher/masquer le bouton d'envoi
        messageInput.addEventListener('input', () => {
            if (messageInput.value.trim()) {
                sendBtn.classList.add('visible');
            } else {
                sendBtn.classList.remove('visible');
            }
        });
        
        // Envoi de message
        sendBtn.addEventListener('click', sendMessage);
        messageInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });
        
        // Fonction d'envoi de message améliorée
        async function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;
            
            // Ajouter le message de l'utilisateur
            addChatMessage(message, true);
            messageInput.value = '';
            sendBtn.classList.remove('visible');
            messageInput.disabled = true;
            sendBtn.disabled = true;
            
            // Afficher l'indicateur de traitement
            const loadingId = showLoadingIndicator();
            
            try {
                const formData = new FormData();
                formData.append('prompt', message);
                
                const response = await fetch('?action=add_task', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    currentTaskId = result.task_id;
                    
                    // Attendre un peu pour le traitement puis actualiser
                    setTimeout(async () => {
                        removeLoadingIndicator(loadingId);
                        
                        // Attendre que le traitement soit complet
                        await waitForTaskCompletion(result.task_id);
                        
                        // Actualiser toutes les données et rafraîchir le chat
                        await refreshChat();
                        
                        // Réactiver le champ de saisie
                        messageInput.disabled = false;
                        sendBtn.disabled = false;
                        messageInput.focus();
                        
                    }, 1500);
                } else {
                    removeLoadingIndicator(loadingId);
                    addChatMessage('❌ Erreur: ' + result.error, false);
                    messageInput.disabled = false;
                    sendBtn.disabled = false;
                }
            } catch (error) {
                removeLoadingIndicator(loadingId);
                addChatMessage('❌ Erreur réseau: ' + error.message, false);
                messageInput.disabled = false;
                sendBtn.disabled = false;
            }
        }

        // Fonction pour rafraîchir le chat après complétion
        async function refreshChat() {
            try {
                const response = await fetch('?action=get_conversations');
                const conversations = await response.json();
                
                // Recharger les conversations dans le chat
                loadConversationsToChat(conversations);
                
            } catch (error) {
                console.error('Erreur rafraîchissement chat:', error);
            }
        }

        // Fonction pour charger les conversations dans le chat
        function loadConversationsToChat(conversations) {
            // Garder le message de bienvenue
            const welcomeMessage = chatMessages.firstElementChild;
            chatMessages.innerHTML = '';
            chatMessages.appendChild(welcomeMessage);
            
            // Ajouter les conversations dans l'ordre chronologique
            conversations.forEach(conv => {
                addConversationToChat(conv);
            });
            
            // Appliquer la coloration syntaxique
            hljs.highlightAll();
        }

        // Fonction pour ajouter une conversation au chat
        function addConversationToChat(conv) {
            // Message utilisateur
            const userMessageDiv = document.createElement('div');
            userMessageDiv.className = 'chat-message';
            userMessageDiv.innerHTML = `
                <div class="flex items-start space-x-4 justify-end">
                    <div class="flex-1 min-w-0 flex justify-end">
                        <div class="bg-gradient-to-r from-purple-600 to-purple-800 rounded-xl p-4 max-w-[80%]">
                            <p>${escapeHtml(conv.prompt)}</p>
                        </div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="user" class="w-5 h-5 text-white"></i>
                    </div>
                </div>
            `;
            chatMessages.appendChild(userMessageDiv);
            
            // Message IA
            const aiMessageDiv = document.createElement('div');
            aiMessageDiv.className = 'chat-message';
            aiMessageDiv.innerHTML = `
                <div class="flex items-start space-x-4">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-purple-800 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="bot" class="w-5 h-5 text-white"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="prose prose-invert max-w-none markdown-content">
                            ${formatAIResponse(conv.response)}
                            ${conv.commands_executed && conv.commands_executed.length > 0 ? `
                                <div class="mt-3 p-3 bg-gray-800 rounded-lg">
                                    <p class="text-sm text-gray-400 mb-2">Actions exécutées :</p>
                                    ${conv.commands_executed.map(cmd => `
                                        <div class="flex items-center justify-between text-xs mb-1">
                                            <span class="text-blue-400">${escapeHtml(cmd.type)}</span>
                                            <span class="text-gray-500">${escapeHtml(cmd.result)}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            chatMessages.appendChild(aiMessageDiv);
            
            // Faire défiler vers le bas
            chatMessages.scrollTop = chatMessages.scrollHeight;
            lucide.createIcons();
        }

        // Fonction pour attendre la complétion d'une tâche
        async function waitForTaskCompletion(taskId, maxAttempts = 15) {
            for (let attempt = 0; attempt < maxAttempts; attempt++) {
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                try {
                    const response = await fetch('?action=get_tasks');
                    const data = await response.json();
                    
                    const task = data.tasks.find(t => t.id === taskId);
                    if (task && task.status === 'completed') {
                        return true;
                    }
                    if (task && task.status === 'failed') {
                        return false;
                    }
                } catch (error) {
                    console.error('Erreur vérification tâche:', error);
                }
            }
            return false;
        }

 
// Fonction pour formater les réponses de l'IA avec Markdown
function formatAIResponse(response) {
    if (!response) return '';
    
    let formatted = response;
    
    // Supprime les balises <think> et leur contenu
    formatted = formatted.replace(/<think>[\s\S]*?<\/think>/g, '');
    
    // Essaie de détecter et formater le JSON
    try {
        const jsonMatch = formatted.match(/\{[\s\S]*\}/);
        if (jsonMatch) {
            const jsonObj = JSON.parse(jsonMatch[0]);
            formatted = formatted.replace(/\{[\s\S]*\}/, JSON.stringify(jsonObj, null, 2));
        }
    } catch (e) {
        // Ce n'est pas du JSON valide, on continue
    }
    
    // Échappe le HTML restant
    formatted = escapeHtml(formatted);
    
    // Conversion Markdown basique
    formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
    formatted = formatted.replace(/`(.*?)`/g, '<code>$1</code>');
    
    // Gestion des blocs de code (y compris JSON)
    formatted = formatted.replace(/```(\w+)?\n([\s\S]*?)```/g, function(match, lang, code) {
        const language = lang || 'text';
        return `<pre><code class="language-${language}">${escapeHtml(code.trim())}</code></pre>`;
    });
    
    // Gestion des blocs de code inline
    formatted = formatted.replace(/```([^`]+)```/g, '<pre><code>$1</code></pre>');
    
    // Mise en forme des chemins de fichiers
    formatted = formatted.replace(/(\/core\/routes\/[^\s]+)/g, '<code>$1</code>');
    formatted = formatted.replace(/(\/tmp\/[^\s]+)/g, '<code>$1</code>');
    
    // Mise en forme des actions
    formatted = formatted.replace(/(✅|❌|⚠️)\s*(.+?)(?=\n|$)/g, '<div class="flex items-center space-x-2 my-1"><span class="text-lg">$1</span><span>$2</span></div>');
    
    // Conversion des sauts de ligne en paragraphes
    const paragraphs = formatted.split(/\n\n+/);
    formatted = paragraphs.map(p => {
        p = p.replace(/\n/g, '<br>');
        return p.trim() ? `<p>${p}</p>` : '';
    }).join('');
    
    return formatted;
}

        // Fonction pour ajouter un message au chat
        function addChatMessage(content, isUser = false) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message';
            
            if (isUser) {
                messageDiv.innerHTML = `
                    <div class="flex items-start space-x-4 justify-end">
                        <div class="flex-1 min-w-0 flex justify-end">
                            <div class="bg-gradient-to-r from-purple-600 to-purple-800 rounded-xl p-4 max-w-[80%]">
                                <p>${escapeHtml(content)}</p>
                            </div>
                        </div>
                        <div class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="user" class="w-5 h-5 text-white"></i>
                        </div>
                    </div>
                `;
            } else {
                messageDiv.innerHTML = `
                    <div class="flex items-start space-x-4">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-purple-800 flex items-center justify-center flex-shrink-0">
                            <i data-lucide="bot" class="w-5 h-5 text-white"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="prose prose-invert max-w-none markdown-content">
                                ${formatAIResponse(content)}
                            </div>
                        </div>
                    </div>
                `;
            }
            
            chatMessages.appendChild(messageDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            lucide.createIcons();
            
            // Appliquer la coloration syntaxique
            hljs.highlightAll();
        }
        
        // Afficher un indicateur de chargement
        function showLoadingIndicator() {
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'chat-message';
            loadingDiv.id = 'loading-indicator';
            loadingDiv.innerHTML = `
                <div class="flex items-start space-x-4">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-600 to-purple-800 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="bot" class="w-5 h-5 text-white"></i>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4">
                        <div class="think-effect">
                            <span></span>
                            <span></span>
                            <span></span>
                        </div>
                    </div>
                </div>
            `;
            chatMessages.appendChild(loadingDiv);
            chatMessages.scrollTop = chatMessages.scrollHeight;
            return 'loading-indicator';
        }
        
        // Supprimer l'indicateur de chargement
        function removeLoadingIndicator(id) {
            const element = document.getElementById(id);
            if (element) {
                element.remove();
            }
        }
        
        // Charger toutes les données
        async function loadAllData() {
            try {
                await Promise.all([
                    loadTasks(),
                    loadStats(),
                    loadRoutes(),
                    loadConversations()
                ]);
            } catch (error) {
                console.error('Erreur chargement données:', error);
            }
        }
        
        // Charger les tâches
        async function loadTasks() {
            try {
                const response = await fetch('?action=get_tasks');
                const data = await response.json();
                displayTasks(data.tasks);
                updateExecutionLogs(data.tasks);
            } catch (error) {
                console.error('Erreur chargement tâches:', error);
            }
        }
        
        // Afficher les tâches
        function displayTasks(tasks) {
            const activeTasksList = tasks.filter(task => 
                task.status === 'pending' || task.status === 'processing'
            ).slice(-5);
            
            activeTasks.innerHTML = '';
            
            if (activeTasksList.length === 0) {
                activeTasks.innerHTML = `
                    <div class="text-center py-4 text-gray-400">
                        <i data-lucide="check-circle" class="w-8 h-8 mx-auto mb-2"></i>
                        <p class="text-sm">Aucune tâche active</p>
                    </div>
                `;
                return;
            }
            
            activeTasksList.forEach(task => {
                const taskElement = createTaskElement(task);
                activeTasks.appendChild(taskElement);
            });
            
            lucide.createIcons();
        }
        
        // Créer un élément tâche
        function createTaskElement(task) {
            const div = document.createElement('div');
            div.className = 'bg-gray-800 rounded-xl p-4 border border-gray-700';
            
            const statusConfig = {
                'pending': { icon: 'clock', color: 'text-yellow-400', text: 'EN ATTENTE' },
                'processing': { icon: 'refresh-cw', color: 'text-blue-400', text: 'EN COURS' },
                'completed': { icon: 'check-circle', color: 'text-green-400', text: 'TERMINÉE' },
                'failed': { icon: 'x-circle', color: 'text-red-400', text: 'ÉCHEC' }
            };
            
            const status = statusConfig[task.status];
            
            div.innerHTML = `
                <div class="flex items-start justify-between mb-2">
                    <div class="flex items-center space-x-2">
                        <i data-lucide="${status.icon}" class="w-4 h-4 ${status.color}"></i>
                        <span class="text-xs font-semibold ${status.color}">${status.text}</span>
                    </div>
                    <span class="text-xs text-gray-400">#${task.id}</span>
                </div>
                <p class="text-sm text-gray-300 mb-2 line-clamp-2">${escapeHtml(task.prompt)}</p>
                <div class="flex items-center justify-between text-xs text-gray-400">
                    <span>${formatTime(task.created_at)}</span>
                </div>
                ${task.status === 'processing' ? `
                    <div class="mt-2">
                        <div class="w-full bg-gray-700 rounded-full h-1">
                            <div class="bg-blue-400 h-1 rounded-full loading-bar"></div>
                        </div>
                    </div>
                ` : ''}
            `;
            
            return div;
        }
        
        // Charger les statistiques
        async function loadStats() {
            try {
                const response = await fetch('?action=get_stats');
                const stats = await response.json();
                
                statTotal.textContent = stats.total;
                statPending.textContent = stats.pending;
                statProcessing.textContent = stats.processing;
                statCompleted.textContent = stats.completed;
                
                // Mettre à jour le statut global
                if (stats.processing > 0) {
                    globalStatus.textContent = 'Traitement en cours';
                    globalStatus.className = 'text-blue-400 font-medium';
                } else if (stats.pending > 0) {
                    globalStatus.textContent = 'Tâches en attente';
                    globalStatus.className = 'text-yellow-400 font-medium';
                } else {
                    globalStatus.textContent = 'Prêt';
                    globalStatus.className = 'text-green-400 font-medium';
                }
                
            } catch (error) {
                console.error('Erreur chargement stats:', error);
            }
        }
        
        // Charger les routes
        async function loadRoutes() {
            try {
                const response = await fetch('?action=get_routes');
                const routes = await response.json();
                
                routesCount.textContent = routes.length;
                
                if (routes.length === 0) {
                    routesList.innerHTML = `
                        <div class="text-center py-4 text-gray-400">
                            <i data-lucide="folder" class="w-8 h-8 mx-auto mb-2"></i>
                            <p class="text-sm">Aucune route créée</p>
                        </div>
                    `;
                    return;
                }
                
                routesList.innerHTML = '';
                routes.forEach(route => {
                    const routeElement = createRouteElement(route);
                    routesList.appendChild(routeElement);
                });
                
                lucide.createIcons();
                
            } catch (error) {
                console.error('Erreur chargement routes:', error);
            }
        }
        
        // Créer un élément route
        function createRouteElement(route) {
            const div = document.createElement('div');
            div.className = 'route-item flex items-center justify-between p-3 rounded-lg cursor-pointer glass border border-transparent hover:border-purple-500/30';
            
            const methodColors = {
                'GET': 'green',
                'POST': 'blue',
                'PUT': 'yellow',
                'DELETE': 'red'
            };
            
            const color = methodColors[route.method] || 'purple';
            
            div.innerHTML = `
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-lg bg-${color}-900/30 flex items-center justify-center">
                        <i data-lucide="route" class="w-4 h-4 text-${color}-400"></i>
                    </div>
                    <div>
                        <span class="text-sm font-medium">${route.endpoint}</span>
                        <div class="text-xs text-gray-400">${route.method}</div>
                    </div>
                </div>
            `;
            
            return div;
        }
        
        // Charger les conversations
        async function loadConversations() {
            try {
                const response = await fetch('?action=get_conversations');
                const conversations = await response.json();
                
                // Mettre à jour les fichiers créés
                updateCreatedFiles(conversations);
                
            } catch (error) {
                console.error('Erreur chargement conversations:', error);
            }
        }
        
        // Mettre à jour les fichiers créés
        function updateCreatedFiles(conversations) {
            const recentConversations = conversations.slice(-10);
            const fileCommands = [];
            
            recentConversations.forEach(conv => {
                if (conv.commands_executed) {
                    conv.commands_executed.forEach(cmd => {
                        if (cmd.type === 'create_file' && cmd.result && cmd.result.includes('✅')) {
                            fileCommands.push({
                                file_path: cmd.parameters.file_path,
                                timestamp: conv.timestamp,
                                result: cmd.result
                            });
                        }
                    });
                }
            });
            
            createdFiles.innerHTML = '';
            
            if (fileCommands.length === 0) {
                createdFiles.innerHTML = `
                    <div class="text-center py-4 text-gray-400">
                        <i data-lucide="file" class="w-8 h-8 mx-auto mb-2"></i>
                        <p class="text-sm">Aucun fichier créé</p>
                    </div>
                `;
                return;
            }
            
            fileCommands.slice(-8).forEach(file => {
                const fileDiv = document.createElement('div');
                fileDiv.className = 'flex items-center space-x-3 p-3 bg-gray-800 rounded-lg';
                
                fileDiv.innerHTML = `
                    <i data-lucide="file" class="w-4 h-4 text-green-400"></i>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-white truncate">${file.file_path}</div>
                        <div class="text-xs text-gray-400">${formatTime(file.timestamp)}</div>
                    </div>
                    <span class="text-xs bg-green-900 text-green-200 px-2 py-1 rounded-full">Créé</span>
                `;
                
                createdFiles.appendChild(fileDiv);
            });
            
            lucide.createIcons();
        }
        
        // Mettre à jour les logs d'exécution
        function updateExecutionLogs(tasks) {
            const recentTasks = tasks.slice(-10);
            executionLogs.innerHTML = '';
            
            recentTasks.forEach(task => {
                const logDiv = document.createElement('div');
                logDiv.className = 'flex items-center justify-between';
                
                const statusColor = task.status === 'completed' ? 'text-green-400' : 
                                 task.status === 'failed' ? 'text-red-400' : 
                                 task.status === 'processing' ? 'text-blue-400' : 'text-yellow-400';
                
                logDiv.innerHTML = `
                    <span class="text-gray-400">[${formatTime(task.created_at)}]</span>
                    <span class="${statusColor}">${task.status.toUpperCase()}</span>
                    <span class="text-gray-300 truncate flex-1 mx-2">${escapeHtml(task.prompt.substring(0, 30))}...</span>
                `;
                
                executionLogs.appendChild(logDiv);
            });
        }
        
        // Traitement des tâches
        async function processTasks() {
            if (isProcessing) return;
            
            isProcessing = true;
            try {
                const response = await fetch('?action=process_tasks');
                const result = await response.json();
                
                if (result.success && result.processed > 0) {
                    loadAllData();
                }
            } catch (error) {
                console.error('Erreur traitement tâches:', error);
            } finally {
                isProcessing = false;
            }
        }
        
        // Gestion des boutons
        autoProcessBtn.addEventListener('click', () => {
            autoProcessEnabled = !autoProcessEnabled;
            autoProcessBtn.innerHTML = autoProcessEnabled ? 
                '<i data-lucide="pause" class="w-5 h-5"></i><span class="font-medium">Pause Auto</span>' :
                '<i data-lucide="play" class="w-5 h-5"></i><span class="font-medium">Traitement Auto</span>';
            autoProcessBtn.className = autoProcessEnabled ?
                'w-full flex items-center space-x-3 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white py-3 px-4 rounded-xl transition-all duration-200 shadow-lg' :
                'w-full flex items-center space-x-3 bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-700 hover:to-yellow-800 text-white py-3 px-4 rounded-xl transition-all duration-200 shadow-lg';
        });
        
        processTasksBtn.addEventListener('click', () => {
            processTasks();
        });
        
        clearMemoryBtn.addEventListener('click', async () => {
            if (confirm('Effacer toute la mémoire des conversations ?')) {
                try {
                    const response = await fetch('?action=clear_memory');
                    const result = await response.json();
                    
                    if (result.success) {
                        location.reload();
                    }
                } catch (error) {
                    console.error('Erreur effacement mémoire:', error);
                }
            }
        });
        
        refreshAllBtn.addEventListener('click', () => {
            loadAllData();
        });
        
        clearCompletedBtn.addEventListener('click', async () => {
            if (confirm('Supprimer toutes les tâches terminées ?')) {
                try {
                    const response = await fetch('?action=clear_completed');
                    const result = await response.json();
                    
                    if (result.success) {
                        loadAllData();
                    }
                } catch (error) {
                    console.error('Erreur nettoyage:', error);
                }
            }
        });
        
        // Fonctions utilitaires
        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        function formatTime(dateString) {
            return new Date(dateString).toLocaleTimeString('fr-FR', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
        
        // Initialisation
        lucide.createIcons();
        hljs.highlightAll();
        console.log('Builder Agent initialisé avec succès!');
    </script>
</body>
</html>
<?php
/**
 * Fonction pour formater les réponses Markdown côté serveur
 */
function formatMarkdownResponse($response) {
    if (empty($response)) return '';
    
    // Supprime les balises <think> et leur contenu
    $formatted = preg_replace('/<think>[\s\S]*?<\/think>/', '', $response);
    
    $formatted = htmlspecialchars($formatted);
    
    // Conversion Markdown basique
    $formatted = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $formatted);
    $formatted = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $formatted);
    $formatted = preg_replace('/`(.*?)`/', '<code>$1</code>', $formatted);
    
    // Gestion des blocs de code
    $formatted = preg_replace_callback('/```(\w+)?\n([\s\S]*?)```/', function($matches) {
        $lang = $matches[1] ?? 'text';
        $code = htmlspecialchars(trim($matches[2]));
        return "<pre><code class=\"language-{$lang}\">{$code}</code></pre>";
    }, $formatted);
    
    // Mise en forme des chemins de fichiers
    $formatted = preg_replace('/(\/core\/routes\/[^\s]+)/', '<code>$1</code>', $formatted);
    $formatted = preg_replace('/(\/tmp\/[^\s]+)/', '<code>$1</code>', $formatted);
    
    // Mise en forme des actions
    $formatted = preg_replace('/(✅|❌|⚠️)\s*(.+?)(?=\n|$)/', '<div class="flex items-center space-x-2 my-1"><span class="text-lg">$1</span><span>$2</span></div>', $formatted);
    
    // Conversion des sauts de ligne
    $formatted = preg_replace('/\n\n/', '</p><p>', $formatted);
    $formatted = preg_replace('/\n/', '<br>', $formatted);
    
    return "<p>{$formatted}</p>";
}
?>