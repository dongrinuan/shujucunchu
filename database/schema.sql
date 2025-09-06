-- 数字存储系统数据库结构
-- 创建数据库
CREATE DATABASE IF NOT EXISTS digital_storage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE digital_storage;

-- 主存储柜表
CREATE TABLE main_storages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    storage_id VARCHAR(20) UNIQUE NOT NULL COMMENT '存储柜编号',
    storage_name VARCHAR(100) NOT NULL COMMENT '存储柜名称',
    password1 VARCHAR(255) NOT NULL COMMENT '一级密码(哈希)',
    password2 VARCHAR(255) NOT NULL COMMENT '二级密码(哈希)',
    max_sub_storages INT DEFAULT 10 COMMENT '最大子存储柜数量',
    status ENUM('active', 'inactive') DEFAULT 'active' COMMENT '状态',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_storage_id (storage_id),
    INDEX idx_status (status)
) ENGINE=InnoDB COMMENT='主存储柜表';

-- 子存储柜表
CREATE TABLE sub_storages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    main_storage_id INT NOT NULL,
    sub_number INT NOT NULL COMMENT '子存储柜编号',
    description TEXT COMMENT '描述',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (main_storage_id) REFERENCES main_storages(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sub_storage (main_storage_id, sub_number),
    INDEX idx_main_storage (main_storage_id)
) ENGINE=InnoDB COMMENT='子存储柜表';

-- 存储文件表
CREATE TABLE stored_files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sub_storage_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL COMMENT '原始文件名',
    file_path VARCHAR(500) NOT NULL COMMENT '文件存储路径',
    file_size BIGINT NOT NULL COMMENT '文件大小(字节)',
    file_type VARCHAR(100) COMMENT '文件类型',
    description TEXT COMMENT '文件描述',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sub_storage_id) REFERENCES sub_storages(id) ON DELETE CASCADE,
    INDEX idx_sub_storage (sub_storage_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='存储文件表';

-- 操作日志表
CREATE TABLE operation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    storage_id VARCHAR(20) NOT NULL,
    operation_type ENUM('store', 'retrieve', 'manage', 'admin') NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_storage_id (storage_id),
    INDEX idx_operation_type (operation_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB COMMENT='操作日志表';

-- 系统设置表
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB COMMENT='系统设置表';

-- 插入默认设置
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('max_file_size', '10485760', '最大文件上传大小(字节) - 默认10MB'),
('allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt,zip,rar', '允许的文件类型'),
('default_sub_storages', '5', '默认子存储柜数量'),
('max_sub_storages', '20', '最大子存储柜数量');

-- 插入示例数据
INSERT INTO main_storages (storage_id, storage_name, password1, password2, max_sub_storages) VALUES
('ST202400001', '示例存储柜1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 10),
('ST202400002', '示例存储柜2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 15);

-- 插入示例子存储柜
INSERT INTO sub_storages (main_storage_id, sub_number, description) VALUES
(1, 1, '子存储柜1'),
(1, 2, '子存储柜2'),
(1, 3, '子存储柜3'),
(2, 1, '子存储柜1'),
(2, 2, '子存储柜2');
