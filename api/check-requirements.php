<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to check if command exists
function commandExists($command) {
    $return = shell_exec("which $command");
    return !empty($return);
}

// Function to get PHP version
function getPhpVersion() {
    return PHP_VERSION;
}

// Function to check if running as root
function isRoot() {
    return posix_getuid() === 0;
}

// Function to check available disk space
function getDiskSpace() {
    $bytes = disk_free_space("/");
    return $bytes ? round($bytes / 1024 / 1024 / 1024, 2) : 0; // GB
}

// Function to check available memory
function getMemoryInfo() {
    $meminfo = file_get_contents('/proc/meminfo');
    preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches);
    return isset($matches[1]) ? round($matches[1] / 1024 / 1024, 2) : 0; // GB
}

// Function to check if port is available
function isPortAvailable($port) {
    $connection = @fsockopen('localhost', $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return false; // Port is in use
    }
    return true; // Port is available
}

// Check all requirements
$requirements = [
    'php_version' => [
        'name' => 'PHP Version',
        'critical' => true,
        'status' => version_compare(PHP_VERSION, '7.3.0', '>=') ? 'pass' : 'fail',
        'message' => version_compare(PHP_VERSION, '7.3.0', '>=') ? 
            'PHP ' . PHP_VERSION . ' (OK)' : 
            'PHP ' . PHP_VERSION . ' (Requires 7.3+)'
    ],
    
    'php_extensions' => [
        'name' => 'PHP Extensions',
        'critical' => true,
        'status' => 'pass',
        'message' => 'Checking required extensions...'
    ],
    
    'root_access' => [
        'name' => 'Root Access',
        'critical' => true,
        'status' => isRoot() ? 'pass' : 'fail',
        'message' => isRoot() ? 'Running as root (OK)' : 'Must run as root'
    ],
    
    'disk_space' => [
        'name' => 'Disk Space',
        'critical' => true,
        'status' => getDiskSpace() >= 5 ? 'pass' : 'fail',
        'message' => getDiskSpace() . 'GB available (Requires 5GB+)'
    ],
    
    'memory' => [
        'name' => 'System Memory',
        'critical' => false,
        'status' => getMemoryInfo() >= 2 ? 'pass' : 'warning',
        'message' => getMemoryInfo() . 'GB RAM (Recommended: 4GB+)'
    ],
    
    'yum' => [
        'name' => 'YUM Package Manager',
        'critical' => true,
        'status' => commandExists('yum') ? 'pass' : 'fail',
        'message' => commandExists('yum') ? 'YUM available (OK)' : 'YUM not found'
    ],
    
    'curl' => [
        'name' => 'cURL',
        'critical' => true,
        'status' => commandExists('curl') ? 'pass' : 'fail',
        'message' => commandExists('curl') ? 'cURL available (OK)' : 'cURL not found'
    ],
    
    'wget' => [
        'name' => 'WGET',
        'critical' => false,
        'status' => commandExists('wget') ? 'pass' : 'warning',
        'message' => commandExists('wget') ? 'WGET available (OK)' : 'WGET not found (optional)'
    ],
    
    'port_80' => [
        'name' => 'Port 80 (HTTP)',
        'critical' => false,
        'status' => isPortAvailable(80) ? 'pass' : 'warning',
        'message' => isPortAvailable(80) ? 'Port 80 available' : 'Port 80 in use (will configure anyway)'
    ],
    
    'port_443' => [
        'name' => 'Port 443 (HTTPS)',
        'critical' => false,
        'status' => isPortAvailable(443) ? 'pass' : 'warning',
        'message' => isPortAvailable(443) ? 'Port 443 available' : 'Port 443 in use (will configure anyway)'
    ],
    
    'port_3306' => [
        'name' => 'Port 3306 (MySQL)',
        'critical' => false,
        'status' => isPortAvailable(3306) ? 'pass' : 'warning',
        'message' => isPortAvailable(3306) ? 'Port 3306 available' : 'MySQL already running'
    ]
];

