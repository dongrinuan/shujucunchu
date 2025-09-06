#!/bin/bash

# 数字存储系统快速安装脚本
# 适用于 Ubuntu/Debian 系统

echo "🚀 数字存储系统 - 快速安装脚本"
echo "================================="

# 检查root权限
if [ "$EUID" -ne 0 ]; then
    echo "❌ 请使用root权限运行此脚本"
    echo "   使用: sudo ./install.sh"
    exit 1
fi

# 更新系统包
echo "📦 更新系统包..."
apt update

# 安装必要软件
echo "🔧 安装必要软件..."
apt install -y apache2 mysql-server php7.4 php7.4-mysql php7.4-mbstring php7.4-json php7.4-session

# 启用Apache模块
echo "⚙️ 配置Apache..."
a2enmod rewrite
systemctl restart apache2

# 创建数据库
echo "🗄️ 配置MySQL数据库..."
read -p "请输入MySQL root密码: " -s mysql_root_password
echo

# 生成随机数据库密码
db_password=$(openssl rand -base64 12)

mysql -u root -p$mysql_root_password <<EOF
CREATE DATABASE IF NOT EXISTS digital_storage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'storage_user'@'localhost' IDENTIFIED BY '$db_password';
GRANT ALL PRIVILEGES ON digital_storage.* TO 'storage_user'@'localhost';
FLUSH PRIVILEGES;
EOF

if [ $? -eq 0 ]; then
    echo "✅ 数据库创建成功"
else
    echo "❌ 数据库创建失败，请检查MySQL密码"
    exit 1
fi

# 导入数据库结构
echo "📋 导入数据库结构..."
mysql -u storage_user -p$db_password digital_storage < database/schema.sql

# 创建数据库配置文件
echo "📝 创建数据库配置..."
cat > config/database.php << EOF
<?php
\$host = 'localhost';
\$dbname = 'digital_storage';
\$username = 'storage_user';
\$password = '$db_password';

try {
    \$pdo = new PDO("mysql:host=\$host;dbname=\$dbname;charset=utf8mb4", \$username, \$password);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException \$e) {
    die("数据库连接失败: " . \$e->getMessage());
}
?>
EOF

# 设置文件权限
echo "🔐 设置文件权限..."
chown -R www-data:www-data .
chmod 755 .
chmod 777 uploads/
chmod 600 config/database.php

# 配置Apache虚拟主机
read -p "请输入域名（如: storage.example.com，直接回车使用默认）: " domain_name
if [ -z "$domain_name" ]; then
    domain_name="digital-storage.local"
fi

cat > /etc/apache2/sites-available/digital-storage.conf << EOF
<VirtualHost *:80>
    ServerName $domain_name
    DocumentRoot $(pwd)
    
    <Directory "$(pwd)">
        AllowOverride All
        Require all granted
    </Directory>
    
    <Directory "$(pwd)/uploads">
        <FilesMatch "\.(php|php3|php4|php5|phtml)$">
            Require all denied
        </FilesMatch>
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/digital-storage_error.log
    CustomLog \${APACHE_LOG_DIR}/digital-storage_access.log combined
</VirtualHost>
EOF

# 启用站点
a2ensite digital-storage.conf
systemctl reload apache2

# 添加到hosts文件（仅用于测试）
if ! grep -q "$domain_name" /etc/hosts; then
    echo "127.0.0.1 $domain_name" >> /etc/hosts
fi

echo ""
echo "🎉 安装完成！"
echo "================================="
echo "🌐 网站地址: http://$domain_name"
echo "👤 默认管理员: admin / admin123"
echo "🔒 数据库密码: $db_password"
echo ""
echo "⚠️  重要提醒:"
echo "   1. 首次登录后请立即修改管理员密码"
echo "   2. 生产环境请配置HTTPS"
echo "   3. 定期备份数据库和uploads目录"
echo "   4. 请保存好数据库密码: $db_password"
echo ""
echo "📖 详细说明请查看: README.md 和 DEPLOYMENT_GUIDE.md"
