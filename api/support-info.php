<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include logger
require_once __DIR__ . '/logger.php';

session_start();

$action = $_GET['action'] ?? 'info';

try {
    $logger = new InstallerLogger();
    
    switch ($action) {
        case 'info':
            // Get support information
            $supportInfo = $logger->getSupportInfo();
            echo json_encode($supportInfo, JSON_PRETTY_PRINT);
            break;
            
        case 'logs':
            // Get specific log type
            $logType = $_GET['type'] ?? 'main';
            $logContent = $logger->getLog($logType);
            
            echo json_encode([
                'success' => true,
                'log_type' => $logType,
                'content' => $logContent,
                'size' => strlen($logContent)
            ]);
            break;
            
        case 'download':
            // Download all logs as ZIP
            $sessionId = session_id();
            $logDir = '/var/log/travian_installer';
            $zipFile = "/tmp/travian_installer_logs_{$sessionId}.zip";
            
            // Create ZIP file
            $zip = new ZipArchive();
            if ($zip->open($zipFile, ZipArchive::CREATE) === TRUE) {
                $files = glob($logDir . "/*{$sessionId}*.log");
                foreach ($files as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();
                
                // Send file
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="travian_installer_logs.zip"');
                header('Content-Length: ' . filesize($zipFile));
                readfile($zipFile);
                unlink($zipFile);
                exit;
            } else {
                throw new Exception('Failed to create ZIP file');
            }
            break;
            
        case 'system':
            // Get system information
            $systemInfo = [
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'os' => PHP_OS,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'user' => get_current_user(),
                'uid' => posix_getuid(),
                'gid' => posix_getgid(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'loaded_extensions' => get_loaded_extensions(),
                'disk_space' => [
                    'free' => disk_free_space('/'),
                    'total' => disk_total_space('/'),
                    'free_gb' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2),
                    'total_gb' => round(disk_total_space('/') / 1024 / 1024 / 1024, 2)
                ],
                'memory_usage' => [
                    'current' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true),
                    'current_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2)
                ],
                'services' => []
            ];
            
            // Check service status
            $services = ['nginx', 'php-fpm', 'mysqld', 'redis', 'memcached', 'firewalld'];
            foreach ($services as $service) {
                exec("systemctl is-active $service 2>/dev/null", $output, $returnCode);
                $systemInfo['services'][$service] = [
                    'active' => $returnCode === 0,
                    'status' => $returnCode === 0 ? 'running' : 'stopped'
                ];
            }
            
            echo json_encode($systemInfo, JSON_PRETTY_PRINT);
            break;
            
        case 'test':
            // Test system components
            $tests = [
                'php_extensions' => [],
                'commands' => [],
                'files' => [],
                'permissions' => []
            ];
            
            // Test PHP extensions
            $requiredExtensions = ['mysqli', 'pdo', 'pdo_mysql', 'gd', 'mbstring', 'xml', 'json', 'curl'];
            foreach ($requiredExtensions as $ext) {
                $tests['php_extensions'][$ext] = extension_loaded($ext);
            }
            
            // Test commands
            $commands = ['php', 'mysql', 'nginx', 'systemctl'];
            foreach ($commands as $cmd) {
                exec("which $cmd 2>/dev/null", $output, $returnCode);
                $tests['commands'][$cmd] = $returnCode === 0;
            }
            
            // Test files
            $files = ['/travian', '/etc/php.ini', '/etc/nginx/nginx.conf'];
            foreach ($files as $file) {
                $tests['files'][$file] = file_exists($file);
            }
            
            // Test permissions
            $dirs = ['/home/travian', '/var/log/travian_installer'];
            foreach ($dirs as $dir) {
                $tests['permissions'][$dir] = is_writable($dir);
            }
            
            echo json_encode($tests, JSON_PRETTY_PRINT);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