// Check PHP extensions with detailed status
$requiredExtensions = [
    'mysqli' => 'MySQL Improved Extension',
    'pdo' => 'PHP Data Objects',
    'pdo_mysql' => 'PDO MySQL Driver',
    'pdo_sqlite' => 'PDO SQLite Driver',
    'mysqlnd' => 'MySQL Native Driver',
    'gd' => 'GD Graphics Library',
    'mbstring' => 'Multibyte String',
    'xml' => 'XML Parser',
    'json' => 'JSON Support',
    'posix' => 'POSIX Functions',
    'sysvsem' => 'System V Semaphores',
    'sysvshm' => 'System V Shared Memory',
    'curl' => 'cURL Library',
    'sockets' => 'Socket Support',
    'ftp' => 'FTP Support',
    'calendar' => 'Calendar Functions',
    'fileinfo' => 'File Information',
    'zip' => 'ZIP Archive',
    'opcache' => 'OPcache (Recommended)',
    'redis' => 'Redis Extension (Optional)',
    'memcache' => 'Memcache Extension (Optional)',
    'memcached' => 'Memcached Extension (Optional)',
    'geoip' => 'GeoIP Extension (Optional)'
];

$extensionStatus = [];
$missingExtensions = [];
$optionalMissing = [];

foreach ($requiredExtensions as $ext => $description) {
    $isLoaded = extension_loaded($ext);
    $isOptional = in_array($ext, ['opcache', 'redis', 'memcache', 'memcached', 'geoip']);
    
    $extensionStatus[$ext] = [
        'loaded' => $isLoaded,
        'description' => $description,
        'optional' => $isOptional
    ];
    
    if (!$isLoaded) {
        if ($isOptional) {
            $optionalMissing[] = $ext;
        } else {
            $missingExtensions[] = $ext;
        }
    }
}

// Add individual extension checks
foreach ($extensionStatus as $ext => $status) {
    $requirements["php_ext_$ext"] = [
        'name' => "PHP $ext",
        'critical' => !$status['optional'],
        'status' => $status['loaded'] ? 'pass' : ($status['optional'] ? 'warning' : 'fail'),
        'message' => $status['loaded'] ? 
            "$ext loaded (OK)" : 
            ($status['optional'] ? "$ext not loaded (optional)" : "$ext missing (required)")
    ];
}

// Overall PHP extensions status
if (!empty($missingExtensions)) {
    $requirements['php_extensions']['status'] = 'fail';
    $requirements['php_extensions']['message'] = 'Missing required extensions: ' . implode(', ', $missingExtensions);
} else {
    $requirements['php_extensions']['status'] = 'pass';
    $requirements['php_extensions']['message'] = 'All required extensions available (OK)';
    if (!empty($optionalMissing)) {
        $requirements['php_extensions']['message'] .= ' | Optional missing: ' . implode(', ', $optionalMissing);
    }
}

// Check if Travian files exist
$travianPath = '/travian';
if (is_dir($travianPath)) {
    $requirements['travian_files'] = [
        'name' => 'Travian Files',
        'critical' => true,
        'status' => 'pass',
        'message' => 'Travian files found in /travian'
    ];
} else {
    $requirements['travian_files'] = [
        'name' => 'Travian Files',
        'critical' => true,
        'status' => 'fail',
        'message' => 'Travian files not found in /travian'
    ];
}

// Check PHP-FPM status
$phpFpmStatus = 'unknown';
$phpFpmMessage = 'PHP-FPM status unknown';
if (commandExists('php-fpm')) {
    $phpFpmStatus = 'pass';
    $phpFpmMessage = 'PHP-FPM available';
    
    // Check if PHP-FPM is running
    exec('systemctl is-active php-fpm 2>/dev/null', $output, $returnCode);
    if ($returnCode === 0) {
        $phpFpmMessage .= ' and running';
    } else {
        $phpFpmMessage .= ' but not running';
        $phpFpmStatus = 'warning';
    }
} else {
    $phpFpmStatus = 'fail';
    $phpFpmMessage = 'PHP-FPM not found';
}

