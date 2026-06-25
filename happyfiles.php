<?php
session_start();

// Cross-platform compatibility detection
define('IS_WINDOWS', DIRECTORY_SEPARATOR === '\\');
define('IS_LINUX', !IS_WINDOWS);

// ========== INTEGRAÇÃO COM HAPPYWP ==========
// Usa a mesma sessão de login do happymanagerwp.php
// Credenciais do banco de dados iguais às do api.php
// ==================================================

// Configuration
define('FM_ROOT_PATH', __DIR__);
define('FM_SESSION_TIMEOUT', 3600); // 1 hour

// Database Configuration (mesmas credenciais do api.php)
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'DB_USER');
define('DB_PASSWORD', 'DB_PASSWORD');
define('DB_NAME', 'mysql');
// Linux MySQL socket support (comment out to use TCP)
// define('DB_SOCKET', '/var/run/mysqld/mysqld.sock');

// Security: Disable dangerous functions
if (function_exists('exec')) {
    ini_set('disable_functions', 'exec,shell_exec,system,passthru,proc_open,popen');
}

// Database connection with cross-platform support
function getDBConnection() {
    try {
        // Build DSN with socket support for Linux
        $dsn = "mysql:charset=utf8";
        
        if (defined('DB_SOCKET') && IS_LINUX && file_exists(constant('DB_SOCKET'))) {
            // Use Unix socket on Linux if available
            $dsn .= ";unix_socket=" . constant('DB_SOCKET');
        } else {
            // Use TCP connection
            $dsn .= ";host=" . DB_HOST;
            // Add port if specified
            if (defined('DB_PORT')) {
                $dsn .= ";port=" . constant('DB_PORT');
            }
        }
        
        $pdo = new PDO($dsn, DB_USERNAME, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}

// Bancos de dados do sistema que devem ser ocultos
$HIDDEN_DATABASES = ['cyberpanel', 'information_schema', 'mysql', 'performance_schema', 'sys'];

// Get databases list (filtra bancos do sistema)
function getDatabases() {
    global $HIDDEN_DATABASES;
    $pdo = getDBConnection();
    if (!$pdo) return [];
    
    try {
        $stmt = $pdo->query("SHOW DATABASES");
        $all = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_filter($all, function($db) use ($HIDDEN_DATABASES) {
            return !in_array($db, $HIDDEN_DATABASES);
        }));
    } catch (PDOException $e) {
        return [];
    }
}

// Get tables from database with detailed error information
function getTables($database) {
    $pdo = getDBConnection();
    if (!$pdo) {
        return ['error' => 'Database connection failed. Please check your database configuration.'];
    }
    
    try {
        // Validate database name
        if (empty($database)) {
            return ['error' => 'No database selected'];
        }
        
        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE " . $pdo->quote($database));
        if ($stmt->rowCount() === 0) {
            return ['error' => "Database '$database' does not exist"];
        }
        
        $pdo->exec("USE `" . str_replace('`', '``', $database) . "`");
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return ['tables' => $tables, 'count' => count($tables)];
    } catch (PDOException $e) {
        error_log("getTables error: " . $e->getMessage());
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
}

// Execute SQL query
function executeQuery($database, $query) {
    $pdo = getDBConnection();
    if (!$pdo) return ['error' => 'Database connection failed'];
    
    try {
        $pdo->exec("USE `$database`");
        $stmt = $pdo->prepare($query);
        $stmt->execute();
        
        if (stripos(trim($query), 'SELECT') === 0 || stripos(trim($query), 'SHOW') === 0 || stripos(trim($query), 'DESC') === 0) {
            return ['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        } else {
            return ['message' => 'Query executed successfully. Rows affected: ' . $stmt->rowCount()];
        }
    } catch (PDOException $e) {
        return ['error' => $e->getMessage()];
    }
}

// Cross-platform path normalization
function normalizePath($path) {
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    return rtrim($path, DIRECTORY_SEPARATOR);
}

// Check file/directory permissions (enhanced for Linux)
function checkPermissions($path) {
    $permissions = [];
    
    if (!file_exists($path)) {
        return ['exists' => false];
    }
    
    $permissions['exists'] = true;
    $permissions['readable'] = is_readable($path);
    $permissions['writable'] = is_writable($path);
    $permissions['executable'] = is_executable($path);
    
    if (IS_LINUX) {
        // Get detailed Unix permissions
        $perms = fileperms($path);
        $permissions['octal'] = substr(sprintf('%o', $perms), -3);
        $permissions['owner_read'] = ($perms & 0x0100) ? true : false;
        $permissions['owner_write'] = ($perms & 0x0080) ? true : false;
        $permissions['owner_execute'] = ($perms & 0x0040) ? true : false;
        $permissions['group_read'] = ($perms & 0x0020) ? true : false;
        $permissions['group_write'] = ($perms & 0x0010) ? true : false;
        $permissions['group_execute'] = ($perms & 0x0008) ? true : false;
        $permissions['other_read'] = ($perms & 0x0004) ? true : false;
        $permissions['other_write'] = ($perms & 0x0002) ? true : false;
        $permissions['other_execute'] = ($perms & 0x0001) ? true : false;
        
        // Get owner information if possible
        if (function_exists('posix_getpwuid') && function_exists('fileowner')) {
            $owner = posix_getpwuid(fileowner($path));
            $permissions['owner'] = $owner['name'] ?? 'unknown';
        }
        
        if (function_exists('posix_getgrgid') && function_exists('filegroup')) {
            $group = posix_getgrgid(filegroup($path));
            $permissions['group'] = $group['name'] ?? 'unknown';
        }
    }
    
    return $permissions;
}

// Enhanced directory tree building with permission checks
function buildDirectoryTree($path, $basePath = '') {
    $tree = [];
    if (!is_dir($path)) return $tree;
    
    $permissions = checkPermissions($path);
    if (!$permissions['readable']) {
        return $tree; // Skip unreadable directories
    }
    
    $items = @scandir($path); // Suppress errors for permission issues
    if ($items === false) return $tree;
    
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $itemPath = $path . DIRECTORY_SEPARATOR . $item;
        $relativePath = $basePath ? $basePath . '/' . $item : $item;
        
        if (is_dir($itemPath)) {
            $itemPermissions = checkPermissions($itemPath);
            $tree[] = [
                'name' => $item,
                'path' => str_replace('\\', '/', $relativePath),
                'type' => 'folder',
                'permissions' => $itemPermissions,
                'children' => $itemPermissions['readable'] ? buildDirectoryTree($itemPath, $relativePath) : []
            ];
        } else {
            // Add files to the tree
            $tree[] = [
                'name' => $item,
                'path' => str_replace('\\', '/', $relativePath),
                'type' => 'file',
                'size' => filesize($itemPath),
                'icon' => getFileIcon($item)
            ];
        }
    }
    
    return $tree;
}

// CSRF Token generation
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// CSRF Token validation
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Authentication check — usa sessão do HappyWP
function isAuthenticated() {
    return isset($_SESSION['logado']) && $_SESSION['logado'] === true;
}

// Update last activity
function updateLastActivity() {
    $_SESSION['last_activity'] = time();
}

// Enhanced path sanitization for cross-platform security
function sanitizePath($path) {
    // Remove directory traversal attempts
    $path = str_replace(['../', '..\\', '../', '..\\'], '', $path);
    
    // Remove dangerous characters
    $path = str_replace(['<', '>', '|', ':', '*', '?', '"'], '', $path);
    
    // Handle null bytes (security)
    $path = str_replace(chr(0), '', $path);
    
    // Normalize directory separators
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    
    // Remove leading/trailing separators
    $path = trim($path, DIRECTORY_SEPARATOR);
    
    // Additional Linux-specific checks
    if (IS_LINUX) {
        // Remove leading dots (hidden files protection)
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $parts = array_filter($parts, function($part) {
            return !empty($part) && $part !== '.' && $part !== '..';
        });
        $path = implode(DIRECTORY_SEPARATOR, $parts);
    }
    
    return $path;
}

// Enhanced real path validation with cross-platform support
function getRealPath($path) {
    $basePath = realpath(FM_ROOT_PATH);
    if ($basePath === false) {
        error_log("Invalid FM_ROOT_PATH: " . FM_ROOT_PATH);
        return false;
    }
    
    // Normalize the base path
    $basePath = normalizePath($basePath);
    
    if (empty($path)) {
        return $basePath;
    }
    
    $sanitizedPath = sanitizePath($path);
    $fullPath = $basePath . DIRECTORY_SEPARATOR . $sanitizedPath;
    
    // Resolve the real path
    $realPath = realpath($fullPath);
    
    // If realpath fails, check if parent directory exists (for new files)
    if ($realPath === false) {
        $parentDir = dirname($fullPath);
        $realParent = realpath($parentDir);
        
        if ($realParent !== false && strpos($realParent, $basePath) === 0) {
            // Parent is valid, return the constructed path for new files
            return $fullPath;
        }
        
        // Default to base path if all else fails
        return $basePath;
    }
    
    // Normalize and security check
    $realPath = normalizePath($realPath);
    
    // Ensure the real path is within the base path (security check)
    if (strpos($realPath, $basePath) !== 0) {
        error_log("Path traversal attempt detected: " . $path);
        return $basePath;
    }
    
    return $realPath;
}

// Format file size
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

// Get file icon based on extension
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $icons = [
        'php' => '🐘',
        'html' => '🌐',
        'css' => '🎨',
        'js' => '⚡',
        'json' => '📋',
        'xml' => '📄',
        'txt' => '📝',
        'md' => '📖',
        'pdf' => '📕',
        'doc' => '📘',
        'docx' => '📘',
        'xls' => '📗',
        'xlsx' => '📗',
        'ppt' => '📙',
        'pptx' => '📙',
        'zip' => '📦',
        'rar' => '📦',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'svg' => '🖼️',
        'mp3' => '🎵',
        'mp4' => '🎬',
        'avi' => '🎬',
        'exe' => '⚙️',
        'dll' => '⚙️',
        'htaccess' => '⚙️',
    ];
    
    return isset($icons[$ext]) ? $icons[$ext] : '📄';
}

// Check if file is editable
function isEditableFile($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $editableExts = ['php', 'html', 'css', 'js', 'json', 'xml', 'txt', 'md', 'py', 'java', 'c', 'cpp', 'h', 'sql', 'ini', 'conf', 'log', 'htaccess'];
    return in_array($ext, $editableExts) || basename($filename) === '.htaccess' || empty($ext);
}

// Handle file operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAuthenticated()) {
    updateLastActivity();
    
    $action = $_POST['action'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrfToken)) {
        die('CSRF token validation failed');
    }
    
    switch ($action) {
        case 'upload':
            if (isset($_FILES['file'])) {
                $currentDir = getRealPath($_POST['current_dir'] ?? '');
                $uploadPath = $currentDir . DIRECTORY_SEPARATOR . basename($_FILES['file']['name']);
                
                if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadPath)) {
                    $message = 'File uploaded successfully';
                } else {
                    $error = 'Failed to upload file';
                }
            }
            break;
            
        case 'delete':
            $filePath = getRealPath($_POST['path'] ?? '');
            if (is_file($filePath)) {
                unlink($filePath) ? $message = 'File deleted' : $error = 'Failed to delete file';
            } elseif (is_dir($filePath)) {
                rmdir($filePath) ? $message = 'Folder deleted' : $error = 'Failed to delete folder';
            }
            break;
            
        case 'rename':
            $oldPath = getRealPath($_POST['old_path'] ?? '');
            $newName = basename(sanitizePath($_POST['new_name'] ?? ''));
            $newPath = dirname($oldPath) . DIRECTORY_SEPARATOR . $newName;
            
            rename($oldPath, $newPath) ? $message = 'Item renamed' : $error = 'Failed to rename';
            break;
            
        case 'create_folder':
            $currentDir = getRealPath($_POST['current_dir'] ?? '');
            $folderName = sanitizePath($_POST['folder_name'] ?? '');
            $folderPath = $currentDir . DIRECTORY_SEPARATOR . $folderName;
            
            mkdir($folderPath) ? $message = 'Folder created' : $error = 'Failed to create folder';
            break;
            
        case 'create_file':
            $currentDir = getRealPath($_POST['current_dir'] ?? '');
            $fileName = sanitizePath($_POST['file_name'] ?? '');
            $filePath = $currentDir . DIRECTORY_SEPARATOR . $fileName;
            
            file_put_contents($filePath, '') !== false ? $message = 'File created' : $error = 'Failed to create file';
            break;
            
        case 'save_file':
            $filePath = getRealPath($_POST['file_path'] ?? '');
            $content = $_POST['content'] ?? '';
            
            file_put_contents($filePath, $content) !== false ? $message = 'File saved' : $error = 'Failed to save file';
            break;
            
        case 'execute_sql':
            $database = $_POST['database'] ?? '';
            $query = $_POST['query'] ?? '';
            
            if ($database && $query) {
                $sqlResult = executeQuery($database, $query);
                if (isset($sqlResult['error'])) {
                    $error = 'SQL Error: ' . $sqlResult['error'];
                } elseif (isset($sqlResult['data'])) {
                    $message = 'Query executed successfully. ' . count($sqlResult['data']) . ' rows returned.';
                    $_SESSION['sql_result'] = $sqlResult['data'];
                    $_SESSION['sql_query'] = $query;
                    $_SESSION['sql_database'] = $database;
                } else {
                    $message = $sqlResult['message'];
                    $_SESSION['sql_result'] = null;
                }
            }
            break;
            
        case 'get_table_structure':
            $database = $_POST['database'] ?? '';
            $table = $_POST['table'] ?? '';
            
            if ($database && $table) {
                $structureResult = executeQuery($database, "DESCRIBE `$table`");
                if (isset($structureResult['data'])) {
                    $_SESSION['table_structure'] = $structureResult['data'];
                    $_SESSION['current_table'] = $table;
                    $message = "Table structure for '$table' loaded.";
                }
            }
            break;
    }
    
    header('Location: ' . $_SERVER['PHP_SELF'] . '?dir=' . urlencode($_POST['current_dir'] ?? '') . '&tab=' . ($_POST['tab'] ?? 'files'));
    exit;
}

