<?php
// Background installation script
session_id($argv[1]);
session_start();

// Include logger
require_once __DIR__ . '/logger.php';

if (!isset($_SESSION['install_data'])) {
    $logger = new InstallerLogger();
    $logger->error('No installation data found in session');
    exit(1);
}

$installData = $_SESSION['install_data'];
$dbConfig = $installData['database'];
$serverConfig = $installData['server'];

// Initialize logger with session ID
$logger = new InstallerLogger($argv[1]);
$logger->systemInfo();
$logger->config('database', $dbConfig);
$logger->config('server', $serverConfig);

function updateProgress($progress, $message = '') {
    $_SESSION['install_progress'] = $progress;
    if ($message) {
        $_SESSION['install_logs'][] = [
            'type' => 'info',
            'message' => $message,
            'timestamp' => date('H:i:s')
        ];
    }
    session_write_close();
    session_start();
}

function addLog($type, $message) {
    $_SESSION['install_logs'][] = [
        'type' => $type,
        'message' => $message,
        'timestamp' => date('H:i:s')
    ];
    session_write_close();
    session_start();
}

function executeCommand($command, $description) {
    global $logger;
    
    $logger->info("Starting: $description");
    $logger->command($command, '', 0);
    
    addLog('info', $description);
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    
    $logger->command($command, implode("\n", $output), $returnCode);
    
    if ($returnCode === 0) {
        addLog('success', "$description completed successfully");
        $logger->info("Completed: $description");
        return true;
    } else {
        addLog('error', "$description failed: " . implode("\n", $output));
        $logger->error("Failed: $description", ['output' => $output, 'return_code' => $returnCode]);
        return false;
    }
}

