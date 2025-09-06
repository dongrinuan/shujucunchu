# 🚀 数字存储系统部署指导

## 📋 系统概述

数字存储系统是一个基于PHP + MySQL的现代化文件存储管理平台，支持多级密码保护、体积限制管理、文件和文本存储等功能。

## 🔧 系统要求

### 服务器环境
- **PHP版本**: 7.4 或更高版本
- **MySQL版本**: 5.7 或更高版本 / MariaDB 10.3+
- **Web服务器**: Apache 2.4+ 或 Nginx 1.18+
- **磁盘空间**: 至少 500MB（根据存储需求调整）

### PHP扩展要求
- `pdo_mysql` - 数据库连接
- `fileinfo` - 文件类型检测
- `mbstring` - 多字节字符串处理
- `session` - 会话管理
- `json` - JSON数据处理

## 📦 部署步骤

### 1. 获取源代码
```bash
# 方式一：直接下载解压
# 将项目文件解压到Web服务器根目录

# 方式二：Git克隆（如果有Git仓库）
git clone [repository-url] digital-storage
cd digital-storage
```

### 2. 配置Web服务器

#### Apache配置
确保启用以下模块：
```apache
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule php7_module modules/libphp7.so
```

虚拟主机配置示例：
```apache
<VirtualHost *:80>
    ServerName storage.example.com
    DocumentRoot "/path/to/digital-storage"
    
    <Directory "/path/to/digital-storage">
        AllowOverride All
        Require all granted
    </Directory>
    
    # 安全配置
    <Directory "/path/to/digital-storage/uploads">
        <FilesMatch "\.(php|php3|php4|php5|phtml)$">
            Require all denied
        </FilesMatch>
    </Directory>
</VirtualHost>
```

#### Nginx配置
```nginx
server {
    listen 80;
    server_name storage.example.com;
    root /path/to/digital-storage;
    index index.php index.html;

    # 安全配置
    location /uploads {
        location ~ \.php$ {
            deny all;
        }
    }
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 3. 数据库配置

#### 创建数据库
```sql
-- 创建数据库
CREATE DATABASE digital_storage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 创建用户（推荐）
CREATE USER 'storage_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON digital_storage.* TO 'storage_user'@'localhost';
FLUSH PRIVILEGES;
```

#### 导入数据库结构
```bash
mysql -u storage_user -p digital_storage < database/schema.sql
```

### 4. 配置应用

#### 数据库连接配置
复制并编辑数据库配置文件：
```bash
cp config/database.example.php config/database.php
```

编辑 `config/database.php`：
```php
<?php
$host = 'localhost';        // 数据库主机
$dbname = 'digital_storage'; // 数据库名
$username = 'storage_user';  // 数据库用户名
$password = 'your_secure_password'; // 数据库密码

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("数据库连接失败: " . $e->getMessage());
}
?>
```

### 5. 文件权限设置

#### Linux/Unix系统
```bash
# 设置目录权限
chmod 755 /path/to/digital-storage
chmod 755 /path/to/digital-storage/uploads
chmod 755 /path/to/digital-storage/config

# 设置文件权限
chmod 644 /path/to/digital-storage/*.php
chmod 644 /path/to/digital-storage/config/*.php
chmod 600 /path/to/digital-storage/config/database.php

# 确保uploads目录可写
chmod 777 /path/to/digital-storage/uploads

# 设置所有者（假设Web服务器用户为www-data）
chown -R www-data:www-data /path/to/digital-storage
```

#### Windows系统
确保以下目录具有写入权限：
- `uploads/` - 文件上传目录
- `config/` - 配置文件目录

### 6. 安全配置

#### .htaccess文件（Apache）
项目根目录的 `.htaccess` 应包含：
```apache
# 防止直接访问配置文件
<Files "database.php">
    Require all denied
</Files>

# 防止目录浏览
Options -Indexes

# 设置安全头
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

### 7. 创建管理员账户

首次访问系统时，需要创建管理员账户。访问：
```
http://your-domain.com/admin/login.php
```

使用以下默认管理员账户登录（首次登录后请立即修改）：
- 用户名: `admin`
- 密码: `admin123`

## 🔐 安全建议

### 1. 密码安全
- 立即修改默认管理员密码
- 使用强密码（至少12位，包含大小写字母、数字、特殊字符）
- 定期更换密码

### 2. 文件上传安全
- 定期检查uploads目录
- 监控磁盘使用情况
- 设置文件大小限制

### 3. 数据库安全
- 使用独立的数据库用户，避免使用root
- 定期备份数据库
- 启用MySQL慢查询日志

### 4. 服务器安全
- 定期更新PHP和MySQL版本
- 配置防火墙
- 启用HTTPS（推荐使用Let's Encrypt）

## 📊 监控和维护

### 1. 日志监控
系统日志位置：
- 操作日志：通过管理后台查看
- Web服务器日志：`/var/log/apache2/` 或 `/var/log/nginx/`
- PHP错误日志：根据php.ini配置

### 2. 性能监控
```bash
# 检查磁盘使用情况
df -h

# 检查MySQL进程
mysqladmin -u root -p processlist

# 检查PHP-FPM状态（如使用）
systemctl status php7.4-fpm
```

### 3. 备份策略
```bash
#!/bin/bash
# 每日备份脚本示例

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backup/digital-storage"

# 备份数据库
mysqldump -u storage_user -p digital_storage > "$BACKUP_DIR/db_$DATE.sql"

# 备份上传文件
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" /path/to/digital-storage/uploads/

# 清理7天前的备份
find $BACKUP_DIR -name "*.sql" -mtime +7 -delete
find $BACKUP_DIR -name "*.tar.gz" -mtime +7 -delete
```

## 🚨 故障排除

### 常见问题

#### 1. 数据库连接失败
```
错误: 数据库连接失败
解决方案:
- 检查数据库服务是否启动
- 验证config/database.php中的连接信息
- 确认数据库用户权限
```

#### 2. 文件上传失败
```
错误: 文件上传失败
解决方案:
- 检查uploads目录权限（需要写入权限）
- 检查PHP配置中的upload_max_filesize和post_max_size
- 确认磁盘空间充足
```

#### 3. 页面空白或500错误
```
错误: 页面显示空白或500错误
解决方案:
- 检查PHP错误日志
- 验证PHP版本兼容性
- 检查文件权限设置
```

### 调试模式
如需启用调试模式，在 `config/database.php` 末尾添加：
```php
// 仅在开发环境使用
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## 📞 技术支持

### 系统信息
- **版本**: 1.0.0
- **最后更新**: 2025年9月6日
- **PHP要求**: 7.4+
- **数据库**: MySQL 5.7+ / MariaDB 10.3+

### 获取帮助
如遇到问题，请检查：
1. 系统日志文件
2. Web服务器错误日志
3. PHP错误日志
4. 数据库连接状态

---

## 📝 更新日志

### v1.0.0 (2025-09-06)
- ✅ 初始版本发布
- ✅ 多级密码保护系统
- ✅ 文件和文本存储功能
- ✅ 体积限制管理
- ✅ 响应式用户界面
- ✅ 管理员后台
- ✅ 分页浏览功能

---

**🎉 部署完成后，您的数字存储系统就可以正常使用了！**

建议在生产环境中定期备份数据，并保持系统更新以确保安全性。
