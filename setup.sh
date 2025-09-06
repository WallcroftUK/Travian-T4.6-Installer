#!/bin/bash

# Travian Server Installer Setup Script
# This script prepares the installer for use

echo "🚀 Travian Server Installer Setup"
echo "=================================="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "⚠️  This script should be run as root for full functionality."
    echo "   Some features may not work properly without root access."
    echo ""
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Exiting..."
        exit 1
    fi
fi

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Installing PHP..."
    yum install -y php php-cli
    if [ $? -ne 0 ]; then
        echo "❌ Failed to install PHP. Please install PHP manually."
        exit 1
    fi
    echo "✅ PHP installed successfully."
else
    echo "✅ PHP is already installed: $(php -v | head -n 1)"
fi

# Check if Travian files exist
if [ ! -d "/travian" ]; then
    echo "⚠️  Travian files not found in /travian"
    echo "   Please ensure Travian files are placed in /travian directory"
    echo ""
    read -p "Continue anyway? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Exiting..."
        exit 1
    fi
else
    echo "✅ Travian files found in /travian"
fi

# Set proper permissions
echo "🔧 Setting up permissions..."
chmod +x launch.php
chmod +x setup.sh
chmod -R 755 .

# Create necessary directories
echo "📁 Creating necessary directories..."
mkdir -p /tmp/travian_installer
mkdir -p /var/log/travian_installer

# Set up log rotation
echo "📝 Setting up log rotation..."
cat > /etc/logrotate.d/travian-installer << 'EOF'
/var/log/travian_installer/*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 644 root root
}
EOF

echo ""
echo "✅ Setup completed successfully!"
echo ""
echo "🎯 Next steps:"
echo "   1. Run: sudo php launch.php"
echo "   2. Open browser to: http://localhost:8080"
echo "   3. Follow the installation wizard"
echo ""
echo "📖 For more information, see README.md"
echo ""
echo "Happy installing! 🎮"
