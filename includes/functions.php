<?php
/**
 * 通用函数库
 */

// 验证密码
function verifyPassword($input_password, $stored_password) {
    return password_verify($input_password, $stored_password);
}

// 加密密码
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// 生成随机存储柜ID
function generateStorageId() {
    return 'ST' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// 检查存储柜是否存在
function storageExists($pdo, $storage_id) {
    $stmt = $pdo->prepare("SELECT id FROM main_storages WHERE storage_id = ?");
    $stmt->execute([$storage_id]);
    return $stmt->fetch() ? true : false;
}

// 获取主存储柜信息
function getMainStorage($pdo, $storage_id) {
    $stmt = $pdo->prepare("SELECT * FROM main_storages WHERE storage_id = ?");
    $stmt->execute([$storage_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 获取子存储柜列表
function getSubStorages($pdo, $main_storage_id) {
    $stmt = $pdo->prepare("SELECT * FROM sub_storages WHERE main_storage_id = ? ORDER BY sub_number");
    $stmt->execute([$main_storage_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 创建子存储柜
function createSubStorage($pdo, $main_storage_id, $sub_number) {
    // 获取默认体积限制
    $default_volume_limit = getSystemSetting($pdo, 'default_sub_storage_volume_limit', 104857600); // 默认100MB
    
    $stmt = $pdo->prepare("INSERT INTO sub_storages (main_storage_id, sub_number, volume_limit, current_volume, created_at) VALUES (?, ?, ?, 0, NOW())");
    return $stmt->execute([$main_storage_id, $sub_number, $default_volume_limit]);
}

// 删除子存储柜
function deleteSubStorage($pdo, $sub_storage_id) {
    // 先删除存储的文件
    $stmt = $pdo->prepare("SELECT * FROM stored_files WHERE sub_storage_id = ?");
    $stmt->execute([$sub_storage_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($files as $file) {
        if (file_exists($file['file_path'])) {
            unlink($file['file_path']);
        }
    }
    
    // 删除文件记录
    $stmt = $pdo->prepare("DELETE FROM stored_files WHERE sub_storage_id = ?");
    $stmt->execute([$sub_storage_id]);
    
    // 删除子存储柜
    $stmt = $pdo->prepare("DELETE FROM sub_storages WHERE id = ?");
    return $stmt->execute([$sub_storage_id]);
}

// 清理主存储柜
function clearMainStorage($pdo, $main_storage_id) {
    // 获取所有子存储柜
    $sub_storages = getSubStorages($pdo, $main_storage_id);
    
    // 删除所有文件
    foreach ($sub_storages as $sub) {
        $stmt = $pdo->prepare("SELECT * FROM stored_files WHERE sub_storage_id = ?");
        $stmt->execute([$sub['id']]);
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($files as $file) {
            if (file_exists($file['file_path'])) {
                unlink($file['file_path']);
            }
        }
    }
    
    // 删除所有文件记录
    $stmt = $pdo->prepare("DELETE sf FROM stored_files sf INNER JOIN sub_storages ss ON sf.sub_storage_id = ss.id WHERE ss.main_storage_id = ?");
    $stmt->execute([$main_storage_id]);
    
    // 重置密码
    $stmt = $pdo->prepare("UPDATE main_storages SET password1 = '', password2 = '', updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$main_storage_id]);
}

// 存储文件
function storeFile($pdo, $main_storage_id, $sub_storage_id, $file_info, $description = '') {
    // 检查体积限制
    if (!canStoreFile($pdo, $sub_storage_id, $file_info['size'])) {
        return false;
    }
    
    $upload_dir = 'uploads/' . date('Y/m/');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_name = time() . '_' . $file_info['name'];
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($file_info['tmp_name'], $file_path)) {
        $stmt = $pdo->prepare("INSERT INTO stored_files (sub_storage_id, original_name, file_path, file_size, description, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $result = $stmt->execute([$sub_storage_id, $file_info['name'], $file_path, $file_info['size'], $description]);
        
        // 更新子存储柜的当前体积
        if ($result) {
            updateSubStorageVolume($pdo, $sub_storage_id);
        }
        
        return $result;
    }
    
    return false;
}

// 存储文本
function storeText($pdo, $main_storage_id, $sub_storage_id, $title, $content, $content_type = 'plain') {
    $stmt = $pdo->prepare("INSERT INTO stored_texts (main_storage_id, sub_storage_id, title, content, content_type, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    return $stmt->execute([$main_storage_id, $sub_storage_id, $title, $content, $content_type]);
}

// 获取存储的文件
function getStoredFiles($pdo, $sub_storage_id) {
    $stmt = $pdo->prepare("SELECT * FROM stored_files WHERE sub_storage_id = ? ORDER BY created_at DESC");
    $stmt->execute([$sub_storage_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 获取存储的文本
function getStoredTexts($pdo, $sub_storage_id) {
    $stmt = $pdo->prepare("SELECT * FROM stored_texts WHERE sub_storage_id = ? ORDER BY created_at DESC");
    $stmt->execute([$sub_storage_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 删除存储的文本
function deleteStoredText($pdo, $text_id) {
    $stmt = $pdo->prepare("DELETE FROM stored_texts WHERE id = ?");
    return $stmt->execute([$text_id]);
}

// 删除存储的文件
function deleteStoredFile($pdo, $file_id) {
    $stmt = $pdo->prepare("SELECT * FROM stored_files WHERE id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($file && file_exists($file['file_path'])) {
        unlink($file['file_path']);
    }
    
    $stmt = $pdo->prepare("DELETE FROM stored_files WHERE id = ?");
    return $stmt->execute([$file_id]);
}

// 显示消息
function showMessage($message, $type = 'info') {
    return "<div class='message $type'>$message</div>";
}

// 重定向
function redirect($url) {
    header("Location: $url");
    exit();
}

// 检查是否为管理员
function isAdmin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// 格式化文件大小
function formatFileSize($bytes) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

// 生成唯一的存储柜ID
function generateUniqueStorageId($pdo) {
    do {
        $storage_id = generateStorageId();
    } while (storageExists($pdo, $storage_id));
    
    return $storage_id;
}

// 检查存储柜是否需要设置二级密码
function needsPassword2Setup($pdo, $storage_id) {
    $stmt = $pdo->prepare("SELECT password2_set FROM main_storages WHERE storage_id = ?");
    $stmt->execute([$storage_id]);
    $result = $stmt->fetch();
    return $result ? !$result['password2_set'] : true;
}

// 设置二级密码
function setPassword2($pdo, $storage_id, $password2) {
    $stmt = $pdo->prepare("UPDATE main_storages SET password2 = ?, password2_set = TRUE, password2_set_at = NOW() WHERE storage_id = ?");
    return $stmt->execute([password_hash($password2, PASSWORD_DEFAULT), $storage_id]);
}

// 验证存储柜密码
function validateStoragePasswords($pdo, $storage_id, $password1, $password2 = null) {
    $stmt = $pdo->prepare("SELECT password1, password2, password2_set FROM main_storages WHERE storage_id = ?");
    $stmt->execute([$storage_id]);
    $storage = $stmt->fetch();
    
    if (!$storage) {
        return ['valid' => false, 'message' => '存储柜不存在'];
    }
    
    // 验证一级密码
    if (!password_verify($password1, $storage['password1'])) {
        return ['valid' => false, 'message' => '一级密码错误'];
    }
    
    // 如果二级密码未设置，返回需要设置
    if (!$storage['password2_set']) {
        return ['valid' => true, 'needs_password2_setup' => true, 'message' => '需要设置二级密码'];
    }
    
    // 如果提供了二级密码，验证它
    if ($password2 !== null) {
        if (!password_verify($password2, $storage['password2'])) {
            return ['valid' => false, 'message' => '二级密码错误'];
        }
    }
    
    return ['valid' => true, 'needs_password2_setup' => false];
}

// 检查存储柜二级密码是否已设置
function hasPassword2Set($pdo, $storage_id) {
    $stmt = $pdo->prepare("SELECT password2_set FROM main_storages WHERE storage_id = ?");
    $stmt->execute([$storage_id]);
    $result = $stmt->fetch();
    return $result ? $result['password2_set'] : false;
}

// ======== 子存储柜密码管理功能 ========

// 设置子存储柜密码
function setSubStoragePassword($pdo, $sub_storage_id, $password, $hint = null) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE sub_storages SET password_hash = ?, password_hint = ? WHERE id = ?");
    return $stmt->execute([$password_hash, $hint, $sub_storage_id]);
}

// 验证子存储柜密码
function verifySubStoragePassword($pdo, $sub_storage_id, $password) {
    $stmt = $pdo->prepare("SELECT password_hash FROM sub_storages WHERE id = ?");
    $stmt->execute([$sub_storage_id]);
    $result = $stmt->fetch();
    
    if (!$result || !$result['password_hash']) {
        return true; // 没有设置密码，直接允许访问
    }
    
    return password_verify($password, $result['password_hash']);
}

// 检查子存储柜是否设置了密码
function hasSubStoragePassword($pdo, $sub_storage_id) {
    $stmt = $pdo->prepare("SELECT password_hash FROM sub_storages WHERE id = ?");
    $stmt->execute([$sub_storage_id]);
    $result = $stmt->fetch();
    
    return $result && !empty($result['password_hash']);
}

// 获取子存储柜密码提示
function getSubStoragePasswordHint($pdo, $sub_storage_id) {
    $stmt = $pdo->prepare("SELECT password_hint FROM sub_storages WHERE id = ?");
    $stmt->execute([$sub_storage_id]);
    $result = $stmt->fetch();
    
    return $result ? $result['password_hint'] : null;
}

// 移除子存储柜密码
function removeSubStoragePassword($pdo, $sub_storage_id) {
    $stmt = $pdo->prepare("UPDATE sub_storages SET password_hash = NULL, password_hint = NULL WHERE id = ?");
    return $stmt->execute([$sub_storage_id]);
}

// 获取子存储柜详细信息（包括密码状态）
function getSubStorageDetails($pdo, $sub_storage_id) {
    $stmt = $pdo->prepare("SELECT *, (password_hash IS NOT NULL) as has_password FROM sub_storages WHERE id = ?");
    $stmt->execute([$sub_storage_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 设置子存储柜体积限制
function setSubStorageVolumeLimit($pdo, $sub_storage_id, $volume_limit) {
    $stmt = $pdo->prepare("UPDATE sub_storages SET volume_limit = ? WHERE id = ?");
    return $stmt->execute([$volume_limit, $sub_storage_id]);
}

// 获取子存储柜体积信息
function getSubStorageVolumeInfo($pdo, $sub_storage_id) {
    $stmt = $pdo->prepare("SELECT volume_limit, current_volume FROM sub_storages WHERE id = ?");
    $stmt->execute([$sub_storage_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// 检查是否可以存储文件（体积限制检查）
function canStoreFile($pdo, $sub_storage_id, $file_size) {
    $volume_info = getSubStorageVolumeInfo($pdo, $sub_storage_id);
    if (!$volume_info) {
        return false;
    }
    
    $available_space = $volume_info['volume_limit'] - $volume_info['current_volume'];
    return $file_size <= $available_space;
}

// 更新子存储柜已使用体积
function updateSubStorageVolume($pdo, $sub_storage_id) {
    $stmt = $pdo->prepare("
        UPDATE sub_storages 
        SET current_volume = (
            SELECT COALESCE(SUM(file_size), 0) 
            FROM stored_files 
            WHERE sub_storage_id = ?
        ) 
        WHERE id = ?
    ");
    return $stmt->execute([$sub_storage_id, $sub_storage_id]);
}

// 获取体积使用百分比
function getVolumeUsagePercentage($pdo, $sub_storage_id) {
    $volume_info = getSubStorageVolumeInfo($pdo, $sub_storage_id);
    if (!$volume_info || $volume_info['volume_limit'] == 0) {
        return 0;
    }
    
    return round(($volume_info['current_volume'] / $volume_info['volume_limit']) * 100, 2);
}

// 获取系统设置
function getSystemSetting($pdo, $setting_key, $default_value = null) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$setting_key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default_value;
}

// 检查体积警告状态
function isVolumeWarning($pdo, $sub_storage_id) {
    $percentage = getVolumeUsagePercentage($pdo, $sub_storage_id);
    $warning_threshold = getSystemSetting($pdo, 'volume_warning_threshold', 80);
    return $percentage >= $warning_threshold;
}

// 获取所有子存储柜的体积信息（用于管理界面）
function getAllSubStoragesVolumeInfo($pdo, $main_storage_id) {
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            sub_number, 
            description,
            volume_limit,
            current_volume,
            ROUND((current_volume / volume_limit) * 100, 2) as usage_percentage
        FROM sub_storages 
        WHERE main_storage_id = ? 
        ORDER BY sub_number
    ");
    $stmt->execute([$main_storage_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 记录操作日志
function logOperation($pdo, $storage_id, $operation_type, $description, $ip_address = null, $user_agent = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO operation_logs (storage_id, operation_type, description, ip_address, user_agent, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([
            $storage_id, 
            $operation_type, 
            $description, 
            $ip_address ?: $_SERVER['REMOTE_ADDR'] ?? null,
            $user_agent ?: $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        // 静默处理日志记录错误，不影响主要功能
        error_log("Failed to log operation: " . $e->getMessage());
        return false;
    }
}
?>
