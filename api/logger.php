<?php
/**
 * Comprehensive Logging System for Travian Installer
 * Provides detailed logging for debugging and support
 */

class InstallerLogger {
    private $logDir;
    private $sessionId;
    private $logFile;
    private $errorFile;
    private $debugFile;
    
    public function __construct($sessionId = null) {
        $this->sessionId = $sessionId ?: session_id();
        $this->logDir = '/var/log/travian_installer';
        $this->logFile = $this->logDir . '/install_' . $this->sessionId . '.log';
        $this->errorFile = $this->logDir . '/error_' . $this->sessionId . '.log';
        $this->debugFile = $this->logDir . '/debug_' . $this->sessionId . '.log';
        
        // Create log directory if it doesn't exist
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    /**
     * Log general information
     */
    public function info($message, $context = []) {
        $this->writeLog('INFO', $message, $context, $this->logFile);
    }
    
    /**
     * Log warnings
     */
    public function warning($message, $context = []) {
        $this->writeLog('WARNING', $message, $context, $this->logFile);
    }
    
    /**
     * Log errors
     */
    public function error($message, $context = []) {
        $this->writeLog('ERROR', $message, $context, $this->logFile);
        $this->writeLog('ERROR', $message, $context, $this->errorFile);
    }
    
    /**
     * Log debug information
     */
    public function debug($message, $context = []) {
        $this->writeLog('DEBUG', $message, $context, $this->debugFile);
    }
    
    /**
     * Log system commands and their output
     */
    public function command($command, $output = '', $returnCode = 0) {
        $context = [
            'command' => $command,
            'output' => $output,
            'return_code' => $returnCode,
            'success' => $returnCode === 0
        ];
        
        $level = $returnCode === 0 ? 'INFO' : 'ERROR';
        $message = "Command executed: $command";
        
        $this->writeLog($level, $message, $context, $this->logFile);
        
        if ($returnCode !== 0) {
            $this->writeLog('ERROR', $message, $context, $this->errorFile);
        }
    }
    
    /**
     * Log system information
     */
    public function systemInfo() {
        $info = [
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
            'disk_free_space' => disk_free_space('/'),
            'disk_total_space' => disk_total_space('/'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true)
        ];
        
        $this->debug('System Information', $info);
    }
    
    /**
     * Log installation step
     */
    public function step($stepNumber, $stepName, $status = 'started', $details = []) {
        $context = [
            'step_number' => $stepNumber,
            'step_name' => $stepName,
            'status' => $status,
            'details' => $details,
            'timestamp' => time()
        ];
        
        $message = "Step $stepNumber: $stepName - $status";
        $this->writeLog('INFO', $message, $context, $this->logFile);
    }
    
    /**
     * Log configuration data
     */
    public function config($configType, $configData) {
        // Sanitize sensitive data
        $sanitized = $this->sanitizeConfig($configData);
        
        $context = [
            'config_type' => $configType,
            'config_data' => $sanitized
        ];
        
        $this->debug("Configuration: $configType", $context);
    }
    
    /**
     * Get log contents
     */
    public function getLog($type = 'main') {
        $file = $type === 'error' ? $this->errorFile : 
                $type === 'debug' ? $this->debugFile : $this->logFile;
        
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        
        return '';
    }
    
    /**
     * Get log summary for support
     */
    public function getSupportInfo() {
        $summary = [
            'session_id' => $this->sessionId,
            'timestamp' => date('Y-m-d H:i:s'),
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'user' => get_current_user(),
            'uid' => posix_getuid(),
            'memory_limit' => ini_get('memory_limit'),
            'loaded_extensions' => get_loaded_extensions(),
            'disk_space' => [
                'free' => disk_free_space('/'),
                'total' => disk_total_space('/')
            ],
            'logs' => [
                'main_log' => $this->getLog('main'),
                'error_log' => $this->getLog('error'),
                'debug_log' => $this->getLog('debug')
            ]
        ];
        
        return $summary;
    }
    
    /**
     * Write log entry
     */
    private function writeLog($level, $message, $context, $file) {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $logEntry = "[$timestamp] [$level] $message$contextStr\n";
        
        file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Sanitize configuration data to remove sensitive information
     */
    private function sanitizeConfig($config) {
        $sensitiveKeys = ['password', 'pass', 'secret', 'key', 'token', 'auth'];
        
        if (is_array($config)) {
            foreach ($config as $key => $value) {
                if (is_array($value)) {
                    $config[$key] = $this->sanitizeConfig($value);
                } elseif (in_array(strtolower($key), $sensitiveKeys)) {
                    $config[$key] = '***REDACTED***';
                }
            }
        }
        
        return $config;
    }
    
    /**
     * Clean up old log files
     */
    public static function cleanup($days = 7) {
        $logDir = '/var/log/travian_installer';
        if (!is_dir($logDir)) {
            return;
        }
        
        $files = glob($logDir . '/*.log');
        $cutoff = time() - ($days * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}

// Global logger instance
$logger = new InstallerLogger();
?>