$requirements['php_fpm'] = [
    'name' => 'PHP-FPM',
    'critical' => true,
    'status' => $phpFpmStatus,
    'message' => $phpFpmMessage
];

// Check OPcache configuration
$opcacheStatus = 'unknown';
$opcacheMessage = 'OPcache status unknown';
if (extension_loaded('opcache')) {
    $opcacheStatus = 'pass';
    $opcacheMessage = 'OPcache loaded';
    
    // Check OPcache configuration
    $opcacheConfig = opcache_get_status();
    if ($opcacheConfig && $opcacheConfig['opcache_enabled']) {
        $opcacheMessage .= ' and enabled';
    } else {
        $opcacheMessage .= ' but not enabled';
        $opcacheStatus = 'warning';
    }
} else {
    $opcacheStatus = 'warning';
    $opcacheMessage = 'OPcache not loaded (recommended for performance)';
}

$requirements['opcache_config'] = [
    'name' => 'OPcache Configuration',
    'critical' => false,
    'status' => $opcacheStatus,
    'message' => $opcacheMessage
];

// Check Redis availability
$redisStatus = 'unknown';
$redisMessage = 'Redis status unknown';
if (commandExists('redis-server')) {
    $redisStatus = 'pass';
    $redisMessage = 'Redis server available';
    
    // Check if Redis is running
    exec('systemctl is-active redis 2>/dev/null', $output, $returnCode);
    if ($returnCode === 0) {
        $redisMessage .= ' and running';
    } else {
        $redisMessage .= ' but not running';
        $redisStatus = 'warning';
    }
} else {
    $redisStatus = 'warning';
    $redisMessage = 'Redis server not found (optional)';
}

$requirements['redis_server'] = [
    'name' => 'Redis Server',
    'critical' => false,
    'status' => $redisStatus,
    'message' => $redisMessage
];

// Check Memcached availability
$memcachedStatus = 'unknown';
$memcachedMessage = 'Memcached status unknown';
if (commandExists('memcached')) {
    $memcachedStatus = 'pass';
    $memcachedMessage = 'Memcached available';
    
    // Check if Memcached is running
    exec('systemctl is-active memcached 2>/dev/null', $output, $returnCode);
    if ($returnCode === 0) {
        $memcachedMessage .= ' and running';
    } else {
        $memcachedMessage .= ' but not running';
        $memcachedStatus = 'warning';
    }
} else {
    $memcachedStatus = 'warning';
    $memcachedMessage = 'Memcached not found (optional)';
}

$requirements['memcached_server'] = [
    'name' => 'Memcached Server',
    'critical' => false,
    'status' => $memcachedStatus,
    'message' => $memcachedMessage
];

// Check system services
$services = [
    'nginx' => 'Nginx Web Server',
    'mysqld' => 'MySQL Database',
    'php-fpm' => 'PHP-FPM Process Manager',
    'firewalld' => 'Firewall Service'
];

foreach ($services as $service => $description) {
    $status = 'unknown';
    $message = "$description status unknown";
    
    if (commandExists('systemctl')) {
        exec("systemctl is-active $service 2>/dev/null", $output, $returnCode);
        if ($returnCode === 0) {
            $status = 'pass';
            $message = "$description is running";
        } else {
            exec("systemctl is-enabled $service 2>/dev/null", $output, $returnCode);
            if ($returnCode === 0) {
                $status = 'warning';
                $message = "$description is enabled but not running";
            } else {
                $status = 'fail';
                $message = "$description is not installed or enabled";
            }
        }
    }
    
    $requirements["service_$service"] = [
        'name' => $description,
        'critical' => in_array($service, ['nginx', 'mysqld', 'php-fpm']),
        'status' => $status,
        'message' => $message
    ];
}

echo json_encode($requirements, JSON_PRETTY_PRINT);
?>
