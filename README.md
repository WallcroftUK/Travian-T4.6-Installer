# Travian Server Web Installer

A modern, user-friendly web-based installer for setting up Travian game servers on CentOS 7+ systems.

## 🚀 Quick Start

### Option 1: Using the Launcher Script
```bash
# Make the launcher executable
chmod +x launch.php

# Run as root for full functionality
sudo php launch.php
```

### Option 2: Using PHP Built-in Server
```bash
# Navigate to installer directory
cd /installer

# Start the web server (run as root)
sudo php -S 0.0.0.0:8080
```

### Option 3: Using Apache/Nginx
```bash
# Copy installer to web root
sudo cp -r /installer /var/www/html/

# Access via browser
http://your-server-ip/installer/
```

## 🌐 Access the Installer

Once the server is running, open your web browser and navigate to:
- **Local access**: `http://localhost:8080`
- **Remote access**: `http://your-server-ip:8080`

## 📋 Installation Process

The installer guides you through 5 simple steps:

### Step 1: System Requirements Check
- ✅ PHP 7.3+ verification
- ✅ Required PHP extensions
- ✅ Root access verification
- ✅ Disk space (5GB+ required)
- ✅ System memory check
- ✅ Package manager availability
- ✅ Network ports availability

### Step 2: Database Configuration
- 🔧 MySQL connection settings
- 🔧 Database user creation
- 🔧 Connection testing

### Step 3: Server Configuration
- ⚙️ Server name and domain
- ⚙️ Admin email configuration
- ⚙️ Language and timezone settings
- ⚙️ Discord webhook (optional)

### Step 4: Installation Progress
- 📊 Real-time progress tracking
- 📝 Live installation logs
- 🔄 Automatic error handling

### Step 5: Installation Complete
- 🎉 Success confirmation
- 🔗 Server access URLs
- 👤 Admin credentials
- 📋 Next steps

## 🛠️ What Gets Installed

The installer automatically sets up:

