<?php
// Simple launcher for the Travian installer
// This script sets up a basic web server to run the installer

$port = 8080;
$host = '0.0.0.0';
$installerPath = __DIR__;

echo "🚀 Travian Server Installer\n";
echo "==========================\n\n";
echo "Starting installer web server...\n";
echo "URL: http://localhost:$port\n";
echo "Press Ctrl+C to stop the server\n\n";

// Check if we're running as root
if (posix_getuid() !== 0) {
    echo "⚠️  WARNING: Not running as root. Some installation steps may fail.\n";
    echo "   Please run as root for full functionality.\n\n";
}

// Start PHP built-in server
$command = "php -S $host:$port -t $installerPath";
echo "Command: $command\n\n";

// Execute the server
passthru($command);
?>
