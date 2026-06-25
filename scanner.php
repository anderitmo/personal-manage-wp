<?php

class WPScanner {
    private $baseDir;

    public function __construct($baseDir) {
        $this->baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR);
    }

    public function scan($exclude = []) {
        $results = [];
        $dirs = array_filter(glob($this->baseDir . DIRECTORY_SEPARATOR . '*'), 'is_dir');

        foreach ($dirs as $dir) {
            $dirName = basename($dir);
            if (in_array($dirName, $exclude)) continue;
            
            if ($this->isWordPress($dir)) {
                $results[] = $this->getSiteInfo($dir);
            }
        }

        return $results;
    }

    private function isWordPress($path) {
        return file_exists($path . DIRECTORY_SEPARATOR . 'wp-config.php');
    }

    private function getSiteInfo($path) {
        $siteName = basename($path);
        $wpVersion = $this->getWPVersion($path);
        $dbConfig = $this->getDBConfig($path);
        
        $activePlugins = null; // null means unknown
        $activeTheme = null;
        $siteUrl = '';
        $users = [];
        $dbConnected = false;
        
        if ($dbConfig) {
            $hosts = [$dbConfig['DB_HOST']];
            if ($dbConfig['DB_HOST'] === 'localhost') $hosts[] = '127.0.0.1';

            foreach ($hosts as $host) {
                try {
                    $dsn = "mysql:host={$host};dbname={$dbConfig['DB_NAME']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbConfig['DB_USER'], $dbConfig['DB_PASSWORD'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 2
                    ]);
                    
                    $prefix = $dbConfig['TABLE_PREFIX'] ?? 'wp_';
                    
                    // Verifica se a tabela options existe (se o WP já foi instalado pelo navegador)
                    $checkTable = $pdo->query("SHOW TABLES LIKE '{$prefix}options'");
                    if ($checkTable->fetch() === false) {
                        $dbConnected = true;
                        $blogName = 'Pendente de Instalacao';
                        break;
                    }

                    $dbConnected = true;

                    // Get siteurl, blogname and updates
                    $stmt = $pdo->prepare("SELECT option_name, option_value FROM {$prefix}options WHERE option_name IN ('siteurl', 'blogname', '_site_transient_update_plugins', '_site_transient_update_themes', '_site_transient_update_core')");
                    $stmt->execute();
                    $options = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                    
                    $siteUrl = rtrim($options['siteurl'] ?? '', '/');
                    $blogName = $options['blogname'] ?? '';
                    $pluginUpdates = isset($options['_site_transient_update_plugins']) ? unserialize($options['_site_transient_update_plugins']) : null;
                    $themeUpdates = isset($options['_site_transient_update_themes']) ? unserialize($options['_site_transient_update_themes']) : null;
                    
                    $coreUpdates = isset($options['_site_transient_update_core']) ? unserialize($options['_site_transient_update_core']) : null;
                    $hasCoreUpdate = false;
                    if ($coreUpdates && isset($coreUpdates->updates) && is_array($coreUpdates->updates)) {
                        foreach ($coreUpdates->updates as $update) {
                            if ($update->response === 'upgrade') {
                                $hasCoreUpdate = true;
                                break;
                            }
                        }
                    }
                    
                    // Get active plugins
                    $stmt = $pdo->prepare("SELECT option_value FROM {$prefix}options WHERE option_name = 'active_plugins'");
                    $stmt->execute();
                    $data = $stmt->fetchColumn();
                    $activePlugins = $data ? unserialize($data) : [];
                    
                    // Get active theme
                    $stmt = $pdo->prepare("SELECT option_value FROM {$prefix}options WHERE option_name = 'stylesheet'");
                    $stmt->execute();
                    $activeTheme = $stmt->fetchColumn();

                    // Get users
                    $stmt = $pdo->prepare("SELECT user_login, user_email, display_name FROM {$prefix}users LIMIT 10");
                    $stmt->execute();
                    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    break;
                } catch (Exception $e) { continue; }
            }
        }

        return [
            'name' => $siteName,
            'title' => $blogName ?? $siteName,
            'path' => $path,
            'url' => $siteUrl,
            'version' => $wpVersion,
            'plugins' => $this->getPlugins($path, $activePlugins, $pluginUpdates),
            'themes' => $this->getThemes($path, $activeTheme, $themeUpdates),
            'users' => $users,
            'db_connected' => $dbConnected,
            'db_name' => $dbConfig['DB_NAME'] ?? 'N/A',
            'has_core_update' => $hasCoreUpdate ?? false,
            'backups' => $this->getBackups($siteName)
        ];
    }

    private function getBackups($siteName) {
        $backupDir = $this->baseDir . DIRECTORY_SEPARATOR . 'happy-backup' . DIRECTORY_SEPARATOR . $siteName;
        $backups = [];
        if (is_dir($backupDir)) {
            $files = glob($backupDir . DIRECTORY_SEPARATOR . '*.zip');
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
        }
        return $backups;
    }

    private function getPlugins($path, $activePlugins, $updates = null) {
        $pluginDir = $path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'plugins';
        if (!is_dir($pluginDir)) return [];

        $plugins = [];
        $items = array_filter(glob($pluginDir . DIRECTORY_SEPARATOR . '*'), 'is_dir');
        $files = glob($pluginDir . DIRECTORY_SEPARATOR . '*.php');
        $all = array_merge($items, $files);

        foreach ($all as $item) {
            $mainFile = is_dir($item) ? $this->findMainPluginFile($item) : $item;
            if ($mainFile && file_exists($mainFile)) {
                $info = $this->parseFileHeader($mainFile, ['Name' => 'Plugin Name', 'Version' => 'Version']);
                
                if ($info['Name']) {
                    $slug = is_dir($item) ? basename($item) . '/' . basename($mainFile) : basename($item);
                    
                    // Logic: true if in array, false if DB connected but not in array, null if DB disconnected
                    $status = null;
                    if (is_array($activePlugins)) {
                        $status = in_array($slug, $activePlugins);
                    }

                    $newVersion = null;
                    if ($updates && isset($updates->response[$slug])) {
                        $updateData = $updates->response[$slug];
                        $newVersion = is_object($updateData) ? $updateData->new_version : ($updateData['new_version'] ?? null);
                    }

                    $plugins[] = [
                        'name' => $info['Name'],
                        'version' => $info['Version'] ?: 'N/A',
                        'new_version' => $newVersion,
                        'active' => $status,
                        'slug' => $slug
                    ];
                }
            }
        }
        return $plugins;
    }

    private function getThemes($path, $activeTheme, $updates = null) {
        $themeDir = $path . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . 'themes';
        if (!is_dir($themeDir)) return [];

        $themes = [];
        $items = array_filter(glob($themeDir . DIRECTORY_SEPARATOR . '*'), 'is_dir');

        foreach ($items as $item) {
            $styleFile = $item . DIRECTORY_SEPARATOR . 'style.css';
            if (file_exists($styleFile)) {
                $info = $this->parseFileHeader($styleFile, ['Name' => 'Theme Name', 'Version' => 'Version']);
                
                if ($info['Name']) {
                    $slug = basename($item);
                    
                    $status = null;
                    if ($activeTheme !== null) {
                        $status = ($slug === $activeTheme);
                    }

                    $newVersion = null;
                    if ($updates && isset($updates->response[$slug])) {
                        $updateData = $updates->response[$slug];
                        $newVersion = is_object($updateData) ? $updateData['new_version'] : ($updateData['new_version'] ?? null);
                    }

                    $themes[] = [
                        'name' => $info['Name'],
                        'version' => $info['Version'] ?: 'N/A',
                        'new_version' => $newVersion,
                        'active' => $status,
                        'slug' => $slug
                    ];
                }
            }
        }
        return $themes;
    }

    public function deleteItem($type, $sitePath, $slug) {
        $folder = ($type === 'plugin') ? 'plugins' : 'themes';
        $slugPart = ($type === 'plugin') ? explode('/', $slug)[0] : $slug;
        $target = $sitePath . DIRECTORY_SEPARATOR . 'wp-content' . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . $slugPart;
        
        if (file_exists($target)) {
            return $this->recursiveDelete($target);
        }
        return false;
    }

    public function recursiveDelete($dir) {
        if (!file_exists($dir)) return true;
        if (!is_dir($dir)) return unlink($dir);
        
        $items = scandir($dir);
        if ($items === false) {
            // Cannot read directory, try to force permissions or return false
            return false;
        }
        
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!$this->recursiveDelete($dir . DIRECTORY_SEPARATOR . $item)) return false;
        }
        return rmdir($dir);
    }

    private function getWPVersion($path) {
        $versionFile = $path . DIRECTORY_SEPARATOR . 'wp-includes' . DIRECTORY_SEPARATOR . 'version.php';
        if (file_exists($versionFile)) {
            $content = file_get_contents($versionFile);
            if (preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', $content, $matches)) {
                return $matches[1];
            }
        }
        return 'Unknown';
    }

    private function getDBConfig($path) {
        $configFile = $path . DIRECTORY_SEPARATOR . 'wp-config.php';
        if (!file_exists($configFile)) return null;

        $content = file_get_contents($configFile);
        $config = [];
        
        $constants = ['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST'];
        foreach ($constants as $const) {
            if (preg_match("/define\(\s*['\"]{$const}['\"]\s*,\s*['\"](.*?)['\"]\s*\)/", $content, $matches)) {
                $config[$const] = $matches[1];
            }
        }

        if (preg_match("/\\\$table_prefix\s*=\s*['\"](.*?)['\"]/", $content, $matches)) {
            $config['TABLE_PREFIX'] = $matches[1];
        }

        return (count($config) >= 4) ? $config : null;
    }

    private function findMainPluginFile($dir) {
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.php');
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'Plugin Name:') !== false) {
                return $file;
            }
        }
        return null;
    }

    private function parseFileHeader($file, $map) {
        $content = file_get_contents($file, false, null, 0, 8192); // Read first 8KB
        $results = [];
        foreach ($map as $key => $header) {
            if (preg_match("/{$header}:(.*)$/mi", $content, $matches)) {
                $results[$key] = trim($matches[1]);
            } else {
                $results[$key] = '';
            }
        }
        return $results;
    }
}