try {
    updateProgress(5, 'Starting installation...');

    // Step 0: Verify installer permissions
    updateProgress(6, 'Verifying installer permissions...');
    $logger->step(6, 'Permission Verification', 'started');
    
    // Check if running as root
    if (posix_getuid() !== 0) {
        throw new Exception('Installer must be run as root for full functionality');
    }
    
    // Ensure installer has all necessary permissions
    executeCommand('chmod +x /installer/setup.sh', 'Setting installer script permissions');
    executeCommand('chmod -R 755 /installer', 'Setting installer directory permissions');
    
    $logger->step(6, 'Permission Verification', 'completed');

    // Step 1: Download Travian source code from GitHub
    updateProgress(8, 'Downloading Travian source code from GitHub...');
    $logger->step(8, 'Source Code Download', 'started');
    
    // Remove existing travian directory if it exists
    executeCommand('rm -rf /travian', 'Removing existing travian directory');
    
    // Download from GitHub
    if (!executeCommand('git clone https://github.com/advocaite/TravianT4.6.git /travian', 'Cloning TravianT4.6 from GitHub')) {
        // Fallback: try with wget/curl if git fails
        $logger->warning('Git clone failed, trying alternative download method');
        executeCommand('wget -O /tmp/travian.zip https://github.com/advocaite/TravianT4.6/archive/refs/heads/main.zip', 'Downloading Travian source as ZIP');
        executeCommand('unzip /tmp/travian.zip -d /tmp/', 'Extracting Travian source');
        executeCommand('mv /tmp/TravianT4.6-main /travian', 'Moving extracted files to /travian');
        executeCommand('rm /tmp/travian.zip', 'Cleaning up ZIP file');
    }
    
    // Verify download
    if (!is_dir('/travian')) {
        throw new Exception('Failed to download Travian source code');
    }
    
    // Set proper permissions
    executeCommand('chown -R root:root /travian', 'Setting travian directory ownership');
    executeCommand('chmod -R 755 /travian', 'Setting travian directory permissions');
    
    $logger->step(8, 'Source Code Download', 'completed');

    // Step 2: Update system packages
    updateProgress(10, 'Updating system packages...');
    if (!executeCommand('yum update -y', 'System update')) {
        throw new Exception('System update failed');
    }

    // Step 2: Install essential packages
    updateProgress(15, 'Installing essential packages...');
    $packages = [
        'epel-release',
        'git curl wget unzip',
        'yum-utils',
        'certbot python2-certbot-nginx'
    ];

    foreach ($packages as $package) {
        if (!executeCommand("yum install -y $package", "Installing $package")) {
            throw new Exception("Failed to install $package");
        }
    }

    // Step 3: Create travian user and setup directories
    updateProgress(20, 'Creating travian user and directories...');
    $logger->step(20, 'User and Directory Setup', 'started');
    
    // Create travian user with proper setup
    executeCommand('useradd -r -s /bin/bash -d /home/travian -m travian 2>/dev/null || true', 'Creating travian user');
    executeCommand('usermod -aG wheel travian 2>/dev/null || true', 'Adding travian to wheel group');
    
    // Create sudoers entry for travian user
    $sudoersContent = "travian ALL=(ALL) NOPASSWD:ALL\n";
    file_put_contents('/etc/sudoers.d/travian', $sudoersContent);
    executeCommand('chmod 440 /etc/sudoers.d/travian', 'Setting sudoers permissions');
    
    // Create directory structure (will be updated with actual server name later)
    $directories = [
        '/home/travian/gpack',
        '/home/travian/servers/ts3/public',
        '/home/travian/servers/ts3/include',
        '/home/travian/servers/ts2/public',
        '/home/travian/servers/ts2/include',
        '/home/travian/logs',
        '/home/travian/backups',
        '/home/travian/tmp'
    ];
    
    foreach ($directories as $dir) {
        executeCommand("mkdir -p $dir", "Creating directory: $dir");
    }
    
    // Set proper ownership
    executeCommand('chown -R travian:travian /home/travian', 'Setting travian user permissions');
    executeCommand('chmod -R 755 /home/travian', 'Setting directory permissions');
    
    $logger->step(20, 'User and Directory Setup', 'completed');

    // Step 4: Install Nginx
    updateProgress(25, 'Installing Nginx...');
    $logger->step(25, 'Nginx Installation', 'started');
    
    if (!executeCommand('yum install -y nginx', 'Installing Nginx')) {
        throw new Exception('Nginx installation failed');
    }
    
    // Create nginx user and set proper ownership
    executeCommand('useradd -r -s /bin/false nginx 2>/dev/null || true', 'Creating nginx user');
    
    // Set nginx directories ownership to travian user
    executeCommand('chown -R travian:travian /var/log/nginx', 'Setting nginx log ownership');
    executeCommand('chown -R travian:travian /etc/nginx', 'Setting nginx config ownership');
    executeCommand('chown -R travian:travian /var/cache/nginx', 'Setting nginx cache ownership');
    executeCommand('chown -R travian:travian /var/lib/nginx', 'Setting nginx lib ownership');
    
    executeCommand('systemctl start nginx', 'Starting Nginx');
    executeCommand('systemctl enable nginx', 'Enabling Nginx');
    
    $logger->step(25, 'Nginx Installation', 'completed');

    // Step 5: Install MySQL 8.0
    updateProgress(30, 'Installing MySQL 8.0...');
    
    // Install MySQL repository
    executeCommand('yum install -y https://dev.mysql.com/get/mysql84-community-release-el7-1.noarch.rpm', 'Installing MySQL repository');
    
    // Import GPG key
    executeCommand('curl -o /etc/pki/rpm-gpg/RPM-GPG-KEY-mysql-2023 https://repo.mysql.com/RPM-GPG-KEY-mysql-2023', 'Downloading MySQL GPG key');
    executeCommand('rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-mysql-2023', 'Importing MySQL GPG key');
    
    // Install MySQL Server
    if (!executeCommand('yum install -y mysql-community-server', 'Installing MySQL Server')) {
        throw new Exception('MySQL installation failed');
    }
    
    executeCommand('systemctl start mysqld', 'Starting MySQL');
    executeCommand('systemctl enable mysqld', 'Enabling MySQL');

    // Step 6: Configure MySQL
    updateProgress(35, 'Configuring MySQL...');
    $logger->step(35, 'MySQL Configuration', 'started');
    
    // Get temporary password
    $tempPassword = '';
    $logContent = file_get_contents('/var/log/mysqld.log');
    if (preg_match('/temporary password.*: (.+)/', $logContent, $matches)) {
        $tempPassword = trim($matches[1]);
        $logger->info("Found temporary MySQL password");
    }

    // Configure MySQL with comprehensive settings
    $mysqlConfig = "\n# Travian Server MySQL Configuration\n[mysqld]\ndefault_authentication_plugin = mysql_native_password\nbind-address = 0.0.0.0\nport = 3306\nmax_connections = 200\nmax_allowed_packet = 64M\ninnodb_buffer_pool_size = 256M\ninnodb_log_file_size = 64M\ninnodb_flush_log_at_trx_commit = 2\ninnodb_flush_method = O_DIRECT\nquery_cache_type = 1\nquery_cache_size = 32M\nquery_cache_limit = 2M\ntmp_table_size = 32M\nmax_heap_table_size = 32M\nslow_query_log = 1\nslow_query_log_file = /var/log/mysql-slow.log\nlong_query_time = 2\n\n[client]\nuser=root\npassword={$dbConfig['db_root_pass']}\nhost=localhost\nport=3306\n";
    file_put_contents('/etc/my.cnf', $mysqlConfig, FILE_APPEND);
    
    // Create MySQL log directory
    executeCommand('mkdir -p /var/log/mysql', 'Creating MySQL log directory');
    executeCommand('chown mysql:mysql /var/log/mysql', 'Setting MySQL log permissions');
    
    // Restart MySQL to apply configuration
    executeCommand('systemctl restart mysqld', 'Restarting MySQL with new configuration');
    
    // Wait for MySQL to start
    sleep(5);
    
    // Run MySQL secure installation if we have temp password
    if (!empty($tempPassword)) {
        $secureInstallScript = "#!/bin/bash\nmysql -u root -p'$tempPassword' --connect-expired-password -e \"ALTER USER 'root'@'localhost' IDENTIFIED BY '{$dbConfig['db_root_pass']}';\"\nmysql -u root -p'{$dbConfig['db_root_pass']}' -e \"DELETE FROM mysql.user WHERE User='';\"\nmysql -u root -p'{$dbConfig['db_root_pass']}' -e \"DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');\"\nmysql -u root -p'{$dbConfig['db_root_pass']}' -e \"DROP DATABASE IF EXISTS test;\"\nmysql -u root -p'{$dbConfig['db_root_pass']}' -e \"DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';\"\nmysql -u root -p'{$dbConfig['db_root_pass']}' -e \"FLUSH PRIVILEGES;\"\n";
        file_put_contents('/tmp/mysql_secure.sh', $secureInstallScript);
        executeCommand('chmod +x /tmp/mysql_secure.sh', 'Making MySQL secure script executable');
        executeCommand('/tmp/mysql_secure.sh', 'Running MySQL secure installation');
        executeCommand('rm /tmp/mysql_secure.sh', 'Cleaning up MySQL secure script');
    }
    
    $logger->step(35, 'MySQL Configuration', 'completed');

    // Step 7: Install PHP 7.3
    updateProgress(40, 'Installing PHP 7.3...');
    
    // Enable Remi repository
    executeCommand('yum install -y http://rpms.remirepo.net/enterprise/remi-release-7.rpm', 'Installing Remi repository');
    executeCommand('yum-config-manager --enable remi-php73', 'Enabling PHP 7.3 repository');
    
    // Install PHP and extensions
    $phpPackages = 'php php-mysqli php-pdo php-pdo_mysql php-pdo_sqlite php-mysqlnd php-memcache php-redis php-gd php-mbstring php-xml php-json php-posix php-sysvsem php-sysvshm php-curl php-sockets php-ftp php-calendar php-fileinfo php-zip php-geoip';
    if (!executeCommand("yum install -y $phpPackages", 'Installing PHP and extensions')) {
        throw new Exception('PHP installation failed');
    }

    // Configure PHP
    updateProgress(45, 'Configuring PHP...');
    $logger->step(45, 'PHP Configuration', 'started');
    
    // Basic PHP configuration
    $phpConfig = "\n; Travian Server Configuration\nmax_execution_time = 300\nmax_input_time = 60\nmemory_limit = 256M\nzlib.output_compression = Off\npost_max_size = 50M\nupload_max_filesize = 50M\nmax_file_uploads = 20\n";
    file_put_contents('/etc/php.ini', $phpConfig, FILE_APPEND);
    
    // Configure OPcache
    $opcacheConfig = "\n; OPcache Configuration\nopcache.enable=1\nopcache.enable_cli=1\nopcache.memory_consumption=128\nopcache.interned_strings_buffer=8\nopcache.max_accelerated_files=4000\nopcache.revalidate_freq=2\nopcache.fast_shutdown=1\nopcache.save_comments=1\n";
    file_put_contents('/etc/php.ini', $opcacheConfig, FILE_APPEND);
    
    // Configure PHP-FPM for travian user
    $phpFpmConfig = "[www]\nuser = travian\ngroup = travian\nlisten = 127.0.0.1:9000\nlisten.owner = travian\nlisten.group = travian\nlisten.mode = 0660\npm = dynamic\npm.max_children = 50\npm.start_servers = 5\npm.min_spare_servers = 5\npm.max_spare_servers = 35\npm.max_requests = 500\npm.process_idle_timeout = 10s\npm.max_requests = 1000\nphp_admin_value[error_log] = /var/log/php-fpm/www-error.log\nphp_admin_flag[log_errors] = on\nphp_value[session.save_handler] = files\nphp_value[session.save_path] = /var/lib/php/session\nphp_value[soap.wsdl_cache_dir] = /var/lib/php/wsdlcache\n";
    file_put_contents('/etc/php-fpm.d/www.conf', $phpFpmConfig);
    
    // Create PHP session directory
    executeCommand('mkdir -p /var/lib/php/session', 'Creating PHP session directory');
    executeCommand('chown -R travian:travian /var/lib/php', 'Setting PHP session ownership');
    
    executeCommand('systemctl restart php-fpm', 'Restarting PHP-FPM');
    executeCommand('systemctl enable php-fpm', 'Enabling PHP-FPM');
    $logger->step(45, 'PHP Configuration', 'completed');

    // Install and configure Redis
    updateProgress(47, 'Installing and configuring Redis...');
    $logger->step(47, 'Redis Installation', 'started');
    
    if (executeCommand('yum install -y redis', 'Installing Redis')) {
        // Configure Redis
        $redisConfig = "# Redis Configuration for Travian\nbind 127.0.0.1\nport 6379\ntimeout 300\ntcp-keepalive 60\nmaxmemory 256mb\nmaxmemory-policy allkeys-lru\nsave 900 1\nsave 300 10\nsave 60 10000\n";
        file_put_contents('/etc/redis.conf', $redisConfig, FILE_APPEND);
        
        executeCommand('systemctl start redis', 'Starting Redis');
        executeCommand('systemctl enable redis', 'Enabling Redis');
        $logger->step(47, 'Redis Installation', 'completed');
    } else {
        $logger->warning('Redis installation failed, continuing without Redis');
    }

    // Install and configure Memcached
    updateProgress(48, 'Installing and configuring Memcached...');
    $logger->step(48, 'Memcached Installation', 'started');
    
    if (executeCommand('yum install -y memcached', 'Installing Memcached')) {
        // Configure Memcached
        $memcachedConfig = "# Memcached Configuration for Travian\nPORT=\"11211\"\nUSER=\"memcached\"\nMAXCONN=\"1024\"\nCACHESIZE=\"256\"\nOPTIONS=\"-l 127.0.0.1\"\n";
        file_put_contents('/etc/sysconfig/memcached', $memcachedConfig);
        
        executeCommand('systemctl start memcached', 'Starting Memcached');
        executeCommand('systemctl enable memcached', 'Enabling Memcached');
        $logger->step(48, 'Memcached Installation', 'completed');
    } else {
        $logger->warning('Memcached installation failed, continuing without Memcached');
    }

    // Step 8: Setup databases
    updateProgress(50, 'Setting up databases...');
    
    // Create databases and user
    $serverName = str_replace(['.', '-'], '_', $serverConfig['server_name']);
    $dbCommands = [
        "CREATE DATABASE IF NOT EXISTS main;",
        "CREATE DATABASE IF NOT EXISTS {$serverName}_ts2;",
        "CREATE DATABASE IF NOT EXISTS {$serverName}_ts3;",
        "CREATE USER IF NOT EXISTS '{$dbConfig['db_user']}'@'localhost' IDENTIFIED BY '{$dbConfig['db_pass']}';",
        "GRANT ALL PRIVILEGES ON main.* TO '{$dbConfig['db_user']}'@'localhost';",
        "GRANT ALL PRIVILEGES ON {$serverName}_ts2.* TO '{$dbConfig['db_user']}'@'localhost';",
        "GRANT ALL PRIVILEGES ON {$serverName}_ts3.* TO '{$dbConfig['db_user']}'@'localhost';",
        "FLUSH PRIVILEGES;"
    ];

    $dbScript = "mysql -u root -p{$dbConfig['db_root_pass']} << 'EOF'\n" . implode("\n", $dbCommands) . "\nEOF";
    if (!executeCommand($dbScript, 'Creating databases and user')) {
        throw new Exception('Database setup failed');
    }

    // Import main.sql if it exists
    if (file_exists('/travian/main.sql')) {
        executeCommand("mysql -u root -p{$dbConfig['db_root_pass']} main < /travian/main.sql", 'Importing main database schema');
    }
    
    $logger->step(50, 'Database Setup', 'completed');

    // Step 8.5: Setup application files (keep in /travian as per GitHub structure)
    updateProgress(52, 'Setting up application files...');
    $logger->step(52, 'File Setup', 'started');
    
    // The files are already in /travian from GitHub download
    // We just need to set proper permissions and create necessary directories
    
    // Create additional directories needed
    $additionalDirs = [
        '/travian/tmp',
        '/travian/logs',
        '/travian/backups',
        '/travian/cache'
    ];
    
    foreach ($additionalDirs as $dir) {
        executeCommand("mkdir -p $dir", "Creating directory: $dir");
    }
    
    // Set proper permissions for all travian files
    executeCommand('chown -R travian:travian /travian', 'Setting travian file ownership');
    executeCommand('chmod -R 755 /travian', 'Setting travian file permissions');
    
    // Set specific permissions for sensitive files
    executeCommand('chmod 600 /travian/main.sql', 'Setting main.sql permissions');
    executeCommand('chmod 644 /travian/dbbackup.php', 'Setting dbbackup.php permissions');
    
    // Create gpack directory if it doesn't exist in sections
    if (is_dir('/travian/sections/gpack')) {
        executeCommand('ln -sf /travian/sections/gpack /travian/gpack', 'Creating gpack symlink');
        $logger->info('Gpack symlink created successfully');
    } else {
        $logger->warning('Gpack directory not found in sections');
    }
    
    // Create cache directory for gpack
    executeCommand('mkdir -p /travian/cache/gpack', 'Creating gpack cache directory');
    executeCommand('chown -R travian:travian /travian/cache', 'Setting cache permissions');
    
    $logger->step(52, 'File Setup', 'completed');

    // Step 9: Create application directories with user's server name
    updateProgress(55, 'Creating application directories...');
    $serverName = str_replace(['.', '-'], '_', $serverConfig['server_name']);
    $serverDir = "/home/travian/{$serverName}/servers/ts3";
    
    executeCommand("mkdir -p {$serverDir}/public", 'Creating public directory');
    executeCommand("mkdir -p {$serverDir}/include", 'Creating include directory');
    executeCommand('chown -R travian:travian /home/travian/', 'Setting directory permissions');

    // Step 10: Configure Nginx
    updateProgress(60, 'Configuring Nginx...');
    $logger->step(60, 'Nginx Configuration', 'started');
    
    // Create comprehensive nginx configuration
    $nginxMainConfig = "user travian;\nworker_processes auto;\nerror_log /var/log/nginx/error.log;\npid /run/nginx.pid;\n\ninclude /usr/share/nginx/modules/*.conf;\n\nevents {\n    worker_connections 1024;\n    use epoll;\n    multi_accept on;\n}\n\nhttp {\n    log_format main '\$remote_addr - \$remote_user [\$time_local] \"\$request\" '\n                    '\$status \$body_bytes_sent \"\$http_referer\" '\n                    '\"\$http_user_agent\" \"\$http_x_forwarded_for\"';\n\n    access_log /var/log/nginx/access.log main;\n\n    sendfile on;\n    tcp_nopush on;\n    tcp_nodelay on;\n    keepalive_timeout 65;\n    types_hash_max_size 2048;\n    client_max_body_size 50M;\n\n    include /etc/nginx/mime.types;\n    default_type application/octet-stream;\n\n    # SSL Configuration\n    ssl_protocols TLSv1.2 TLSv1.3;\n    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;\n    ssl_prefer_server_ciphers off;\n    ssl_session_cache shared:SSL:10m;\n    ssl_session_timeout 10m;\n\n    # Gzip Configuration\n    gzip on;\n    gzip_vary on;\n    gzip_min_length 1024;\n    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;\n\n    include /etc/nginx/conf.d/*.conf;\n    include /etc/nginx/partial.d/*.conf;\n}\n";
    file_put_contents('/etc/nginx/nginx.conf', $nginxMainConfig);
    
    // Create nginx partials directory
    executeCommand('mkdir -p /etc/nginx/partial.d', 'Creating nginx partials directory');
    
    // Create default server configuration
    $defaultServerConfig = "# Default server configuration\nserver {\n    listen 80 default_server;\n    listen [::]:80 default_server;\n    server_name _;\n    root /usr/share/nginx/html;\n    index index.html index.htm;\n\n    location / {\n        try_files \$uri \$uri/ =404;\n    }\n\n    error_page 404 /404.html;\n    location = /404.html {\n    }\n\n    error_page 500 502 503 504 /50x.html;\n    location = /50x.html {\n    }\n}\n";
    file_put_contents('/etc/nginx/conf.d/default.conf', $defaultServerConfig);
    
    // Create Travian defaults partial
    $travianDefaults = "# Travian Server Defaults\n# Include this in your server blocks\n\n# Security headers\nadd_header X-Frame-Options DENY;\nadd_header X-Content-Type-Options nosniff;\nadd_header X-XSS-Protection \"1; mode=block\";\nadd_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;\n\n# Main location block\nlocation / {\n    try_files \$uri \$uri/ /index.php?\$query_string;\n}\n\n# PHP processing\nlocation ~ \\.php\$ {\n    fastcgi_pass 127.0.0.1:9000;\n    fastcgi_index index.php;\n    fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;\n    include fastcgi_params;\n    fastcgi_read_timeout 300;\n    fastcgi_connect_timeout 300;\n    fastcgi_send_timeout 300;\n}\n\n# Static files caching\nlocation ~* \\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)\$ {\n    expires 1y;\n    add_header Cache-Control \"public, immutable\";\n    access_log off;\n}\n\n# Deny access to sensitive files\nlocation ~ /\\. {\n    deny all;\n    access_log off;\n    log_not_found off;\n}\n\nlocation ~ \\.(sql|log|conf)\$ {\n    deny all;\n    access_log off;\n    log_not_found off;\n}\n";
    file_put_contents('/etc/nginx/partial.d/travian_defaults.conf', $travianDefaults);
    
    // Create server configuration using partials
    $nginxServerConfig = "# HTTP to HTTPS redirect\nserver {\n    listen 80;\n    server_name {$serverConfig['server_name']};\n    return 301 https://\$server_name\$request_uri;\n}\n\n# HTTPS server\nserver {\n    listen 443 ssl http2;\n    server_name {$serverConfig['server_name']};\n    root /travian/main_script/public;\n    index index.php index.html;\n\n    # SSL Configuration\n    ssl_certificate /etc/ssl/certs/nginx-selfsigned.crt;\n    ssl_certificate_key /etc/ssl/private/nginx-selfsigned.key;\n    ssl_session_timeout 1d;\n    ssl_session_cache shared:SSL:50m;\n    ssl_stapling on;\n    ssl_stapling_verify on;\n\n    # Include Travian defaults\n    include /etc/nginx/partial.d/travian_defaults.conf;\n}\n";
    file_put_contents("/etc/nginx/conf.d/{$serverName}_ts3.conf", $nginxServerConfig);

    // Set proper ownership for nginx configuration
    executeCommand('chown -R travian:travian /etc/nginx', 'Setting nginx config ownership');
    executeCommand('chown -R travian:travian /var/log/nginx', 'Setting nginx log ownership');

    // Test and restart Nginx
    executeCommand('nginx -t', 'Testing Nginx configuration');
    executeCommand('systemctl restart nginx', 'Restarting Nginx');
    
    $logger->step(60, 'Nginx Configuration', 'completed');

    // Step 11: Generate SSL certificate
    updateProgress(65, 'Generating SSL certificate...');
    executeCommand('mkdir -p /etc/ssl/private /etc/ssl/certs', 'Creating SSL directories');
    
    $sslCommand = "openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout /etc/ssl/private/nginx-selfsigned.key -out /etc/ssl/certs/nginx-selfsigned.crt -subj '/C=US/ST=State/L=City/O=Organization/CN={$serverConfig['server_name']}'";
    executeCommand($sslCommand, 'Generating self-signed SSL certificate');

    // Step 12: Create configuration files
    updateProgress(70, 'Creating configuration files...');
    $logger->step(70, 'Configuration Files', 'started');
    
    // Global config
    $serverName = str_replace(['.', '-'], '_', $serverConfig['server_name']);
    $globalConfig = "<?php\nglobal \$globalConfig;\n\$globalConfig = [];\n\n// Static Parameters\n\$globalConfig['staticParameters'] = [];\n\$globalConfig['staticParameters']['default_language'] = '{$serverConfig['default_language']}';\n\$globalConfig['staticParameters']['default_timezone'] = '{$serverConfig['timezone']}';\n\$globalConfig['staticParameters']['indexUrl'] = 'https://{$serverConfig['server_name']}/';\n\$globalConfig['staticParameters']['adminEmail'] = '{$serverConfig['admin_email']}';\n\$globalConfig['staticParameters']['recaptcha_public_key'] = '';\n\$globalConfig['staticParameters']['recaptcha_private_key'] = '';\n\n// Database Configuration\n\$globalConfig['dataSources'] = [];\n\$globalConfig['dataSources']['globalDB'] = [];\n\$globalConfig['dataSources']['globalDB']['hostname'] = '{$dbConfig['db_host']}';\n\$globalConfig['dataSources']['globalDB']['username'] = '{$dbConfig['db_user']}';\n\$globalConfig['dataSources']['globalDB']['password'] = '{$dbConfig['db_pass']}';\n\$globalConfig['dataSources']['globalDB']['database'] = 'main';\n\$globalConfig['dataSources']['globalDB']['charset'] = 'utf8mb4';\n\$globalConfig['dataSources']['globalDB']['port'] = {$dbConfig['db_port']};\n\n// Server Configuration\n\$globalConfig['server'] = [];\n\$globalConfig['server']['name'] = '{$serverConfig['server_name']}';\n\$globalConfig['server']['domain'] = '{$serverConfig['server_name']}';\n\$globalConfig['server']['admin_email'] = '{$serverConfig['admin_email']}';\n\$globalConfig['server']['timezone'] = '{$serverConfig['timezone']}';\n\$globalConfig['server']['language'] = '{$serverConfig['default_language']}';\n\n// Paths\n\$globalConfig['paths'] = [];\n\$globalConfig['paths']['travian_root'] = '/travian/';\n\$globalConfig['paths']['main_script'] = '/travian/main_script/';\n\$globalConfig['paths']['gpack'] = '/travian/gpack/';\n\$globalConfig['paths']['cache'] = '/travian/cache/';\n\$globalConfig['paths']['logs'] = '/travian/logs/';\n\$globalConfig['paths']['backups'] = '/travian/backups/';\n";
    file_put_contents("/home/travian/{$serverName}/globalConfig.php", $globalConfig);
    executeCommand("chown travian:travian /home/travian/{$serverName}/globalConfig.php", 'Setting global config permissions');

    // Server environment
    $serverDir = "/home/travian/{$serverName}/servers/ts3";
    $envConfig = "<?php\ndefine(\"IS_DEV\", false);\ndefine(\"PUBLIC_PATH\", dirname(__DIR__) . \"/public/\");\ndefine(\"INCLUDE_PATH\", dirname(__DIR__) . \"/include/\");\ndefine(\"GPACK_PATH\", \"/travian/gpack/\");\ndefine(\"MAIN_SCRIPT_PATH\", \"/travian/main_script/\");\ndefine(\"TRAVIAN_ROOT\", \"/travian/\");\n";
    file_put_contents("{$serverDir}/include/env.php", $envConfig);
    executeCommand("chown travian:travian {$serverDir}/include/env.php", 'Setting env config permissions');
    
    // Create server-specific configuration
    $serverConfigContent = "<?php\n// Server Configuration for {$serverConfig['server_name']}\n\$serverConfig = [];\n\$serverConfig['name'] = '{$serverConfig['server_name']}';\n\$serverConfig['admin_email'] = '{$serverConfig['admin_email']}';\n\$serverConfig['default_language'] = '{$serverConfig['default_language']}';\n\$serverConfig['timezone'] = '{$serverConfig['timezone']}';\n\$serverConfig['database'] = [\n    'host' => '{$dbConfig['db_host']}',\n    'port' => '{$dbConfig['db_port']}',\n    'user' => '{$dbConfig['db_user']}',\n    'pass' => '{$dbConfig['db_pass']}',\n    'name' => '{$serverName}_ts3'\n];\n\$serverConfig['paths'] = [\n    'travian_root' => '/travian/',\n    'main_script' => '/travian/main_script/',\n    'gpack' => '/travian/gpack/',\n    'cache' => '/travian/cache/',\n    'logs' => '/travian/logs/',\n    'backups' => '/travian/backups/'\n];\n";
    file_put_contents("{$serverDir}/include/config.php", $serverConfigContent);
    executeCommand("chown travian:travian {$serverDir}/include/config.php", 'Setting server config permissions');
    
    // Create gpack configuration in the correct location
    $gpackConfig = "<?php\n// Gpack Configuration\n\$gpackConfig = [];\n\$gpackConfig['path'] = '/travian/gpack/';\n\$gpackConfig['enabled'] = true;\n\$gpackConfig['cache_enabled'] = true;\n\$gpackConfig['cache_path'] = '/travian/cache/gpack/';\n";
    file_put_contents('/travian/gpack/config.php', $gpackConfig);
    executeCommand('chown travian:travian /travian/gpack/config.php', 'Setting gpack config permissions');
    
    // Configure TaskWorker
    $taskWorkerConfig = "<?php\n// TaskWorker Configuration for {$serverConfig['server_name']}\n\$taskWorkerConfig = [];\n\$taskWorkerConfig['users'] = [];\n\$taskWorkerConfig['users']['{$serverName}'] = [];\n\$taskWorkerConfig['users']['{$serverName}']['main_domain'] = '{$serverConfig['server_name']}';\n\$taskWorkerConfig['users']['{$serverName}']['type'] = 'cloudflare';\n\$taskWorkerConfig['users']['{$serverName}']['zone_id'] = ''; // User needs to set this\n\$taskWorkerConfig['users']['{$serverName}']['email'] = '{$serverConfig['admin_email']}';\n\$taskWorkerConfig['users']['{$serverName}']['api_key'] = ''; // User needs to set this\n\$taskWorkerConfig['users']['{$serverName}']['ip'] = ''; // Auto-detected\n";
    file_put_contents('/travian/TaskWorker/config.php', $taskWorkerConfig);
    executeCommand('chown travian:travian /travian/TaskWorker/config.php', 'Setting TaskWorker config permissions');
    
    // Update TaskWorker runTasks.php with user's configuration
    $runTasksContent = file_get_contents('/travian/TaskWorker/runTasks.php');
    $runTasksContent = str_replace(
        "'' => [",
        "'{$serverName}' => [",
        $runTasksContent
    );
    $runTasksContent = str_replace(
        "'main_domain' => '',",
        "'main_domain' => '{$serverConfig['server_name']}',",
        $runTasksContent
    );
    $runTasksContent = str_replace(
        "'email' => '',",
        "'email' => '{$serverConfig['admin_email']}',",
        $runTasksContent
    );
    $runTasksContent = str_replace(
        "'zone_id' => '',",
        "'zone_id' => '', // User needs to set Cloudflare Zone ID",
        $runTasksContent
    );
    $runTasksContent = str_replace(
        "'api_key' => '',",
        "'api_key' => '', // User needs to set Cloudflare API Key",
        $runTasksContent
    );
    file_put_contents('/travian/TaskWorker/runTasks.php', $runTasksContent);
    executeCommand('chown travian:travian /travian/TaskWorker/runTasks.php', 'Setting TaskWorker script permissions');
    
    $logger->step(70, 'Configuration Files', 'completed');

    // Discord webhook
    if (!empty($serverConfig['discord_webhook'])) {
        file_put_contents('/travian/discord_webhook.url', $serverConfig['discord_webhook']);
        executeCommand('chmod 600 /travian/discord_webhook.url', 'Setting webhook file permissions');
        executeCommand('chown travian:travian /travian/discord_webhook.url', 'Setting webhook file ownership');
    }

    // Step 13: Create systemd service
    updateProgress(75, 'Creating systemd service...');
    
    $serviceName = "{$serverName}_ts3";
    $serviceConfig = "[Unit]\nDescription=Travian game engine (ts3) - {$serverConfig['server_name']}\nAfter=network.target mysql.service\n\n[Service]\nType=simple\nUser=travian\nGroup=travian\nWorkingDirectory={$serverDir}/include\nExecStart=/usr/bin/php {$serverDir}/include/{$serviceName}.service.php\nRestart=always\nRestartSec=10\n\n[Install]\nWantedBy=multi-user.target\n";
    file_put_contents("/etc/systemd/system/{$serviceName}.service", $serviceConfig);

    // Create service script
    $serviceScript = "#!/usr/bin/php -q\n<?php\nrequire __DIR__ . \"/env.php\";\nif(IS_DEV){\n    require(\"/travian/main_script_dev/include/AutomationEngine.php\");\n} else {\n    require(\"/travian/main_script/include/AutomationEngine.php\");\n}\n";
    file_put_contents("{$serverDir}/include/{$serviceName}.service.php", $serviceScript);
    executeCommand("chmod +x {$serverDir}/include/{$serviceName}.service.php", 'Making service script executable');

    // Step 14: Update sync.sh with user's server name and run install
    updateProgress(80, 'Configuring sync.sh and installing services...');
    $logger->step(80, 'Sync.sh Configuration', 'started');
    
    // Update sync.sh to use the user's server name instead of xravian
    $syncShContent = file_get_contents('/travian/Manager/sync.sh');
    $syncShContent = str_replace(
        'supported_users=("")',
        "supported_users=(\"{$serverName}\")",
        $syncShContent
    );
    file_put_contents('/travian/Manager/sync.sh', $syncShContent);
    executeCommand('chmod +x /travian/Manager/sync.sh', 'Making sync.sh executable');
    executeCommand('chown travian:travian /travian/Manager/sync.sh', 'Setting sync.sh ownership');
    
    // Run the install command from sync.sh
    executeCommand('cd /travian/Manager && ./sync.sh --install', 'Running sync.sh install command');
    
    $logger->step(80, 'Sync.sh Configuration', 'completed');
    
    // Step 15: Start additional services
    updateProgress(82, 'Starting additional services...');
    executeCommand('systemctl daemon-reload', 'Reloading systemd daemon');
    executeCommand("systemctl start {$serviceName}.service", 'Starting Travian service');
    executeCommand("systemctl enable {$serviceName}.service", 'Enabling Travian service');

    // Step 16: Configure firewall
    updateProgress(85, 'Configuring firewall...');
    executeCommand('firewall-cmd --permanent --add-service=http', 'Adding HTTP to firewall');
    executeCommand('firewall-cmd --permanent --add-service=https', 'Adding HTTPS to firewall');
    executeCommand('firewall-cmd --permanent --add-service=ssh', 'Adding SSH to firewall');
    executeCommand('firewall-cmd --reload', 'Reloading firewall');

    // Step 17: Final setup
    updateProgress(90, 'Running final setup...');
    
    // Set Multihunter password
    $adminPassword = sha1('admin123');
    executeCommand("mysql -u root -p{$dbConfig['db_root_pass']} -e \"UPDATE users SET password='$adminPassword' WHERE id=2\" {$serverName}_ts3", 'Setting Multihunter password');

    // Generate admin token
    $token = bin2hex(random_bytes(16));
    executeCommand("mysql -u root -p{$dbConfig['db_root_pass']} -e \"INSERT INTO paymentConfig (loginToken) VALUES ('$token') ON DUPLICATE KEY UPDATE loginToken='$token'\" main", 'Generating admin token');

    // Step 18: Create admin access URL
    updateProgress(95, 'Creating admin access...');
    $loginHash = sha1(sha1('admin123'));
    $adminUrl = "https://{$serverConfig['server_name']}/login.php?action=multiLogin&hash=$loginHash&token=$token";
    
    // Store admin URL for final step
    $_SESSION['admin_url'] = $adminUrl;
    $_SESSION['server_url'] = "https://{$serverConfig['server_name']}";
    
    // Step 19: Create installation summary
    updateProgress(97, 'Creating installation summary...');
    $logger->step(97, 'Installation Summary', 'started');
    
    $summary = "Travian Server Installation Summary\n";
    $summary .= "==================================\n\n";
    $summary .= "Server Information:\n";
    $summary .= "- Server Name: {$serverConfig['server_name']}\n";
    $summary .= "- Admin Email: {$serverConfig['admin_email']}\n";
    $summary .= "- Default Language: {$serverConfig['default_language']}\n";
    $summary .= "- Timezone: {$serverConfig['timezone']}\n\n";
    $summary .= "Access Information:\n";
    $summary .= "- Server URL: https://{$serverConfig['server_name']}\n";
    $summary .= "- Admin URL: $adminUrl\n";
    $summary .= "- Admin Username: admin\n";
    $summary .= "- Admin Password: admin123\n\n";
    $summary .= "File Structure:\n";
    $summary .= "- Travian Root: /travian/\n";
    $summary .= "- Main Script: /travian/main_script/\n";
    $summary .= "- Gpack Files: /travian/gpack/ (symlink to /travian/sections/gpack/)\n";
    $summary .= "- Server Config: {$serverDir}/\n";
    $summary .= "- Global Config: /home/travian/{$serverName}/globalConfig.php\n";
    $summary .= "- Logs: /travian/logs/\n";
    $summary .= "- Backups: /travian/backups/\n";
    $summary .= "- Cache: /travian/cache/\n";
    $summary .= "- Installer: /installer/\n\n";
    $summary .= "Database Information:\n";
    $summary .= "- Host: {$dbConfig['db_host']}\n";
    $summary .= "- Port: {$dbConfig['db_port']}\n";
    $summary .= "- User: {$dbConfig['db_user']}\n";
    $summary .= "- Databases: main, {$serverName}_ts2, {$serverName}_ts3\n\n";
    $summary .= "Services:\n";
    $summary .= "- Nginx: Web Server\n";
    $summary .= "- MySQL: Database Server\n";
    $summary .= "- PHP-FPM: PHP Process Manager\n";
    $summary .= "- Redis: Caching (if installed)\n";
    $summary .= "- Memcached: Caching (if installed)\n";
    $summary .= "- Travian Service: {$serviceName}.service\n";
    $summary .= "- TravianIndex: Main index service\n";
    $summary .= "- TravianMail: Mail notification service\n";
    $summary .= "- TravianTaskWorker: Background task service\n\n";
    $summary .= "Management Commands:\n";
    $summary .= "- travian --status: Check service status\n";
    $summary .= "- travian --restart: Restart all services\n";
    $summary .= "- travian --start: Start all services\n";
    $summary .= "- travian --stop: Stop all services\n";
    $summary .= "- travian --sync-global: Sync global files\n";
    $summary .= "- travian --update: Update server files\n\n";
    $summary .= "Installation completed on: " . date('Y-m-d H:i:s') . "\n";
    
    file_put_contents('/home/travian/INSTALLATION_SUMMARY.txt', $summary);
    executeCommand('chown travian:travian /home/travian/INSTALLATION_SUMMARY.txt', 'Setting summary file permissions');
    
    $logger->step(97, 'Installation Summary', 'completed');

    updateProgress(100, 'Installation completed successfully!');
    addLog('success', 'Travian server installation completed successfully!');
    addLog('info', "Server URL: https://{$serverConfig['server_name']}");
    addLog('info', "Admin URL: $adminUrl");
    addLog('info', 'Default admin credentials: admin / admin123');

    $logger->step(100, 'Installation Complete', 'completed', [
        'server_url' => "https://{$serverConfig['server_name']}",
        'admin_url' => $adminUrl,
        'admin_username' => 'admin',
        'admin_password' => 'admin123'
    ]);
    
    $logger->info('Installation completed successfully');
    $logger->info("Server URL: https://{$serverConfig['server_name']}");
    $logger->info("Admin URL: $adminUrl");

    $_SESSION['install_status'] = 'completed';

} catch (Exception $e) {
    $logger->error('Installation failed', [
        'error_message' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine(),
        'error_trace' => $e->getTraceAsString()
    ]);
    
    addLog('error', 'Installation failed: ' . $e->getMessage());
    $_SESSION['install_status'] = 'error';
    $_SESSION['install_progress'] = 0;
}
?>