// Handle download
if (isset($_GET['download']) && isAuthenticated()) {
    updateLastActivity();
    $filePath = getRealPath($_GET['download']);
    
    if (is_file($filePath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }
}

// Handle logout — redireciona para o dashboard principal
if (isset($_GET['logout'])) {
    header('Location: happymanagerwp.php?logout=true');
    exit;
}

// Check authentication — redireciona para login do HappyWP
if (!isAuthenticated()) {
    header('Location: happymanagerwp.php');
    exit;
}

updateLastActivity();

// ========== AJAX ENDPOINTS (devem vir ANTES do HTML) ==========
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_tables' && isAuthenticated()) {
    $database = $_GET['database'] ?? '';
    header('Content-Type: application/json');
    $result = getTables($database);
    echo json_encode($result);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'clear_results' && isAuthenticated()) {
    unset($_SESSION['sql_result'], $_SESSION['sql_query'], $_SESSION['sql_database']);
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Get current directory and tab
$currentDir = $_GET['dir'] ?? '';
$currentTab = $_GET['tab'] ?? 'files';
$currentPath = getRealPath($currentDir);
$relativePath = str_replace(realpath(FM_ROOT_PATH), '', $currentPath);
$relativePath = trim($relativePath, DIRECTORY_SEPARATOR);

// Get directory tree
$directoryTree = buildDirectoryTree(realpath(FM_ROOT_PATH));

// Get databases for sidebar
$databases = getDatabases();

// Handle file editing
$editFile = null;
$editContent = '';
if (isset($_GET['edit']) && isAuthenticated()) {
    $editFile = getRealPath($_GET['edit']);
    if (is_file($editFile) && isEditableFile($editFile)) {
        $editContent = file_get_contents($editFile);
    } else {
        $editFile = null;
    }
}

// Get directory contents
$items = [];
if (is_dir($currentPath)) {
    $files = scandir($currentPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $currentPath . DIRECTORY_SEPARATOR . $file;
        $relativePath = $currentDir ? $currentDir . '/' . $file : $file;
        $permissions = checkPermissions($filePath);
        
        $items[] = [
            'name' => $file,
            'path' => str_replace('\\', '/', $relativePath),
            'is_dir' => is_dir($filePath),
            'size' => is_file($filePath) ? filesize($filePath) : 0,
            'modified' => filemtime($filePath),
            'icon' => is_dir($filePath) ? '📁' : getFileIcon($file),
            'editable' => is_file($filePath) && isEditableFile($file),
            'permissions' => $permissions,
            'owner' => $permissions['owner'] ?? 'unknown',
            'group' => $permissions['group'] ?? 'unknown',
            'octal' => $permissions['octal'] ?? '---'
        ];
    }
    
    // Sort: directories first, then files
    usort($items, function($a, $b) {
        if ($a['is_dir'] && !$b['is_dir']) return -1;
        if (!$a['is_dir'] && $b['is_dir']) return 1;
        return strcasecmp($a['name'], $b['name']);
    });
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureFileHub - Cross-Platform File Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs/loader.min.js"></script>
    <style>
        .monaco-editor {
            height: 500px;
        }
        .tree-item {
            cursor: pointer;
            user-select: none;
        }
        .tree-item:hover {
            background-color: #f3f4f6;
        }
        .tree-children {
            display: none;
            margin-left: 20px;
            transition: all 0.3s ease;
        }
        .tree-children.expanded {
            display: block;
        }
        .sidebar {
            height: calc(100vh - 70px);
            overflow-y: auto;
        }
        .main-content {
            height: calc(100vh - 70px);
            overflow-y: auto;
        }
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: rgba(0, 0, 0, 0.7);
            animation: fadeIn 0.3s;
        }
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background-color: #fff;
            border-radius: 8px;
            width: 95%;
            height: 90%;
            max-width: 1400px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s;
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px 8px 0 0;
        }
        .modal-body {
            flex: 1;
            overflow: hidden;
            padding: 0;
            display: flex;
            flex-direction: column;
        }
        .modal-body form {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f9fafb;
            border-radius: 0 0 8px 8px;
        }
        #modalEditor {
            width: 100%;
            height: 100%;
            flex: 1;
            min-height: 0;
        }
        .close-btn {
            font-size: 28px;
            font-weight: bold;
            color: white;
            cursor: pointer;
            line-height: 20px;
            transition: transform 0.2s;
        }
        .close-btn:hover {
            transform: scale(1.2);
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        /* Collapsible Tree Icons */
        .tree-toggle {
            display: inline-block;
            width: 16px;
            transition: transform 0.3s;
        }
        .tree-toggle.collapsed {
            transform: rotate(0deg);
        }
        .tree-toggle.expanded {
            transform: rotate(90deg);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Enhanced Professional Header -->
    <header class="bg-gradient-to-r from-blue-600 to-indigo-700 text-white shadow-xl">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <!-- Left: Logo and Title -->
                <div class="flex items-center space-x-4">
                    <div class="bg-white bg-opacity-20 p-2 rounded-lg">
                        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight">Happy Files</h1>
                        <p class="text-xs opacity-90">v2.0 • Gerenciador de Arquivos</p>
                    </div>
                </div>

                <!-- Center: Breadcrumb Navigation -->
                <div class="flex-1 mx-8">
                    <div class="bg-white bg-opacity-10 rounded-lg px-4 py-2 backdrop-blur-sm">
                        <div class="flex items-center text-sm">
                            <span class="opacity-75">📍 Current Path:</span>
                            <span class="ml-2 font-medium truncate" title="<?= htmlspecialchars($currentDir) ?>">
                                <?= htmlspecialchars(substr($currentDir, 0, 60) . (strlen($currentDir) > 60 ? '...' : '')) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right: System Info and User -->
                <div class="flex items-center space-x-6">
                    <div class="text-right hidden md:block">
                        <div class="text-xs opacity-75">System</div>
                        <div class="text-sm font-semibold">
                            <?= IS_WINDOWS ? '🪟 Windows' : '🐧 Linux' ?> • PHP <?= PHP_VERSION ?>
                        </div>
                    </div>
                    <div class="h-10 w-px bg-white opacity-20"></div>
                    <div class="flex items-center space-x-3">
                        <div class="text-right">
                            <div class="text-xs opacity-75">Logado como</div>
                            <div class="text-sm font-semibold">Admin</div>
                        </div>
                        <a href="happymanagerwp.php" class="bg-green-600 bg-opacity-90 px-4 py-2 rounded-lg text-sm hover:bg-opacity-100 transition-all duration-200 font-medium shadow-lg mr-2">
                            ← Dashboard
                        </a>
                        <a href="?logout" class="bg-red-500 bg-opacity-90 px-4 py-2 rounded-lg text-sm hover:bg-opacity-100 transition-all duration-200 font-medium shadow-lg">
                            🚪 Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex">
        <!-- Left Sidebar -->
        <div class="w-80 bg-white shadow-lg sidebar border-r">
            <!-- Tabs -->
            <div class="flex border-b">
                <button onclick="switchSidebarTab('files')" id="filesTab" class="flex-1 py-2 px-4 text-sm font-medium border-b-2 <?= $currentTab === 'files' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    📁 Files
                </button>
                <button onclick="switchSidebarTab('database')" id="databaseTab" class="flex-1 py-2 px-4 text-sm font-medium border-b-2 <?= $currentTab === 'database' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700' ?>">
                    🗄️ Database
                </button>
            </div>

            <!-- Files Tree -->
            <div id="filesContent" class="p-4 <?= $currentTab !== 'files' ? 'hidden' : '' ?>">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-sm font-semibold text-gray-700">Directory Structure</h3>
                    <div class="flex space-x-1">
                        <button onclick="expandAllFolders()" class="text-xs bg-blue-100 text-blue-600 px-2 py-1 rounded hover:bg-blue-200" title="Expand All Folders">
                            ➕
                        </button>
                        <button onclick="collapseAllFolders()" class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded hover:bg-gray-200" title="Collapse All Folders">
                            ➖
                        </button>
                    </div>
                </div>
                <div class="tree">
                    <?php renderTree($directoryTree); ?>
                </div>
            </div>

            <!-- Database Tree -->
            <div id="databaseContent" class="p-4 <?= $currentTab !== 'database' ? 'hidden' : '' ?>">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">MySQL Databases</h3>
                <?php if (empty($databases)): ?>
                    <p class="text-gray-500 text-sm">❌ Database connection failed</p>
                <?php else: ?>
                    <div class="space-y-2">
                        <?php foreach ($databases as $db): ?>
                            <div class="tree-item p-2 rounded text-sm" onclick="toggleDatabase('<?= htmlspecialchars($db) ?>')">
                                <span class="toggle-icon">▶</span>
                                <span class="ml-1">🗄️ <?= htmlspecialchars($db) ?></span>
                                <div class="tree-children" id="db-<?= htmlspecialchars($db) ?>">
                                    <!-- Tables will be loaded here -->
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 main-content">
            <div class="p-6">
                <!-- Messages -->
                <?php if (isset($message)): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Main Content Tabs -->
                <div class="bg-white rounded-lg shadow mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <a href="?tab=files&dir=<?= urlencode($currentDir) ?>" class="py-2 px-1 border-b-2 font-medium text-sm <?= $currentTab === 'files' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                                📁 File Manager
                            </a>
                            <a href="?tab=database&dir=<?= urlencode($currentDir) ?>" class="py-2 px-1 border-b-2 font-medium text-sm <?= $currentTab === 'database' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' ?>">
                                🗄️ Database Manager
                            </a>
                        </nav>
                    </div>

                    <div class="p-6">
                        <?php if ($currentTab === 'files'): ?>
                            <!-- File Manager Content -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-2">
                                    <span class="text-gray-600">📍</span>
                                    <span class="font-medium">Current Path:</span>
                                    <span class="text-blue-600"><?= $currentDir ?: '/' ?></span>
                                </div>
                                
                                <?php if ($currentDir): ?>
                                    <a href="?tab=files&dir=<?= urlencode(dirname($currentDir)) ?>" class="bg-gray-500 text-white px-3 py-1 rounded text-sm hover:bg-gray-600">
                                        ⬆️ Up
                                    </a>
                                <?php endif; ?>
                            </div>

                            <!-- File Operations Cards -->
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                                <!-- Upload File -->
                                <form method="POST" enctype="multipart/form-data" class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200 shadow-sm hover:shadow-md transition-shadow">
                                    <input type="hidden" name="action" value="upload">
                                    <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir) ?>">
                                    <input type="hidden" name="tab" value="files">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <label class="block text-sm font-semibold mb-2 text-blue-700">📤 Upload File</label>
                                    <input type="file" name="file" required class="w-full text-xs mb-2 file:mr-2 file:py-1 file:px-3 file:rounded file:border-0 file:text-sm file:bg-blue-500 file:text-white hover:file:bg-blue-600 file:cursor-pointer">
                                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-3 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors shadow-sm">Upload</button>
                                </form>

                                <!-- Create Folder -->
                                <form method="POST" class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200 shadow-sm hover:shadow-md transition-shadow">
                                    <input type="hidden" name="action" value="create_folder">
                                    <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir) ?>">
                                    <input type="hidden" name="tab" value="files">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <label class="block text-sm font-semibold mb-2 text-green-700">📁 Create Folder</label>
                                    <input type="text" name="folder_name" required placeholder="Folder name" class="w-full px-3 py-2 border border-green-300 rounded-lg text-sm mb-2 focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    <button type="submit" class="w-full bg-green-600 text-white py-2 px-3 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors shadow-sm">Create</button>
                                </form>

                                <!-- Create File -->
                                <form method="POST" class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200 shadow-sm hover:shadow-md transition-shadow">
                                    <input type="hidden" name="action" value="create_file">
                                    <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir) ?>">
                                    <input type="hidden" name="tab" value="files">
                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                    <label class="block text-sm font-semibold mb-2 text-yellow-700">📝 Create File</label>
                                    <input type="text" name="file_name" required placeholder="file.txt" class="w-full px-3 py-2 border border-yellow-300 rounded-lg text-sm mb-2 focus:ring-2 focus:ring-yellow-500 focus:border-transparent">
                                    <button type="submit" class="w-full bg-yellow-600 text-white py-2 px-3 rounded-lg text-sm font-medium hover:bg-yellow-700 transition-colors shadow-sm">Create</button>
                                </form>

                                <!-- Actions -->
                                <div class="bg-gradient-to-br from-gray-50 to-gray-100 p-4 rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow">
                                    <label class="block text-sm font-semibold mb-2 text-gray-700">🔄 Quick Actions</label>
                                    <a href="?tab=files" class="block w-full bg-gray-600 text-white py-2 px-3 rounded-lg text-sm text-center font-medium hover:bg-gray-700 transition-colors shadow-sm mb-2">Refresh View</a>
                                    <?php if ($editFile): ?>
                                        <button onclick="toggleEditor()" class="w-full bg-purple-600 text-white py-2 px-3 rounded-lg text-sm font-medium hover:bg-purple-700 transition-colors shadow-sm">Hide Editor</button>
                                    <?php else: ?>
                                        <button onclick="showEditorInfo()" class="w-full bg-purple-500 text-white py-1 px-2 rounded text-sm hover:bg-purple-600">Editor Info</button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- File List -->
                            <div class="bg-white rounded-lg shadow overflow-hidden">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Modified</th>
                                            <?php if (IS_LINUX): ?>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permissions</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Owner</th>
                                            <?php endif; ?>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($items as $item): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3">
                                                    <div class="flex items-center">
                                                        <span class="mr-2"><?= $item['icon'] ?></span>
                                                        <?php if ($item['is_dir']): ?>
                                                            <a href="?tab=files&dir=<?= urlencode($item['path']) ?>" class="text-blue-600 hover:underline">
                                                                <?= htmlspecialchars($item['name']) ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span><?= htmlspecialchars($item['name']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600">
                                                    <?= $item['is_dir'] ? '-' : formatBytes($item['size']) ?>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600">
                                                    <?= date('Y-m-d H:i:s', $item['modified']) ?>
                                                </td>
                                                <?php if (IS_LINUX): ?>
                                                <td class="px-4 py-3 text-sm text-gray-600">
                                                    <span class="font-mono text-xs"><?= $item['octal'] ?></span>
                                                    <div class="text-xs text-gray-500">
                                                        <?= $item['permissions']['readable'] ? 'r' : '-' ?>
                                                        <?= $item['permissions']['writable'] ? 'w' : '-' ?>
                                                        <?= $item['permissions']['executable'] ? 'x' : '-' ?>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 text-sm text-gray-600">
                                                    <div class="text-xs">
                                                        <div><?= htmlspecialchars($item['owner']) ?></div>
                                                        <div class="text-gray-400"><?= htmlspecialchars($item['group']) ?></div>
                                                    </div>
                                                </td>
                                                <?php endif; ?>
                                                <td class="px-4 py-3">
                                                    <div class="flex space-x-1">
                                                        <?php if (!$item['is_dir']): ?>
                                                            <a href="?download=<?= urlencode($item['path']) ?>" class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded text-xs font-medium transition-colors" title="Download File">
                                                                📥
                                                            </a>
                                                            <?php if ($item['editable']): ?>
                                                                <a href="?edit=<?= urlencode($item['path']) ?>&dir=<?= urlencode($currentDir) ?>&tab=files" class="inline-flex items-center px-2 py-1 bg-green-50 text-green-600 hover:bg-green-100 rounded text-xs font-medium transition-colors" title="Edit File">
                                                                    ✏️
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        
                                                        <button onclick="renameItem('<?= htmlspecialchars($item['path']) ?>', '<?= htmlspecialchars($item['name']) ?>')" class="inline-flex items-center px-2 py-1 bg-yellow-50 text-yellow-600 hover:bg-yellow-100 rounded text-xs font-medium transition-colors" title="Rename">
                                                            ✏️
                                                        </button>
                                                        <button onclick="deleteItem('<?= htmlspecialchars($item['path']) ?>')" class="inline-flex items-center px-2 py-1 bg-red-50 text-red-600 hover:bg-red-100 rounded text-xs font-medium transition-colors" title="Delete">
                                                            🗑️
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($items)): ?>
                                            <tr>
                                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                                    📂 Empty directory
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php elseif ($currentTab === 'database'): ?>
                            <!-- Database Manager Content -->
                            <div class="space-y-6">
                                <!-- System & Database Status Info -->
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="bg-indigo-50 p-4 rounded-lg">
                                        <h3 class="text-sm font-semibold text-indigo-800 mb-2">💻 System Info</h3>
                                        <p class="text-indigo-600 text-sm"><?= IS_WINDOWS ? '🪟 Windows' : '🐧 Linux' ?></p>
                                        <p class="text-gray-600 text-xs">PHP <?= PHP_VERSION ?></p>
                                        <p class="text-gray-600 text-xs"><?= php_uname('s') ?> <?= php_uname('r') ?></p>
                                    </div>
                                    
                                    <div class="bg-blue-50 p-4 rounded-lg">
                                        <h3 class="text-sm font-semibold text-blue-800 mb-2">📊 Connection Status</h3>
                                        <?php if (!empty($databases)): ?>
                                            <p class="text-green-600 text-sm">✅ Connected to MySQL</p>
                                            <p class="text-gray-600 text-xs">Host: <?= DB_HOST ?></p>
                                        <?php else: ?>
                                            <p class="text-red-600 text-sm">❌ Connection Failed</p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="bg-green-50 p-4 rounded-lg">
                                        <h3 class="text-sm font-semibold text-green-800 mb-2">🗄️ Databases</h3>
                                        <p class="text-green-600 text-lg font-bold"><?= count($databases) ?></p>
                                        <p class="text-gray-600 text-xs">Available databases</p>
                                    </div>
                                    
                                    <div class="bg-purple-50 p-4 rounded-lg">
                                        <h3 class="text-sm font-semibold text-purple-800 mb-2">⚡ Quick Actions</h3>
                                        <div class="space-y-1">
                                            <button onclick="showSystemInfo()" class="text-purple-600 text-xs hover:underline block">Detailed Info</button>
                                            <button onclick="clearResults()" class="text-purple-600 text-xs hover:underline block">Clear Results</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Query Builder & SQL Editor -->
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- SQL Query Form -->
                                    <div class="bg-white p-4 rounded-lg shadow">
                                        <h3 class="text-lg font-medium mb-4 flex items-center">
                                            <span class="mr-2">🔍</span> SQL Query Editor
                                        </h3>
                                        <form method="POST" class="space-y-4">
                                            <input type="hidden" name="action" value="execute_sql">
                                            <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir) ?>">
                                            <input type="hidden" name="tab" value="database">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            
                                            <div>
                                                <label class="block text-sm font-medium mb-2">Database</label>
                                                <select name="database" id="databaseSelect" onchange="loadDatabaseTables()" required class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                                                    <option value="">Select Database</option>
                                                    <?php foreach ($databases as $db): ?>
                                                        <option value="<?= htmlspecialchars($db) ?>" <?= (isset($_SESSION['sql_database']) && $_SESSION['sql_database'] === $db) ? 'selected' : '' ?>><?= htmlspecialchars($db) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="grid grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium mb-2">Tables</label>
                                                    <select id="tableSelect" onchange="generateTableQuery()" class="w-full px-3 py-2 border border-gray-300 rounded">
                                                        <option value="">Select Table</option>
                                                    </select>
                                                </div>
                                                
                                                <div>
                                                    <label class="block text-sm font-medium mb-2">Quick Queries</label>
                                                    <select onchange="setQuickQuery(this.value)" class="w-full px-3 py-2 border border-gray-300 rounded">
                                                        <option value="">Select Query Template</option>
                                                        <optgroup label="Database Queries">
                                                            <option value="SHOW DATABASES;">Show Databases</option>
                                                            <option value="SHOW TABLES;">Show Tables</option>
                                                            <option value="SHOW PROCESSLIST;">Show Processes</option>
                                                            <option value="SHOW STATUS;">Show Status</option>
                                                        </optgroup>
                                                        <optgroup label="Table Queries">
                                                            <option value="SELECT * FROM table_name LIMIT 10;">Select Data</option>
                                                            <option value="DESCRIBE table_name;">Table Structure</option>
                                                            <option value="SHOW CREATE TABLE table_name;">Show Create Table</option>
                                                            <option value="SELECT COUNT(*) FROM table_name;">Count Rows</option>
                                                        </optgroup>
                                                        <optgroup label="System Queries">
                                                            <option value="SELECT VERSION();">MySQL Version</option>
                                                            <option value="SELECT NOW();">Current Time</option>
                                                            <option value="SHOW VARIABLES LIKE 'max_connections';">Max Connections</option>
                                                        </optgroup>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div>
                                                <div class="flex justify-between items-center mb-2">
                                                    <label class="block text-sm font-medium">SQL Query</label>
                                                    <div class="flex space-x-2">
                                                        <button type="button" onclick="formatSQLQuery()" class="text-xs bg-gray-100 px-2 py-1 rounded hover:bg-gray-200">Format</button>
                                                        <button type="button" onclick="clearSQLQuery()" class="text-xs bg-gray-100 px-2 py-1 rounded hover:bg-gray-200">Clear</button>
                                                    </div>
                                                </div>
                                                <textarea name="query" id="sqlQuery" rows="8" placeholder="Enter your SQL query here..." class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500 font-mono text-sm"><?= isset($_SESSION['sql_query']) ? htmlspecialchars($_SESSION['sql_query']) : '' ?></textarea>
                                            </div>
                                            
                                            <div class="flex space-x-2">
                                                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 flex items-center">
                                                    <span class="mr-1">▶️</span> Execute Query
                                                </button>
                                                <button type="button" onclick="explainQuery()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                                    🔍 Explain
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Table Structure & Info -->
                                    <div class="bg-white p-4 rounded-lg shadow">
                                        <h3 class="text-lg font-medium mb-4 flex items-center">
                                            <span class="mr-2">📋</span> Table Information
                                        </h3>
                                        
                                        <?php if (isset($_SESSION['table_structure']) && isset($_SESSION['current_table'])): ?>
                                            <div class="mb-4">
                                                <h4 class="font-medium text-gray-800 mb-2">Table: <?= htmlspecialchars($_SESSION['current_table']) ?></h4>
                                                <div class="overflow-x-auto">
                                                    <table class="w-full text-sm">
                                                        <thead class="bg-gray-100">
                                                            <tr>
                                                                <th class="px-2 py-1 text-left">Column</th>
                                                                <th class="px-2 py-1 text-left">Type</th>
                                                                <th class="px-2 py-1 text-left">Null</th>
                                                                <th class="px-2 py-1 text-left">Key</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($_SESSION['table_structure'] as $column): ?>
                                                                <tr class="border-b">
                                                                    <td class="px-2 py-1 font-mono"><?= htmlspecialchars($column['Field']) ?></td>
                                                                    <td class="px-2 py-1"><?= htmlspecialchars($column['Type']) ?></td>
                                                                    <td class="px-2 py-1"><?= $column['Null'] === 'YES' ? '✅' : '❌' ?></td>
                                                                    <td class="px-2 py-1"><?= htmlspecialchars($column['Key']) ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                            <?php unset($_SESSION['table_structure'], $_SESSION['current_table']); ?>
                                        <?php else: ?>
                                            <div class="text-center py-8 text-gray-500">
                                                <p class="mb-2">📊 No table selected</p>
                                                <p class="text-sm">Select a table to view its structure</p>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Quick Table Actions -->
                                        <div class="border-t pt-4 mt-4">
                                            <h5 class="font-medium text-gray-700 mb-2">Table Actions</h5>
                                            <div class="grid grid-cols-2 gap-2">
                                                <button onclick="browseTable()" class="text-xs bg-blue-100 text-blue-700 px-3 py-2 rounded hover:bg-blue-200">Browse Data</button>
                                                <button onclick="getTableStructure()" class="text-xs bg-green-100 text-green-700 px-3 py-2 rounded hover:bg-green-200">Structure</button>
                                                <button onclick="exportTable()" class="text-xs bg-purple-100 text-purple-700 px-3 py-2 rounded hover:bg-purple-200">Export</button>
                                                <button onclick="optimizeTable()" class="text-xs bg-orange-100 text-orange-700 px-3 py-2 rounded hover:bg-orange-200">Optimize</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- SQL Results -->
                                <?php if (isset($_SESSION['sql_result']) && !empty($_SESSION['sql_result'])): ?>
                                    <div class="bg-white rounded-lg shadow overflow-hidden">
                                        <div class="bg-gray-50 px-4 py-3 border-b flex justify-between items-center">
                                            <h3 class="text-lg font-medium flex items-center">
                                                <span class="mr-2">📊</span> Query Results
                                                <span class="ml-2 text-sm text-gray-500">(<?= count($_SESSION['sql_result']) ?> rows)</span>
                                            </h3>
                                            <div class="flex space-x-2">
                                                <button onclick="exportResults('csv')" class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded hover:bg-green-200">Export CSV</button>
                                                <button onclick="exportResults('json')" class="text-xs bg-blue-100 text-blue-700 px-3 py-1 rounded hover:bg-blue-200">Export JSON</button>
                                                <button onclick="clearResults()" class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded hover:bg-red-200">Clear</button>
                                            </div>
                                        </div>
                                        <div class="overflow-x-auto max-h-96">
                                            <table class="w-full">
                                                <thead class="bg-gray-50 sticky top-0">
                                                    <tr>
                                                        <?php if (!empty($_SESSION['sql_result'])): ?>
                                                            <?php foreach (array_keys($_SESSION['sql_result'][0]) as $column): ?>
                                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase border-b"><?= htmlspecialchars($column) ?></th>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200">
                                                    <?php foreach ($_SESSION['sql_result'] as $index => $row): ?>
                                                        <tr class="hover:bg-gray-50 <?= $index % 2 === 0 ? 'bg-white' : 'bg-gray-25' ?>">
                                                            <?php foreach ($row as $value): ?>
                                                                <td class="px-4 py-3 text-sm text-gray-900 max-w-xs truncate" title="<?= htmlspecialchars($value ?? 'NULL') ?>">
                                                                    <?php if ($value === null): ?>
                                                                        <span class="text-gray-400 italic">NULL</span>
                                                                    <?php elseif (is_numeric($value)): ?>
                                                                        <span class="text-blue-600"><?= htmlspecialchars($value) ?></span>
                                                                    <?php elseif (strlen($value) > 50): ?>
                                                                        <span title="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars(substr($value, 0, 47)) ?>...</span>
                                                                    <?php else: ?>
                                                                        <?= htmlspecialchars($value) ?>
                                                                    <?php endif; ?>
                                                                </td>
                                                            <?php endforeach; ?>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Recent Queries History -->
                                <div class="bg-white rounded-lg shadow p-4">
                                    <h3 class="text-lg font-medium mb-4 flex items-center">
                                        <span class="mr-2">📝</span> Query History
                                    </h3>
                                    <div id="queryHistory" class="space-y-2 max-h-40 overflow-y-auto">
                                        <!-- Query history will be populated by JavaScript -->
                                        <p class="text-gray-500 text-sm">No queries executed yet</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- File editor now opens in modal - see modal section at bottom -->
            </div>
        </div>
    </div>

    <!-- Editor Modal -->
    <?php if ($editFile): ?>
    <div id="editorModal" class="modal show">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h3 class="text-xl font-bold">✏️ Editing File</h3>
                    <p class="text-sm opacity-90 mt-1">📄 <?= htmlspecialchars(basename($editFile)) ?> • 📁 <?= htmlspecialchars(dirname($editFile)) ?></p>
                </div>
                <span class="close-btn" onclick="closeEditorModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="editorForm" style="height: 100%; display: flex; flex-direction: column;">
                    <input type="hidden" name="action" value="save_file">
                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($_GET['edit']) ?>">
                    <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir) ?>">
                    <input type="hidden" name="tab" value="files">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    
                    <div id="modalEditor" style="flex: 1; min-height: 0;"></div>
                    <textarea name="content" id="content" style="display: none;"><?= htmlspecialchars($editContent) ?></textarea>
                </form>
            </div>
            <div class="modal-footer">
                <div class="flex space-x-2">
                    <button type="button" onclick="saveEditorFile()" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 font-medium shadow">
                        💾 Save File (Ctrl+S)
                    </button>
                    <button type="button" onclick="formatCode()" class="bg-blue-500 text-white px-6 py-2 rounded-lg hover:bg-blue-600 font-medium shadow">
                        🎨 Format Code
                    </button>
                </div>
                <button type="button" onclick="closeEditorModal()" class="bg-gray-500 text-white px-6 py-2 rounded-lg hover:bg-gray-600 font-medium shadow">
                    ✖️ Close
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hidden forms for actions -->
    <form method="POST" id="deleteForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="path" id="deletePath">
        <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir) ?>">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($currentTab) ?>">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    </form>

    <form method="POST" id="renameForm" style="display: none;">
        <input type="hidden" name="action" value="rename">
        <input type="hidden" name="old_path" id="renameOldPath">
        <input type="hidden" name="new_name" id="renameNewName">
        <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir) ?>">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($currentTab) ?>">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    </form>

    <script>
        // Sidebar navigation
        function switchSidebarTab(tab) {
            document.getElementById('filesContent').classList.toggle('hidden', tab !== 'files');
            document.getElementById('databaseContent').classList.toggle('hidden', tab !== 'database');
            
            document.getElementById('filesTab').className = tab === 'files' 
                ? 'flex-1 py-2 px-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600'
                : 'flex-1 py-2 px-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700';
                
            document.getElementById('databaseTab').className = tab === 'database' 
                ? 'flex-1 py-2 px-4 text-sm font-medium border-b-2 border-blue-500 text-blue-600'
                : 'flex-1 py-2 px-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700';
        }

        // Tree navigation with collapsible folders
        function toggleTreeFolder(folderId) {
            const folder = document.getElementById(folderId);
            const toggleIcon = folder.previousElementSibling.querySelector('.tree-toggle');
            
            if (folder.style.display === 'none') {
                folder.style.display = 'block';
                toggleIcon.classList.remove('collapsed');
                toggleIcon.classList.add('expanded');
                toggleIcon.textContent = '▼';
                
                // Save state to localStorage
                saveTreeState(folderId, true);
            } else {
                folder.style.display = 'none';
                toggleIcon.classList.remove('expanded');
                toggleIcon.classList.add('collapsed');
                toggleIcon.textContent = '▶';
                
                // Save state to localStorage
                saveTreeState(folderId, false);
            }
        }

        function navigateToFolder(path) {
            window.location.href = '?tab=files&dir=' + encodeURIComponent(path);
        }

        function saveTreeState(folderId, isExpanded) {
            let treeStates = JSON.parse(localStorage.getItem('treeStates') || '{}');
            treeStates[folderId] = isExpanded;
            localStorage.setItem('treeStates', JSON.stringify(treeStates));
        }

        function restoreTreeStates() {
            let treeStates = JSON.parse(localStorage.getItem('treeStates') || '{}');
            for (let folderId in treeStates) {
                if (treeStates[folderId]) {
                    const folder = document.getElementById(folderId);
                    if (folder) {
                        const toggleIcon = folder.previousElementSibling.querySelector('.tree-toggle');
                        folder.style.display = 'block';
                        if (toggleIcon) {
                            toggleIcon.classList.remove('collapsed');
                            toggleIcon.classList.add('expanded');
                            toggleIcon.textContent = '▼';
                        }
                    }
                }
            }
        }

        function expandAllFolders() {
            document.querySelectorAll('.tree-children').forEach(folder => {
                folder.style.display = 'block';
                const toggleIcon = folder.previousElementSibling.querySelector('.tree-toggle');
                if (toggleIcon) {
                    toggleIcon.classList.remove('collapsed');
                    toggleIcon.classList.add('expanded');
                    toggleIcon.textContent = '▼';
                }
            });
        }

        function collapseAllFolders() {
            document.querySelectorAll('.tree-children').forEach(folder => {
                folder.style.display = 'none';
                const toggleIcon = folder.previousElementSibling.querySelector('.tree-toggle');
                if (toggleIcon) {
                    toggleIcon.classList.remove('expanded');
                    toggleIcon.classList.add('collapsed');
                    toggleIcon.textContent = '▶';
                }
            });
            // Clear saved states
            localStorage.removeItem('treeStates');
        }

        // Initialize tree states on page load
        document.addEventListener('DOMContentLoaded', function() {
            restoreTreeStates();
        });

        function toggleTreeItem(path) {
            window.location.href = '?tab=files&dir=' + encodeURIComponent(path);
        }

        function toggleDatabase(dbName) {
            const element = document.getElementById('db-' + dbName);
            const icon = element.previousElementSibling.querySelector('.toggle-icon');
            
            if (element.classList.contains('expanded')) {
                element.classList.remove('expanded');
                icon.textContent = '▶';
                element.innerHTML = '';
            } else {
                element.classList.add('expanded');
                icon.textContent = '▼';
                
                // Load tables for this database
                fetch('?ajax=get_tables&database=' + encodeURIComponent(dbName))
                    .then(response => response.json())
                    .then(result => {
                        if (result.error) {
                            element.innerHTML = `<div class="p-1 ml-4 text-sm text-red-500">⚠️ ${result.error}</div>`;
                        } else if (result.tables && result.tables.length > 0) {
                            let html = '';
                            result.tables.forEach(table => {
                                html += `<div class="p-1 ml-4 text-sm text-gray-600 hover:bg-gray-100 rounded cursor-pointer" onclick="selectTable('${dbName}', '${table}')">📋 ${table}</div>`;
                            });
                            element.innerHTML = html;
                        } else {
                            element.innerHTML = '<div class="p-1 ml-4 text-sm text-gray-400">📭 No tables found</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        element.innerHTML = '<div class="p-1 ml-4 text-sm text-red-500">⚠️ Network error. Check console for details.</div>';
                    });
            }
        }

        function selectTable(database, table) {
            document.querySelector('select[name="database"]').value = database;
            document.getElementById('sqlQuery').value = `SELECT * FROM \`${table}\` LIMIT 10;`;
            
            // Also switch to database tab if not already there
            if (window.location.search.indexOf('tab=database') === -1) {
                window.location.href = '?tab=database';
            }
        }

        // Enhanced SQL Query helpers
        function setQuickQuery(query) {
            if (query) {
                const sqlTextarea = document.getElementById('sqlQuery');
                const selectedTable = document.getElementById('tableSelect').value;
                
                // Replace table_name placeholder with selected table
                if (selectedTable && query.includes('table_name')) {
                    query = query.replace(/table_name/g, '`' + selectedTable + '`');
                }
                
                sqlTextarea.value = query;
                addToQueryHistory(query);
            }
        }

        // Database and table management
        function loadDatabaseTables() {
            const database = document.getElementById('databaseSelect').value;
            const tableSelect = document.getElementById('tableSelect');
            
            if (!database) {
                tableSelect.innerHTML = '<option value="">Select Table</option>';
                return;
            }
            
            fetch('?ajax=get_tables&database=' + encodeURIComponent(database))
                .then(response => response.json())
                .then(result => {
                    if (result.error) {
                        tableSelect.innerHTML = `<option value="">⚠️ ${result.error}</option>`;
                    } else if (result.tables && result.tables.length > 0) {
                        let html = '<option value="">Select Table</option>';
                        result.tables.forEach(table => {
                            html += `<option value="${table}">${table}</option>`;
                        });
                        tableSelect.innerHTML = html;
                    } else {
                        tableSelect.innerHTML = '<option value="">No tables found</option>';
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    tableSelect.innerHTML = '<option value="">Network error - check console</option>';
                });
        }

        function generateTableQuery() {
            const table = document.getElementById('tableSelect').value;
            if (table) {
                document.getElementById('sqlQuery').value = `SELECT * FROM \`${table}\` LIMIT 10;`;
            }
        }

        // Query formatting and management
        function formatSQLQuery() {
            const textarea = document.getElementById('sqlQuery');
            let query = textarea.value.trim();
            
            if (!query) return;
            
            // Basic SQL formatting
            query = query.replace(/\s+/g, ' '); // Remove extra spaces
            query = query.replace(/\s*,\s*/g, ', '); // Fix comma spacing
            query = query.replace(/\s*(=|<|>|<=|>=|!=)\s*/g, ' $1 '); // Fix operator spacing
            query = query.replace(/\b(SELECT|FROM|WHERE|ORDER BY|GROUP BY|HAVING|LIMIT|INSERT|UPDATE|DELETE|CREATE|ALTER|DROP)\b/gi, '\n$1');
            query = query.replace(/\bAND\b/gi, '\n  AND');
            query = query.replace(/\bOR\b/gi, '\n  OR');
            
            textarea.value = query.trim();
        }

        function clearSQLQuery() {
            document.getElementById('sqlQuery').value = '';
        }

        function explainQuery() {
            const query = document.getElementById('sqlQuery').value.trim();
            const database = document.getElementById('databaseSelect').value;
            
            if (!query || !database) {
                alert('Please select a database and enter a query');
                return;
            }
            
            if (query.toLowerCase().startsWith('select')) {
                document.getElementById('sqlQuery').value = `EXPLAIN ${query}`;
            } else {
                alert('EXPLAIN only works with SELECT queries');
            }
        }

        // Table actions
        function browseTable() {
            const table = document.getElementById('tableSelect').value;
            if (table) {
                document.getElementById('sqlQuery').value = `SELECT * FROM \`${table}\` LIMIT 50;`;
                addToQueryHistory(`Browse table: ${table}`);
            } else {
                alert('Please select a table first');
            }
        }

        function getTableStructure() {
            const table = document.getElementById('tableSelect').value;
            const database = document.getElementById('databaseSelect').value;
            
            if (!table || !database) {
                alert('Please select a database and table first');
                return;
            }
            
            // Create and submit form for table structure
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="get_table_structure">
                <input type="hidden" name="database" value="${database}">
                <input type="hidden" name="table" value="${table}">
                <input type="hidden" name="current_dir" value="<?= htmlspecialchars($currentDir) ?>">
                <input type="hidden" name="tab" value="database">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function exportTable() {
            const table = document.getElementById('tableSelect').value;
            if (table) {
                document.getElementById('sqlQuery').value = `SELECT * FROM \`${table}\`;`;
                alert('Query set to export entire table. Execute to see results, then use Export buttons.');
            } else {
                alert('Please select a table first');
            }
        }

        function optimizeTable() {
            const table = document.getElementById('tableSelect').value;
            if (table) {
                document.getElementById('sqlQuery').value = `OPTIMIZE TABLE \`${table}\`;`;
                addToQueryHistory(`Optimize table: ${table}`);
            } else {
                alert('Please select a table first');
            }
        }

        // System and utility functions
        function showSystemInfo() {
            const isLinux = <?= IS_LINUX ? 'true' : 'false' ?>;
            const phpVersion = '<?= PHP_VERSION ?>';
            const systemName = '<?= php_uname('s') ?>';
            const systemRelease = '<?= php_uname('r') ?>';
            const systemVersion = '<?= php_uname('v') ?>';
            const machineName = '<?= php_uname('n') ?>';
            const architecture = '<?= php_uname('m') ?>';
            
            const systemInfo = `
-- ================================================
-- 🖥️  SecureFileHub System Information
-- ================================================

-- 💻 Platform Details
-- Operating System: ${systemName} ${systemRelease}
-- Architecture: ${architecture}
-- Machine Name: ${machineName}
-- PHP Version: ${phpVersion}
-- Platform Type: ${isLinux ? 'Linux/Unix' : 'Windows'}

-- 🗄️ MySQL System Information
SELECT 
    '=== MySQL Server Information ===' as info_section,
    VERSION() as mysql_version,
    @@version_comment as mysql_distribution,
    @@datadir as data_directory,
    @@basedir as base_directory,
    @@max_connections as max_connections,
    @@max_allowed_packet as max_packet_size,
    @@innodb_buffer_pool_size as innodb_buffer_pool,
    @@query_cache_size as query_cache_size,
    @@sql_mode as sql_mode,
    @@default_storage_engine as default_engine;

-- 📊 Server Status
-- SELECT 
--     '=== Server Status ===' as status_section,
--     VARIABLE_NAME as setting_name,
--     VARIABLE_VALUE as setting_value
-- FROM INFORMATION_SCHEMA.GLOBAL_STATUS 
-- WHERE VARIABLE_NAME IN (
--     'Uptime', 'Connections', 'Queries', 'Threads_connected',
--     'Innodb_buffer_pool_pages_total', 'Innodb_buffer_pool_pages_free'
-- );

-- 🔍 Database Information
-- SELECT 
--     '=== Database Summary ===' as db_section,
--     SCHEMA_NAME as database_name,
--     DEFAULT_CHARACTER_SET_NAME as charset,
--     DEFAULT_COLLATION_NAME as collation
-- FROM INFORMATION_SCHEMA.SCHEMATA
-- WHERE SCHEMA_NAME NOT IN ('information_schema', 'performance_schema', 'mysql', 'sys');
            `.trim();
            
            document.getElementById('sqlQuery').value = systemInfo;
        }

        function clearResults() {
            fetch('?ajax=clear_results', {method: 'POST'})
                .then(() => location.reload());
        }

        // Export functions
        function exportResults(format) {
            const results = <?= isset($_SESSION['sql_result']) ? json_encode($_SESSION['sql_result']) : '[]' ?>;
            
            if (!results || results.length === 0) {
                alert('No results to export');
                return;
            }
            
            if (format === 'csv') {
                exportToCSV(results);
            } else if (format === 'json') {
                exportToJSON(results);
            }
        }

        function exportToCSV(data) {
            if (data.length === 0) return;
            
            const headers = Object.keys(data[0]);
            let csv = headers.join(',') + '\n';
            
            data.forEach(row => {
                const values = headers.map(header => {
                    const value = row[header];
                    // Escape quotes and wrap in quotes if contains comma or quote
                    const escaped = String(value || '').replace(/"/g, '""');
                    return escaped.includes(',') || escaped.includes('"') ? `"${escaped}"` : escaped;
                });
                csv += values.join(',') + '\n';
            });
            
            downloadFile(csv, 'query_results.csv', 'text/csv');
        }

        function exportToJSON(data) {
            const json = JSON.stringify(data, null, 2);
            downloadFile(json, 'query_results.json', 'application/json');
        }

        function downloadFile(content, filename, contentType) {
            const blob = new Blob([content], { type: contentType });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }

        // Query history management
        let queryHistory = JSON.parse(localStorage.getItem('sql_query_history') || '[]');

        function addToQueryHistory(query) {
            if (!query || query.trim() === '') return;
            
            // Remove if already exists and add to beginning
            queryHistory = queryHistory.filter(q => q.query !== query);
            queryHistory.unshift({
                query: query,
                timestamp: new Date().toLocaleString(),
                database: document.getElementById('databaseSelect').value
            });
            
            // Keep only last 10 queries
            queryHistory = queryHistory.slice(0, 10);
            
            localStorage.setItem('sql_query_history', JSON.stringify(queryHistory));
            updateQueryHistoryDisplay();
        }

        function updateQueryHistoryDisplay() {
            const container = document.getElementById('queryHistory');
            if (!container) return;
            
            if (queryHistory.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-sm">No queries executed yet</p>';
                return;
            }
            
            const html = queryHistory.map((item, index) => `
                <div class="bg-gray-50 p-3 rounded border cursor-pointer hover:bg-gray-100" onclick="loadFromHistory(${index})">
                    <div class="flex justify-between items-start">
                        <code class="text-sm text-blue-600 flex-1 truncate">${item.query}</code>
                        <span class="text-xs text-gray-500 ml-2">${item.timestamp}</span>
                    </div>
                    ${item.database ? `<div class="text-xs text-gray-600 mt-1">Database: ${item.database}</div>` : ''}
                </div>
            `).join('');
            
            container.innerHTML = html;
        }

        function loadFromHistory(index) {
            const item = queryHistory[index];
            if (item) {
                document.getElementById('sqlQuery').value = item.query;
                if (item.database) {
                    document.getElementById('databaseSelect').value = item.database;
                    loadDatabaseTables();
                }
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateQueryHistoryDisplay();
            
            // Load tables for pre-selected database
            const selectedDb = document.getElementById('databaseSelect').value;
            if (selectedDb) {
                loadDatabaseTables();
            }
            
            // Add query to history when form is submitted
            const sqlForm = document.querySelector('form input[name="action"][value="execute_sql"]');
            if (sqlForm) {
                sqlForm.closest('form').addEventListener('submit', function() {
                    const query = document.getElementById('sqlQuery').value.trim();
                    if (query) {
                        addToQueryHistory(query);
                    }
                });
            }
        });

        // File operations
        function deleteItem(path) {
            if (confirm('Are you sure you want to delete this item?')) {
                document.getElementById('deletePath').value = path;
                document.getElementById('deleteForm').submit();
            }
        }

        function renameItem(path, currentName) {
            const newName = prompt('Enter new name:', currentName);
            if (newName && newName !== currentName) {
                document.getElementById('renameOldPath').value = path;
                document.getElementById('renameNewName').value = newName;
                document.getElementById('renameForm').submit();
            }
        }

        // Monaco Editor for Modal
        <?php if ($editFile): ?>
            require.config({ 
                paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.34.1/min/vs' }
            });

            let editor;
            require(['vs/editor/editor.main'], function() {
                const fileExtension = '<?= strtolower(pathinfo($editFile, PATHINFO_EXTENSION)) ?>';
                let language = 'plaintext';
                
                const languageMap = {
                    'php': 'php',
                    'js': 'javascript',
                    'html': 'html',
                    'css': 'css',
                    'json': 'json',
                    'xml': 'xml',
                    'py': 'python',
                    'java': 'java',
                    'c': 'c',
                    'cpp': 'cpp',
                    'h': 'c',
                    'sql': 'sql',
                    'md': 'markdown',
                    'txt': 'plaintext'
                };
                
                if (languageMap[fileExtension]) {
                    language = languageMap[fileExtension];
                }

                // Small delay to ensure modal is fully rendered
                setTimeout(function() {
                    const editorContainer = document.getElementById('modalEditor');
                    
                    if (!editorContainer) {
                        console.error('Editor container not found!');
                        return;
                    }

                    editor = monaco.editor.create(editorContainer, {
                        value: <?= json_encode($editContent) ?>,
                        language: language,
                        theme: 'vs-dark',
                        automaticLayout: true,
                        minimap: { enabled: true },
                        lineNumbers: 'on',
                        wordWrap: 'on',
                        fontSize: 14,
                        scrollBeyondLastLine: false,
                        renderWhitespace: 'selection',
                        folding: true,
                        lineDecorationsWidth: 10,
                        glyphMargin: true
                    });

                    // Make editor globally accessible
                    window.editor = editor;

                    // Force layout update after creation
                    setTimeout(function() {
                        if (editor) {
                            editor.layout();
                        }
                    }, 100);

                    // Update hidden textarea when content changes
                    editor.onDidChangeModelContent(function() {
                        document.getElementById('content').value = editor.getValue();
                    });

                    // Focus editor
                    editor.focus();
                }, 200);
            });

            function formatCode() {
                if (!window.editor) {
                    console.error('Editor not initialized');
                    alert('❌ Editor not ready. Please wait a moment and try again.');
                    return;
                }
                
                try {
                    // Get the format action
                    const formatAction = window.editor.getAction('editor.action.formatDocument');
                    
                    if (!formatAction) {
                        alert('⚠️ Format Document action not available for this file type.');
                        return;
                    }
                    
                    // Trigger formatting
                    formatAction.run().then(() => {
                        console.log('Code formatted successfully');
                        // Update the hidden textarea
                        document.getElementById('content').value = window.editor.getValue();
                    }).catch((err) => {
                        console.error('Format error:', err);
                        alert('❌ Format failed: ' + (err.message || 'Unknown error'));
                    });
                } catch (e) {
                    console.error('Format error:', e);
                    alert('❌ Format failed. Make sure the file type supports formatting.');
                }
            }

            function saveEditorFile() {
                if (window.editor) {
                    document.getElementById('content').value = window.editor.getValue();
                }
                document.getElementById('editorForm').submit();
            }

            function closeEditorModal() {
                if (confirm('Close editor? Any unsaved changes will be lost.')) {
                    window.location.href = '?tab=files&dir=<?= urlencode($currentDir) ?>';
                }
            }

            // Save with Ctrl+S
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    saveEditorFile();
                }
                // Close modal with ESC
                if (e.key === 'Escape') {
                    closeEditorModal();
                }
            });

            // Prevent accidental page close
            window.addEventListener('beforeunload', function (e) {
                if (editor && editor.getValue() !== <?= json_encode($editContent) ?>) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        <?php endif; ?>

        function toggleEditor() {
            const editorContainer = document.querySelector('.monaco-editor');
            const editorSection = editorContainer ? editorContainer.closest('.bg-white.rounded-lg.shadow.mt-6') : null;
            const toggleButton = document.querySelector('button[onclick="toggleEditor()"]');
            
            if (editorSection) {
                const isHidden = editorSection.style.display === 'none';
                editorSection.style.display = isHidden ? 'block' : 'none';
                
                if (toggleButton) {
                    toggleButton.textContent = isHidden ? 'Hide Editor' : 'Show Editor';
                    toggleButton.className = isHidden 
                        ? 'w-full bg-purple-500 text-white py-1 px-2 rounded text-sm hover:bg-purple-600'
                        : 'w-full bg-green-500 text-white py-1 px-2 rounded text-sm hover:bg-green-600';
                }
                
                // Trigger editor resize if showing
                if (isHidden && window.editor) {
                    setTimeout(() => {
                        window.editor.layout();
                    }, 100);
                }
            }
        }

        function showEditorInfo() {
            alert(`📝 Code Editor Information:

🎯 Purpose: 
• Edit text files directly in your browser
• Syntax highlighting for multiple languages
• Professional code editing features

🚀 How to use:
1. Click "✏️ Edit" next to any text file
2. The Monaco Editor will appear below
3. Use "Toggle Editor" to show/hide the editor
4. Save with Ctrl+S or the Save button

💡 Supported files:
• Code: PHP, JavaScript, HTML, CSS, Python, Java, C++
• Data: JSON, XML, SQL
• Text: TXT, MD, INI, LOG, CONF

✨ Features:
• Syntax highlighting
• Code formatting
• Auto-completion
• Error detection
• Dark theme interface`);
        }
    </script>
</body>
</html>

<?php
// Helper function to render directory tree
function renderTree($items, $level = 0) {
    foreach ($items as $item) {
        $indent = str_repeat('  ', $level);
        $isFolder = ($item['type'] ?? 'file') === 'folder';
        $hasChildren = !empty($item['children']);
        $itemId = 'tree-' . md5($item['path']);
        
        echo '<div class="tree-item-wrapper" data-path="' . htmlspecialchars($item['path']) . '">';
        
        if ($isFolder) {
            // Folder with toggle
            echo '<div class="tree-item p-1 text-sm hover:bg-gray-100 rounded flex items-center cursor-pointer" onclick="toggleTreeFolder(\'' . $itemId . '\')">';
            echo '<span class="tree-toggle collapsed mr-1">▶</span>';
            echo '<span class="mr-1">📁</span>';
            echo '<span>' . htmlspecialchars($item['name']) . '</span>';
            echo '</div>';
            
            if ($hasChildren) {
                echo '<div class="tree-children ml-4" id="' . $itemId . '" style="display: none;">';
                renderTree($item['children'], $level + 1);
                echo '</div>';
            }
        } else {
            // File - make it clickable to edit
            $icon = $item['icon'] ?? '📄';
            $editLink = "?edit=" . urlencode($item['path']) . "&dir=" . urlencode(dirname($item['path']));
            echo '<a href="' . htmlspecialchars($editLink) . '" class="tree-item p-1 text-sm hover:bg-blue-50 rounded flex items-center no-underline text-gray-700 ml-4">';
            echo '<span class="mr-1">' . $icon . '</span>';
            echo '<span>' . htmlspecialchars($item['name']) . '</span>';
            echo '</a>';
        }
        
        echo '</div>';
    }
}
?>