### Source Code
- **TravianT4.6** - Downloaded from [GitHub repository](https://github.com/advocaite/TravianT4.6)
- **Complete File Structure** - All game files in `/travian/` directory
- **Gpack Integration** - Graphics pack files properly linked

### System Components
- **Nginx** - Web server with SSL support
- **MySQL 8.0** - Database server with performance optimization
- **PHP 7.3** - With all required extensions (including geoip, redis)
- **SSL Certificate** - Self-signed for testing

### Application Setup
- **Travian User** - Dedicated system user with sudo access
- **Directory Structure** - Proper file organization matching GitHub structure
- **Database Schema** - All required databases
- **Configuration Files** - Server and application configs
- **Systemd Service** - Automatic startup and management

### Security & Access
- **Firewall Rules** - HTTP/HTTPS/SSH access
- **Admin Panel** - Web-based administration
- **Discord Integration** - Optional webhook setup

## 🔧 Manual Installation

If you prefer manual installation, refer to:
- `/INSTALLATION.md` - Detailed step-by-step guide
- `/CENTOS7_QUICK_SETUP.md` - Quick setup commands

## 🚨 Requirements

### System Requirements
- **OS**: CentOS 7+ or RHEL 7+
- **RAM**: Minimum 2GB (4GB recommended)
- **Storage**: Minimum 5GB free space
- **CPU**: 2+ cores recommended
- **Network**: Static IP address

### Prerequisites
- Root access to the server
- Internet connection for package downloads and GitHub access
- Git or wget/curl for downloading source code

## 🔍 Troubleshooting

### Common Issues

**1. "Not running as root" warning**
```bash
# Run the installer as root
sudo php launch.php
```

**2. Port 8080 already in use**
```bash
# Use a different port
sudo php -S 0.0.0.0:8081
```

**3. Database connection fails**
- Verify MySQL is running: `systemctl status mysqld`
- Check root password is correct
- Ensure firewall allows MySQL port (3306)

**4. Installation fails at package installation**
- Check internet connectivity
- Verify yum repositories are working
- Run `yum update` manually first

### Logs and Debugging

**Installation logs** are displayed in real-time in the web interface.

**System logs** can be checked:
```bash
# Check service status
systemctl status nginx php-fpm xravian_ts3.service mysqld

# Check installation logs
journalctl -u xravian_ts3.service -f

# Check web server logs
tail -f /var/log/nginx/error.log
```

## 🎯 Post-Installation

After successful installation:

1. **Access your server**: Visit the provided URL
2. **Login as admin**: Use the generated admin credentials
3. **Configure settings**: Adjust game parameters as needed
4. **Set up SSL**: Replace self-signed certificate with Let's Encrypt
5. **Create backups**: Set up regular database backups

## 🔒 Security Notes

- Change default admin password immediately
- Set up proper SSL certificates for production
- Configure firewall rules appropriately
- Regular security updates are recommended
- Monitor server logs for suspicious activity

## 📞 Support

### Discord Community
Join our Discord server for support, updates, and community discussions:
**🔗 [https://discord.gg/ZgmNK2cQjm](https://discord.gg/ZgmNK2cQjm)**

### Troubleshooting
If you encounter issues:

1. Check the troubleshooting section above
2. Review the installation logs in the web interface
3. Check system logs using the commands provided
4. Ensure all requirements are met
5. Try manual installation as fallback
6. Join our Discord for community support

### 📋 Finding and Sending Logs

#### **During Installation**
- **Real-time logs**: Visible in the web interface during installation
- **Progress tracking**: Live updates with detailed status messages
- **Error messages**: Immediate feedback with specific error details

#### **After Installation**
- **Main log file**: `/var/log/travian_installer/install_{session_id}.log`
- **Error log file**: `/var/log/travian_installer/error_{session_id}.log`
- **Debug log file**: `/var/log/travian_installer/debug_{session_id}.log`
- **Command log file**: `/var/log/travian_installer/command_{session_id}.log`
- **Installation summary**: `/home/travian/INSTALLATION_SUMMARY.txt`

#### **System Logs**
- **Nginx logs**: `/var/log/nginx/error.log` and `/var/log/nginx/access.log`
- **MySQL logs**: `/var/log/mysqld.log` and `/var/log/mysql-slow.log`
- **PHP-FPM logs**: `/var/log/php-fpm/www-error.log`
- **System logs**: `/var/log/messages` and `/var/log/secure`

#### **📦 How to Send Logs for Support**

**Method 1: Download Support Package (Recommended)**
1. Access the installer interface: `http://your-server/installer/`
2. Click "Download Support Info" button
3. Get a ZIP file containing all logs and system information
4. Send the ZIP file to support

**Method 2: Manual Log Collection**
```bash
# Create a support package manually
cd /tmp
mkdir travian_support_$(date +%Y%m%d_%H%M%S)
cd travian_support_*

# Copy all relevant logs
cp /var/log/travian_installer/*.log ./
cp /home/travian/INSTALLATION_SUMMARY.txt ./
cp /var/log/nginx/error.log ./nginx_error.log
cp /var/log/mysqld.log ./mysql.log

# Get system information
uname -a > system_info.txt
php -v >> system_info.txt
nginx -v >> system_info.txt
mysql --version >> system_info.txt

# Check service status
systemctl status nginx >> service_status.txt
systemctl status mysqld >> service_status.txt
systemctl status php-fpm >> service_status.txt

# Create ZIP
cd ..
zip -r travian_support_$(date +%Y%m%d_%H%M%S).zip travian_support_*/
```

**Method 3: Quick Error Check**
```bash
# Check if services are running
travian --status

# Check recent errors
tail -f /var/log/travian_installer/error_*.log
tail -f /var/log/nginx/error.log
tail -f /var/log/mysqld.log
```

#### **When Contacting Support**
Please include:
1. **Support package ZIP file** (downloaded from installer)
2. **Server specifications** (OS, RAM, disk space)
3. **Error description** (what you were trying to do)
4. **Steps to reproduce** the issue
5. **Screenshots** of any error messages

---

**Happy Gaming!** 🎮

Your Travian server is now ready to host players and provide an amazing gaming experience!
