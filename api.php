<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
ob_start();

require_once 'scanner.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $basePath = __DIR__;
    // $basePath = __DIR__ . '/..'; // Uncomment for Laragon sibling folder mode
    
    $scanner = new WPScanner($basePath);
    $currentDirName = basename(__DIR__);

    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'scan';

        if ($action === 'orphan_backups') {
            $backupRoot = $basePath . DIRECTORY_SEPARATOR . 'happy-backup';
            $orphans = [];

            if (is_dir($backupRoot)) {
                $backupFolders = array_filter(glob($backupRoot . DIRECTORY_SEPARATOR . '*'), 'is_dir');
                foreach ($backupFolders as $bf) {
                    $folderName = basename($bf);
                    $siteDir = $basePath . DIRECTORY_SEPARATOR . $folderName;
                    // If the site directory doesn't exist, this backup is orphaned
                    if (!is_dir($siteDir)) {
                        $files = glob($bf . DIRECTORY_SEPARATOR . '*.zip');
                        $backups = [];
                        if ($files) {
                            foreach ($files as $file) {
                                $backups[] = [
                                    'name' => basename($file),
                                    'date' => date('Y-m-d H:i:s', filemtime($file)),
                                    'size' => filesize($file)
                                ];
                            }
                            usort($backups, function($a, $b) {
                                return strcmp($b['date'], $a['date']);
                            });
                        }
                        if (count($backups) > 0) {
                            $orphans[] = [
                                'folder' => $folderName,
                                'backups' => $backups
                            ];
                        }
                    }
                }
            }

            ob_end_clean();
            echo json_encode(['status' => 'success', 'data' => $orphans]);
        } else {
            $data = $scanner->scan([$currentDirName, '.git', '.vscode', 'node_modules']);
            ob_end_clean();
            echo json_encode(['status' => 'success', 'data' => $data]);
        }
    } 
    elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';

        if ($action === 'delete') {
            $type = $input['type'] ?? ''; // plugin or theme
            $sitePath = $input['sitePath'] ?? '';
            $slug = $input['slug'] ?? '';
            $active = $input['active'] ?? true;

            // Security: Only allow deleting inactive items
            if ($active) {
                throw new Exception('Cannot delete an active item.');
            }

            if ($scanner->deleteItem($type, $sitePath, $slug)) {
                ob_end_clean();
                echo json_encode(['status' => 'success', 'message' => 'Item deleted successfully.']);
            } else {
                throw new Exception('Failed to delete item. Check permissions.');
            }
        } elseif ($action === 'install') {
            $folder = $input['folder'] ?? '';
            $createDb = $input['createDb'] ?? false;

            if (empty($folder)) throw new Exception('Nome da pasta é obrigatório.');
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $folder)) throw new Exception('Nome da pasta inválido.');

            $targetDir = $basePath . DIRECTORY_SEPARATOR . $folder;
            if (file_exists($targetDir)) {
                throw new Exception('O diretório já existe.');
            }

            if (!mkdir($targetDir, 0755, true)) {
                throw new Exception('Falha ao criar o diretório.');
            }

            // Download WP via cURL or file_get_contents
            $wpUrl = 'https://br.wordpress.org/latest-pt_BR.zip';
            $zipFile = $targetDir . DIRECTORY_SEPARATOR . 'wp.zip';
            
            if (function_exists('curl_version')) {
                $ch = curl_init($wpUrl);
                $fp = fopen($zipFile, 'w+');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($fp);
                if ($httpCode !== 200) {
                    rmdir($targetDir);
                    throw new Exception('Falha ao baixar o WordPress (HTTP ' . $httpCode . ').');
                }
            } else {
                $wpContent = @file_get_contents($wpUrl);
                if ($wpContent === false) {
                    rmdir($targetDir);
                    throw new Exception('Falha ao baixar o WordPress via file_get_contents.');
                }
                file_put_contents($zipFile, $wpContent);
            }

            // Unzip
            $zip = new ZipArchive;
            if ($zip->open($zipFile) === TRUE) {
                $zip->extractTo($targetDir);
                $zip->close();
            } else {
                throw new Exception('Falha ao descompactar o WordPress.');
            }
            unlink($zipFile);

            // WP extracts into a "wordpress" subfolder. Move contents up.
            $wpFolder = $targetDir . DIRECTORY_SEPARATOR . 'wordpress';
            if (is_dir($wpFolder)) {
                $files = scandir($wpFolder);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        rename($wpFolder . DIRECTORY_SEPARATOR . $file, $targetDir . DIRECTORY_SEPARATOR . $file);
                    }
                }
                rmdir($wpFolder);
            }

            // --- Auto Install Elementor Plugin ---
            $elementorUrl = 'https://downloads.wordpress.org/plugin/elementor.zip';
            $elementorZip = $targetDir . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'elementor.zip';
            
            if (function_exists('curl_version')) {
                $ch = curl_init($elementorUrl);
                $fp = fopen($elementorZip, 'w+');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 120);
                curl_exec($ch);
                curl_close($ch);
                fclose($fp);
            } else {
                @file_put_contents($elementorZip, @file_get_contents($elementorUrl));
            }
            if (file_exists($elementorZip)) {
                $zip = new ZipArchive;
                if ($zip->open($elementorZip) === TRUE) {
                    $zip->extractTo($targetDir . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins');
                    $zip->close();
                }
                @unlink($elementorZip);
            }

            // --- Auto Install Hello Elementor Theme ---
            $helloUrl = 'https://downloads.wordpress.org/theme/hello-elementor.zip';
            $helloZip = $targetDir . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'themes' . DIRECTORY_SEPARATOR . 'hello-elementor.zip';
            
            if (function_exists('curl_version')) {
                $ch = curl_init($helloUrl);
                $fp = fopen($helloZip, 'w+');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_exec($ch);
                curl_close($ch);
                fclose($fp);
            } else {
                @file_put_contents($helloZip, @file_get_contents($helloUrl));
            }
            if (file_exists($helloZip)) {
                $zip = new ZipArchive;
                if ($zip->open($helloZip) === TRUE) {
                    $zip->extractTo($targetDir . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'themes');
                    $zip->close();
                }
                @unlink($helloZip);
            }

            // --- Auto Clean Defaults (Plugins and Themes) ---
            @unlink($targetDir . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'hello.php');
            $scanner->recursiveDelete($targetDir . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'akismet');
            
            $themesDir = $targetDir . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'themes';
            if (is_dir($themesDir)) {
                $themes = scandir($themesDir);
                foreach ($themes as $theme) {
                    if (strpos($theme, 'twenty') === 0) {
                        $scanner->recursiveDelete($themesDir . DIRECTORY_SEPARATOR . $theme);
                    }
                }
            }

            // --- Auto Clean Defaults (Posts and Pages) via MU-Plugin ---
            $muDir = $targetDir . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'mu-plugins';
            if (!is_dir($muDir)) {
                mkdir($muDir, 0755, true);
            }
            $cleanerCode = "<?php\n" .
                           "// Plugin temporário para limpar posts padrão e se autodestruir\n" .
                           "add_action('wp_install', function() {\n" .
                           "    wp_delete_post(1, true); // Olá mundo\n" .
                           "    wp_delete_post(2, true); // Página de Exemplo\n" .
                           "    wp_delete_post(3, true); // Política de Privacidade\n" .
                           "    @unlink(__FILE__);\n" .
                           "});\n";
            file_put_contents($muDir . DIRECTORY_SEPARATOR . 'happy-cleaner.php', $cleanerCode);

            $message = 'WordPress instalado, Elementor adicionado e padrões apagados.';

            // Create DB
            if ($createDb) {
                
                $dbUser = 'DB_USER';
                $dbPass = 'DB_PASSWORD';
                
                $dbHost = 'localhost';
                $dbName = 'wp_' . str_replace('-', '_', $folder);

                try {
                    $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}`");

                    // Create wp-config.php
                    $sampleConfigPath = $targetDir . DIRECTORY_SEPARATOR . 'wp-config-sample.php';
                    if (file_exists($sampleConfigPath)) {
                        $config = file_get_contents($sampleConfigPath);
                        $config = str_replace(
                            ["'database_name_here'", "'username_here'", "'password_here'", "'localhost'"],
                            ["'{$dbName}'", "'{$dbUser}'", "'{$dbPass}'", "'{$dbHost}'"],
                            $config
                        );
                        
                        file_put_contents($targetDir . DIRECTORY_SEPARATOR . 'wp-config.php', $config);
                        $message .= " Banco de dados '{$dbName}' criado e wp-config.php configurado.";
                    }
                } catch (Exception $e) {
                    $message .= " Atenção: falha ao criar banco de dados: " . $e->getMessage();
                }
            }

            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => $message]);
        } elseif ($action === 'delete_site') {
            $folder = $input['folder'] ?? '';
            
            if (empty($folder)) throw new Exception('Nome da pasta é obrigatório.');
            $folder = basename($folder); // Previne directory traversal
            
            $targetDir = $basePath . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($targetDir)) {
                throw new Exception('O diretório não existe.');
            }

            $message = 'Site removido com sucesso.';

            // Tentar remover banco de dados
            $configFile = $targetDir . DIRECTORY_SEPARATOR . 'wp-config.php';
            if (file_exists($configFile)) {
                $content = file_get_contents($configFile);
                $dbName = $dbUser = $dbPass = $dbHost = '';

                if (preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $matches)) $dbName = $matches[1];
                if (preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $matches)) $dbUser = $matches[1];
                if (preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $matches)) $dbPass = $matches[1];
                if (preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $matches)) $dbHost = $matches[1];

                if ($dbName && $dbUser && $dbPass && $dbHost) {
                    try {
                        // Tenta remover usando as credenciais do wp-config (ou seja, o dbUser pode ser script_admin ou ter permissão)
                        $pdo = new PDO("mysql:host={$dbHost};charset=utf8mb4", $dbUser, $dbPass, [
                            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                            PDO::ATTR_TIMEOUT => 2
                        ]);
                        $pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
                        $message .= " Banco de dados '{$dbName}' apagado.";
                    } catch (Exception $e) {
                        $message .= " Falha ao remover banco de dados (sem permissão).";
                    }
                }
            }

            // Exclui a pasta do WP
            if (!$scanner->recursiveDelete($targetDir)) {
                throw new Exception("Falha ao apagar arquivos da pasta /{$folder}.");
            }

            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => $message]);
        } elseif ($action === 'backup_site') {
            // Increase limits for potentially large backups
            set_time_limit(600);
            ini_set('memory_limit', '512M');

            $folder = $input['folder'] ?? '';
            if (empty($folder)) throw new Exception('Nome da pasta é obrigatório.');
            $folder = basename($folder); // Previne directory traversal

            $targetDir = $basePath . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($targetDir)) {
                throw new Exception('O diretório não existe.');
            }

            $configFile = $targetDir . DIRECTORY_SEPARATOR . 'wp-config.php';
            if (!file_exists($configFile)) {
                throw new Exception('wp-config.php não encontrado. O site não parece ser um WordPress válido.');
            }

            // Read DB credentials
            $content = file_get_contents($configFile);
            $dbName = $dbUser = $dbPass = $dbHost = $tablePrefix = '';

            if (preg_match("/define\(\s*['\"]DB_NAME['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $matches)) $dbName = $matches[1];
            if (preg_match("/define\(\s*['\"]DB_USER['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $matches)) $dbUser = $matches[1];
            if (preg_match("/define\(\s*['\"]DB_PASSWORD['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $matches)) $dbPass = $matches[1];
            if (preg_match("/define\(\s*['\"]DB_HOST['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $matches)) $dbHost = $matches[1];
            if (preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $matches)) $tablePrefix = $matches[1];

            // Prepare Backup directory
            $backupDirRoot = $basePath . DIRECTORY_SEPARATOR . 'happy-backup';
            if (!is_dir($backupDirRoot)) mkdir($backupDirRoot, 0755, true);
            $siteBackupDir = $backupDirRoot . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($siteBackupDir)) mkdir($siteBackupDir, 0755, true);

            $dateStr = date('Y-m-d_H_i_s');
            $random = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 16);
            $zipName = "BM_{$dateStr}_{$random}.zip";
            $zipPath = $siteBackupDir . DIRECTORY_SEPARATOR . $zipName;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("Falha ao criar o arquivo ZIP de backup.");
            }

            $db_tables_count = 0;
            if ($dbName && $dbUser && $dbPass && $dbHost) {
                try {
                    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    
                    $tables = [];
                    $stmt = $pdo->query("SHOW TABLES");
                    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                        $tables[] = $row[0];
                    }

                    foreach ($tables as $table) {
                        $sql = "DROP TABLE IF EXISTS `$table`;\n";
                        $createStmt = $pdo->query("SHOW CREATE TABLE `$table`");
                        $createRow = $createStmt->fetch(PDO::FETCH_NUM);
                        $sql .= $createRow[1] . ";\n\n";

                        $rows = $pdo->query("SELECT * FROM `$table`");
                        while ($r = $rows->fetch(PDO::FETCH_ASSOC)) {
                            $sql .= "INSERT INTO `$table` VALUES(";
                            $vals = [];
                            foreach ($r as $v) {
                                if (is_null($v)) {
                                    $vals[] = "NULL";
                                } else {
                                    $vals[] = $pdo->quote($v);
                                }
                            }
                            $sql .= implode(",", $vals) . ");\n";
                        }
                        $zip->addFromString("db_tables/{$table}.sql", $sql);
                        $db_tables_count++;
                    }
                } catch (Exception $e) {
                    // Continue even if DB fails
                }
            }

            $filesCount = 0;
            $bytesCount = 0;
            
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );
            
            foreach ($iterator as $fileInfo) {
                $filePath = $fileInfo->getPathname();
                $relativePath = substr($filePath, strlen($targetDir) + 1);
                $zipPathName = "wordpress/" . str_replace('\\', '/', $relativePath);
                
                if ($fileInfo->isDir()) {
                    $zip->addEmptyDir($zipPathName);
                } else {
                    $zip->addFile($filePath, $zipPathName);
                    $filesCount++;
                    $bytesCount += $fileInfo->getSize();
                }
            }

            $domain = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $dbDomain = "http://" . $domain . "/" . $folder;

            $manifest = [
                "name" => $zipName,
                "date" => date('Y-m-d H:i:s'),
                "files" => $filesCount,
                "bytes" => $bytesCount,
                "cron" => false,
                "total_queries" => $db_tables_count,
                "manifest" => date('Y-m-d H:i:s'),
                "millis_start" => microtime(true) * 1000,
                "millis_end" => microtime(true) * 1000,
                "version" => "2.1.5.1",
                "domain" => $domain,
                "dbdomain" => $dbDomain,
                "uid" => 1,
                "source_query_output" => 2000,
                "db_backup_engine" => "v4",
                "multisite" => false,
                "config" => [
                    "ABSPATH" => rtrim(str_replace('\\', '/', $targetDir), '/') . '/',
                    "DB_NAME" => $dbName,
                    "DB_USER" => $dbUser,
                    "DB_HOST" => $dbHost,
                    "table_prefix" => $tablePrefix ?: 'wp_'
                ]
            ];
            
            $zip->addFromString('bmi_backup_manifest.json', json_encode($manifest));
            $zip->addFromString('bmi_logs_this_backup.log', "Backup initiated by HappyWP.\nBackup successful.\n");

            $zip->close();

            ob_end_clean();
            echo json_encode(['status' => 'success', 'message' => 'Backup gerado com sucesso!', 'backup' => $zipName]);
        } elseif ($action === 'delete_backup') {
            $folder = $input['folder'] ?? '';
            $backup = $input['backup'] ?? '';

            if (empty($folder) || empty($backup)) throw new Exception('Pasta e backup são obrigatórios.');
            $folder = basename($folder);
            $backup = basename($backup);

            $backupPath = $basePath . DIRECTORY_SEPARATOR . 'happy-backup' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $backup;
            
            if (file_exists($backupPath)) {
                if (unlink($backupPath)) {
                    // If folder is now empty, remove it
                    $remainingDir = $basePath . DIRECTORY_SEPARATOR . 'happy-backup' . DIRECTORY_SEPARATOR . $folder;
                    $remainingFiles = glob($remainingDir . DIRECTORY_SEPARATOR . '*');
                    if (empty($remainingFiles)) {
                        @rmdir($remainingDir);
                    }
                    ob_end_clean();
                    echo json_encode(['status' => 'success', 'message' => 'Backup removido com sucesso.']);
                } else {
                    throw new Exception('Falha ao remover o backup (permissão negada).');
                }
            } else {
                throw new Exception('Arquivo de backup não encontrado.');
            }
        } elseif ($action === 'delete_orphan_folder') {
            $folder = $input['folder'] ?? '';
            if (empty($folder)) throw new Exception('Nome da pasta é obrigatório.');
            $folder = basename($folder);

            // Safety: only allow deleting if the site folder does NOT exist (truly orphan)
            $siteDir = $basePath . DIRECTORY_SEPARATOR . $folder;
            if (is_dir($siteDir)) {
                throw new Exception('Este site ainda existe. Use a exclusão normal.');
            }

            $backupFolderPath = $basePath . DIRECTORY_SEPARATOR . 'happy-backup' . DIRECTORY_SEPARATOR . $folder;
            if (!is_dir($backupFolderPath)) {
                throw new Exception('Pasta de backup não encontrada.');
            }

            if ($scanner->recursiveDelete($backupFolderPath)) {
                ob_end_clean();
                echo json_encode(['status' => 'success', 'message' => "Todos os backups de '{$folder}' foram removidos."]);
            } else {
                throw new Exception('Falha ao remover a pasta de backup.');
            }
        } else {
            throw new Exception('Invalid action.');
        }
    }
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
}
