<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// 检查用户权限
if (!isset($_SESSION['storage_id']) || !isset($_SESSION['action'])) {
    redirect('index.php');
}

$storage_id = $_SESSION['storage_id'];
$storage_data = $_SESSION['storage_data'];
$action = $_SESSION['action'];

// 获取子存储柜列表
$sub_storages = getSubStorages($pdo, $storage_data['id']);

// 分页设置
$items_per_page = 6; // 每页显示6个子存储柜
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_items = count($sub_storages);
$total_pages = ceil($total_items / $items_per_page);
$offset = ($page - 1) * $items_per_page;

// 获取当前页的子存储柜
$current_page_sub_storages = array_slice($sub_storages, $offset, $items_per_page);

// 处理页面重定向，保持当前页
function redirectToCurrentPage($page = null) {
    if ($page === null) {
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    }
    $url = $_SERVER['PHP_SELF'];
    if ($page > 1) {
        $url .= '?page=' . $page;
    }
    header("Location: $url");
    exit();
}

// 处理表单提交
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查权限 - 取东西模式不能进行存储和管理操作
if ($action === 'retrieve' && !isset($_POST['verify_sub_password']) && !isset($_POST['lock_sub_storage'])) {
    $message = showMessage('查看模式下不能进行存储或管理操作！如需管理，请返回首页选择管理模式。', 'error');
    } elseif (isset($_POST['upload_file']) && ($action === 'store' || $action === 'manage')) {
        // 上传文件
        $sub_storage_id = $_POST['sub_storage_id'];
        $description = $_POST['description'] ?? '';
        
        if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            // 检查体积限制
            if (!canStoreFile($pdo, $sub_storage_id, $_FILES['file']['size'])) {
                $volume_info = getSubStorageVolumeInfo($pdo, $sub_storage_id);
                $available_space = $volume_info ? ($volume_info['volume_limit'] - $volume_info['current_volume']) : 0;
                $file_size_text = formatFileSize($_FILES['file']['size']);
                $available_space_text = formatFileSize($available_space);
                $message = showMessage("文件上传失败！可用空间不足。文件大小: {$file_size_text}，可用空间: {$available_space_text}", 'error');
            } elseif (storeFile($pdo, $storage_data['id'], $sub_storage_id, $_FILES['file'], $description)) {
                $message = showMessage('文件上传成功！', 'success');
            } else {
                $message = showMessage('文件上传失败！', 'error');
            }
        } else {
            $message = showMessage('请选择要上传的文件！', 'error');
        }
    } elseif (isset($_POST['save_text']) && ($action === 'store' || $action === 'manage')) {
        // 保存文本
        $sub_storage_id = $_POST['sub_storage_id'];
        $title = trim($_POST['title']);
        $content = $_POST['content'];
        $content_type = $_POST['content_type'];
        
        if (!empty($title) && !empty($content)) {
            if (storeText($pdo, $storage_data['id'], $sub_storage_id, $title, $content, $content_type)) {
                $message = showMessage('文本保存成功！', 'success');
            } else {
                $message = showMessage('文本保存失败！', 'error');
            }
        } else {
            $message = showMessage('标题和内容不能为空！', 'error');
        }
    } elseif (isset($_POST['add_sub_storage']) && ($action === 'store' || $action === 'manage')) {
        // 添加子存储柜
        $current_count = count($sub_storages);
        if ($current_count < $storage_data['max_sub_storages']) {
            $new_sub_number = $current_count + 1;
            if (createSubStorage($pdo, $storage_data['id'], $new_sub_number)) {
                $message = showMessage('子存储柜创建成功！', 'success');
                $sub_storages = getSubStorages($pdo, $storage_data['id']); // 刷新列表
            } else {
                $message = showMessage('子存储柜创建失败！', 'error');
            }
        } else {
            $message = showMessage('已达到最大子存储柜数量限制！', 'error');
        }
    } elseif (isset($_POST['delete_sub_storage']) && $action === 'manage') {
        // 删除子存储柜
        $sub_storage_id = $_POST['sub_storage_id'];
        if (deleteSubStorage($pdo, $sub_storage_id)) {
            $message = showMessage('子存储柜删除成功！', 'success');
            $sub_storages = getSubStorages($pdo, $storage_data['id']); // 刷新列表
        } else {
            $message = showMessage('子存储柜删除失败！', 'error');
        }
    } elseif (isset($_POST['delete_file'])) {
        // 删除文件
        $file_id = $_POST['file_id'];
        if (deleteStoredFile($pdo, $file_id)) {
            $message = showMessage('文件删除成功！', 'success');
        } else {
            $message = showMessage('文件删除失败！', 'error');
        }
    } elseif (isset($_POST['delete_text'])) {
        // 删除文本
        $text_id = $_POST['text_id'];
        if (deleteStoredText($pdo, $text_id)) {
            $message = showMessage('文本删除成功！', 'success');
        } else {
            $message = showMessage('文本删除失败！', 'error');
        }
    } elseif (isset($_POST['set_sub_password']) && ($action === 'store' || $action === 'manage')) {
        // 设置子存储柜密码
        $sub_storage_id = $_POST['sub_storage_id'];
        $password = $_POST['sub_password'];
        $hint = $_POST['password_hint'] ?? '';
        
        if (!empty($password)) {
            if (setSubStoragePassword($pdo, $sub_storage_id, $password, $hint)) {
                $message = showMessage('子存储柜密码设置成功！', 'success');
            } else {
                $message = showMessage('密码设置失败！', 'error');
            }
        } else {
            $message = showMessage('密码不能为空！', 'error');
        }
    } elseif (isset($_POST['remove_sub_password']) && ($action === 'store' || $action === 'manage')) {
        // 移除子存储柜密码
        $sub_storage_id = $_POST['sub_storage_id'];
        if (removeSubStoragePassword($pdo, $sub_storage_id)) {
            $message = showMessage('子存储柜密码已移除！', 'success');
        } else {
            $message = showMessage('密码移除失败！', 'error');
        }
    } elseif (isset($_POST['verify_sub_password'])) {
        // 验证子存储柜密码
        $sub_storage_id = $_POST['sub_storage_id'];
        $password = $_POST['sub_password'];
        
        if (verifySubStoragePassword($pdo, $sub_storage_id, $password)) {
            $_SESSION['unlocked_sub_storages'][$sub_storage_id] = true;
            $message = showMessage('子存储柜解锁成功！', 'success');
        } else {
            $message = showMessage('密码错误！', 'error');
        }
    } elseif (isset($_POST['lock_sub_storage'])) {
        // 锁定子存储柜
        $sub_storage_id = $_POST['sub_storage_id'];
        unset($_SESSION['unlocked_sub_storages'][$sub_storage_id]);
        $message = showMessage('子存储柜已锁定！', 'success');
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>存储柜管理 - 数字存储系统</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .storage-interface {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .sub-storages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .sub-storages-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .sub-storage {
                padding: 12px;
            }
        }
        
        .sub-storage {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            background: #f7fafc;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .sub-storage:hover {
            border-color: #3182ce;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .sub-storage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .toggle-details {
            background: #4299e1;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 18px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.3s ease;
        }
        
        .toggle-details:hover {
            background: #3182ce;
            transform: translateY(-1px);
        }
        
        .upload-form {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
        }
        
        .upload-form-header h5 {
            margin: 0 0 12px 0;
            color: #495057;
            text-align: center;
            font-size: 0.95em;
        }
        
        .storage-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: #e9ecef;
            color: #6c757d;
            cursor: pointer;
            border-radius: 10px 10px 0 0;
            transition: all 0.3s ease;
        }
        
        .tab-btn.active {
            background: #007bff;
            color: white;
        }
        
        .storage-tab-content {
            display: none;
        }
        
        .storage-tab-content.active {
            display: block;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
        }
        
        /* 子存储柜操作弹窗样式 */
        .sub-storage-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .sub-storage-modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        
        .sub-storage-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .sub-storage-modal-close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
            line-height: 1;
        }
        
        .sub-storage-modal-close:hover {
            color: #000;
            transform: scale(1.1);
        }
        
        .modal-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 25px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .modal-tab-btn {
            padding: 12px 25px;
            border: none;
            background: #e9ecef;
            color: #6c757d;
            cursor: pointer;
            border-radius: 10px 10px 0 0;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }
        
        .modal-tab-btn.active {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            transform: translateY(-2px);
        }
        
        .modal-tab-content {
            display: none;
        }
        
        .modal-tab-content.active {
            display: block;
        }
        
        .modal-form-group {
            margin-bottom: 20px;
        }
        
        .modal-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        
        .modal-form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ced4da;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .modal-form-control:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
        }
        
        .modal-textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .modal-submit-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .modal-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .sub-storage-actions {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin: 15px 0;
            padding: 10px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .sub-storage-actions .btn {
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 500;
            border-radius: 15px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            min-width: 80px;
            white-space: nowrap;
        }
        
        .sub-storage-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
        }
        
        /* 移动设备响应式按钮 */
        @media (max-width: 768px) {
            .sub-storage-actions {
                flex-direction: column;
                gap: 6px;
            }
            
            .sub-storage-actions .btn {
                width: 100%;
                min-width: auto;
            }
        }
        
        /* 密码保护相关样式 */
        .password-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            padding: 8px 12px;
            border-radius: 8px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .status-locked {
            color: #dc3545;
            font-weight: 500;
        }
        
        .status-unlocked {
            color: #28a745;
            font-weight: 500;
        }
        
        .password-input-area {
            margin: 15px 0;
            padding: 20px;
            background: #fff3cd;
            border: 2px solid #ffeaa7;
            border-radius: 10px;
        }
        
        .password-hint {
            background: #e3f2fd;
            color: #1976d2;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .password-input-group {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .password-input-group input {
            flex: 1;
        }
        
        .password-form {
            margin: 0;
        }
        
        .locked-summary {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            margin: 15px 0;
        }
        
        .password-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .password-actions .btn {
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        /* 布局控制样式 */
        .layout-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin: 20px 0;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
        
        /* 不同布局样式 */
        .sub-storages-grid.layout-list {
            grid-template-columns: 1fr;
            gap: 15px;
        }
        
        .sub-storages-grid.layout-compact {
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .sub-storages-grid.layout-wide {
            grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
            gap: 30px;
        }
        
        /* 紧凑布局下的子存储柜样式 */
        .layout-compact .sub-storage {
            padding: 15px;
        }
        
        .layout-compact .sub-storage-header h4 {
            font-size: 1em;
        }
        
        .layout-compact .content-summary {
            font-size: 0.9em;
        }
        
        /* 列表布局下的子存储柜样式 */
        .layout-list .sub-storage {
            display: flex;
            align-items: center;
            padding: 15px 20px;
        }
        
        .layout-list .sub-storage-header {
            flex: 1;
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .layout-list .content-summary {
            margin: 0 20px;
        }
        
        .layout-list .sub-storage-actions {
            margin: 0;
            padding: 0;
        }
        
        /* 优化的内容显示 */
        .content-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            margin: 8px 0;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .content-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .content-item-info {
            flex: 1;
        }
        
        .content-item-title {
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 4px;
        }
        
        .content-item-meta {
            font-size: 0.85em;
            color: #718096;
        }
        
        .content-item-actions {
            display: flex;
            gap: 8px;
        }
        
        .content-type-badge {
            display: inline-block;
            padding: 2px 8px;
            background: #e2e8f0;
            color: #4a5568;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 500;
            margin-left: 8px;
        }
        
        /* 内容区域样式 */
        .content-section {
            margin: 20px 0;
        }
        
        .section-title {
            color: #2d3748;
            margin: 0 0 15px 0;
            padding: 10px 15px;
            background: linear-gradient(135deg, #edf2f7, #e2e8f0);
            border-radius: 8px;
            border-left: 4px solid #4299e1;
            font-size: 1.1em;
            font-weight: 600;
        }
        
        .content-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .text-preview-mini {
            font-size: 0.85em;
            color: #718096;
            margin-top: 5px;
            padding: 8px;
            background: #f7fafc;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            border-left: 3px solid #e2e8f0;
        }
        
        /* 改进操作按钮样式 */
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85em;
            border-radius: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary.btn-sm {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
        }
        
        .btn-primary.btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 153, 225, 0.4);
        }
        
        .btn-danger.btn-sm {
            background: linear-gradient(135deg, #f56565, #e53e3e);
            color: white;
        }
        
        .btn-danger.btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(245, 101, 101, 0.4);
        }
        
        .sub-storage h4 {
            color: #2d3748;
            margin: 0;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sub-storage-number {
            background: #3182ce;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        
        .content-summary {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            font-size: 12px;
            color: #666;
        }
        
        .content-count {
            background: white;
            padding: 3px 6px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            font-size: 11px;
        }
        
        .sub-storage-details {
            transition: max-height 0.3s ease;
            overflow: hidden;
        }
        
        .sub-storage-details.collapsed {
            max-height: 0;
        }
        
        .sub-storage-details.expanded {
            max-height: 1000px;
        }
        
        .toggle-details {
            background: none;
            border: none;
            color: #3182ce;
            cursor: pointer;
            font-size: 12px;
            padding: 5px 10px;
            border-radius: 15px;
            transition: background 0.3s;
        }
        
        .toggle-details:hover {
            background: #e6f3ff;
        }
        
        .sub-storage-actions {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .file-list {
            margin-top: 15px;
        }
        
        .file-item {
            background: white;
            padding: 10px;
            margin: 5px 0;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .upload-form {
            background: #e6fffa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .storage-tabs {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            border-bottom-color: #3182ce;
            color: #3182ce;
            font-weight: bold;
        }
        
        .storage-tab-content {
            display: none;
        }
        
        .storage-tab-content.active {
            display: block;
        }
        
        .content-type-badge {
            background: #4a5568;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 10px;
        }
        
        .text-preview {
            margin-top: 10px;
        }
        
        .text-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .text-modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 800px;
            max-height: 80%;
            overflow-y: auto;
        }
        
        .text-modal-close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .text-modal-close:hover {
            color: black;
        }
        
        .management-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .management-section h3 {
            margin: 0 0 15px 0;
            color: white;
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .management-section p {
            margin: 10px 0;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .management-section .btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .management-section .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }
        
        .storage-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            backdrop-filter: blur(10px);
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5em;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.8;
        }
        
        /* 标题下方按钮样式 */
        .header-nav-buttons a {
            transition: all 0.3s ease;
        }
        
        .header-nav-buttons a:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .header-nav-buttons .btn:first-child:hover {
            background: rgba(255,255,255,0.25) !important;
            border-color: rgba(255,255,255,0.5) !important;
        }
        
        .header-nav-buttons .btn:last-child:hover {
            background: rgba(220,53,69,1) !important;
            border-color: rgba(220,53,69,1) !important;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 顶部导航栏 -->
        <div style="padding: 10px 0; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2);">
            <!-- 移除了按钮，只保留分隔线 -->
        </div>
        
        <h1>存储柜: <?php echo htmlspecialchars($storage_data['storage_name']); ?></h1>
        
        <p style="text-align: center; color: white; margin-bottom: 20px;">
            当前操作模式: 
            <?php 
                switch($action) {
                    case 'store':
                    case 'manage': 
                        echo '<span style="background: #38a169; padding: 5px 15px; border-radius: 20px;">� 存取和管理模式</span>';
                        echo '<br><small style="margin-top: 5px; display: inline-block;">已验证二级密码，可以存取文件、保存文本和管理存储柜</small>';
                        break;
                    case 'retrieve': 
                        echo '<span style="background: #3182ce; padding: 5px 15px; border-radius: 20px;">📤 取东西模式</span>';
                        echo '<br><small style="margin-top: 5px; display: inline-block;">仅验证一级密码，只能查看和下载内容</small>';
                        break;
                }
            ?>
        </p>
        
        <!-- 将按钮移到操作模式说明下面 -->
        <div style="text-align: center; margin: 15px 0 25px 0;">
            <div class="header-nav-buttons" style="display: flex; justify-content: center; gap: 15px;">
                <a href="index.php" class="btn" style="background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3); padding: 10px 20px; font-size: 14px; border-radius: 25px; text-decoration: none;">返回主页</a>
                <a href="logout.php" class="btn btn-primary" style="background: rgba(220,53,69,0.8); color: white; border: 1px solid rgba(220,53,69,0.9); padding: 10px 20px; font-size: 14px; border-radius: 25px; text-decoration: none;">退出登录</a>
            </div>
        </div>
        
        <div class="storage-interface">
            <!-- 存储柜管理操作 (置顶) -->
            <?php if ($action === 'store' || $action === 'manage'): ?>
                <div class="management-section">
                    <h3>
                        ⚙️ 存储管理中心
                    </h3>
                    
                    <div class="storage-stats">
                        <div class="stat-item">
                            <span class="stat-number"><?php echo count($sub_storages); ?></span>
                            <span class="stat-label">子储物柜</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $storage_data['max_sub_storages']; ?></span>
                            <span class="stat-label">最大容量</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo number_format((count($sub_storages) / $storage_data['max_sub_storages']) * 100, 1); ?>%</span>
                            <span class="stat-label">使用率</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-number"><?php echo $storage_data['max_sub_storages'] - count($sub_storages); ?></span>
                            <span class="stat-label">剩余名额</span>
                        </div>
                    </div>
                    
                    <!-- 排列方式选择器 -->
                    <div class="layout-controls">
                        <label style="color: rgba(255, 255, 255, 0.9); margin-right: 10px;">📋 排列方式:</label>
                        <select id="layoutSelector" onchange="changeLayout()" style="padding: 5px 10px; border-radius: 5px; border: none;">
                            <option value="grid">🔲 网格布局</option>
                            <option value="list">📄 列表布局</option>
                            <option value="compact">📦 紧凑布局</option>
                            <option value="wide">📐 宽屏布局</option>
                        </select>
                        
                        <label style="color: rgba(255, 255, 255, 0.9); margin-left: 20px; margin-right: 10px;">🔢 排序方式:</label>
                        <select id="sortSelector" onchange="sortSubStorages()" style="padding: 5px 10px; border-radius: 5px; border: none;">
                            <option value="number">按编号排序</option>
                            <option value="content">按内容数量</option>
                            <option value="date">按创建时间</option>
                            <option value="name">按名称排序</option>
                        </select>
                    </div>
                    
                    <?php if (count($sub_storages) < $storage_data['max_sub_storages']): ?>
                        <form method="post" style="display: inline;">
                            <button type="submit" name="add_sub_storage" class="btn btn-primary">
                                ➕ 添加子存储柜
                            </button>
                        </form>
                    <?php else: ?>
                        <p style="color: rgba(255, 255, 255, 0.8); font-style: italic; margin: 10px 0;">
                            ⚠️ 已达到最大子存储柜数量限制
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <!-- 子存储柜列表 -->
            <?php if (empty($sub_storages)): ?>
                <div class="message info">
                    暂无子存储柜。
                    <?php if ($action === 'store' || $action === 'manage'): ?>
                        请先创建子存储柜。
                    <?php elseif ($action === 'retrieve'): ?>
                        当前为取东西模式，无法创建子存储柜。
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="sub-storages-grid">
                    <?php foreach ($current_page_sub_storages as $sub_storage): ?>
                        <?php
                        // 检查子存储柜是否有密码保护
                        $has_password = hasSubStoragePassword($pdo, $sub_storage['id']);
                        $is_unlocked = !$has_password || isset($_SESSION['unlocked_sub_storages'][$sub_storage['id']]);
                        $status_class = $has_password ? ($is_unlocked ? 'unlocked' : 'locked') : '';
                        ?>
                        <div class="sub-storage <?php echo $status_class; ?>" data-sub-number="<?php echo $sub_storage['sub_number']; ?>" data-created="<?php echo $sub_storage['created_at']; ?>">
                            <div class="sub-storage-header">
                                <h4>
                                    <span class="sub-storage-number"><?php echo $sub_storage['sub_number']; ?></span>
                                    子存储柜 #<?php echo $sub_storage['sub_number']; ?>
                                </h4>
                                <div class="header-actions">
                                    <button class="toggle-details" onclick="toggleDetails(<?php echo $sub_storage['id']; ?>)">
                                        <span id="toggle-text-<?php echo $sub_storage['id']; ?>">展开详情</span>
                                    </button>
                                    <?php if ($action === 'store' || $action === 'manage'): ?>
                                        <button class="btn btn-danger btn-sm" onclick="deleteSubStorage(<?php echo $sub_storage['id']; ?>)">
                                            🗑️ 删除
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($sub_storage['description']): ?>
                                <div style="margin-bottom: 15px; color: #666; font-style: italic;">
                                    <?php echo htmlspecialchars($sub_storage['description']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- 密码保护状态显示 -->
                            <?php if ($has_password): ?>
                                <div class="password-status">
                                    <?php if ($is_unlocked): ?>
                                        <span class="status-unlocked">🔓 已解锁</span>
                                        <?php if ($action === 'store' || $action === 'manage'): ?>
                                            <button class="btn btn-sm btn-secondary" onclick="lockSubStorage(<?php echo $sub_storage['id']; ?>)">🔒 锁定</button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="status-locked">🔒 需要密码</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($is_unlocked): ?>
                                <!-- 内容统计 -->
                                <?php 
                                    $files = getStoredFiles($pdo, $sub_storage['id']); 
                                    $texts = getStoredTexts($pdo, $sub_storage['id']); 
                                    $file_count = count($files);
                                    $text_count = count($texts);
                                    
                                    // 获取体积信息
                                    $volume_info = getSubStorageVolumeInfo($pdo, $sub_storage['id']);
                                    $usage_percentage = getVolumeUsagePercentage($pdo, $sub_storage['id']);
                                    $is_warning = isVolumeWarning($pdo, $sub_storage['id']);
                                    $available_space = $volume_info ? ($volume_info['volume_limit'] - $volume_info['current_volume']) : 0;
                                ?>
                                
                                <div class="content-summary">
                                    <span class="content-count">📁 <?php echo $file_count; ?> 个文件</span>
                                    <span class="content-count">📝 <?php echo $text_count; ?> 个文本</span>
                                </div>
                                
                                <!-- 体积使用情况 -->
                                <div class="volume-info">
                                    <div class="volume-bar-container">
                                        <div class="volume-bar <?php echo $is_warning ? 'warning' : ''; ?>">
                                            <div class="volume-progress" style="width: <?php echo $usage_percentage; ?>%"></div>
                                        </div>
                                        <div class="volume-text">
                                            <span class="volume-used"><?php echo $volume_info ? formatFileSize($volume_info['current_volume']) : '0 B'; ?></span>
                                            <span class="volume-separator">/</span>
                                            <span class="volume-total"><?php echo $volume_info ? formatFileSize($volume_info['volume_limit']) : '0 B'; ?></span>
                                            <span class="volume-percentage">(<?php echo $usage_percentage; ?>%)</span>
                                        </div>
                                    </div>
                                    <div class="volume-available">
                                        可用空间: <?php echo formatFileSize($available_space); ?>
                                    </div>
                                    <div class="volume-notice">
                                        <small style="color: #666; font-size: 0.8em;">📝 体积限制由管理员统一管理</small>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- 密码输入区域 -->
                                <div class="password-input-area">
                                    <form method="post" class="password-form">
                                        <input type="hidden" name="sub_storage_id" value="<?php echo $sub_storage['id']; ?>">
                                        
                                        <?php 
                                        $hint = getSubStoragePasswordHint($pdo, $sub_storage['id']);
                                        if ($hint): 
                                        ?>
                                            <div class="password-hint">
                                                💡 提示: <?php echo htmlspecialchars($hint); ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="password-input-group">
                                            <input type="password" name="sub_password" placeholder="请输入子存储柜密码" required class="form-control">
                                            <button type="submit" name="verify_sub_password" class="btn btn-primary">🔓 解锁</button>
                                        </div>
                                    </form>
                                </div>
                                
                                <div class="locked-summary">
                                    <p style="color: #666; text-align: center; padding: 20px;">
                                        🔒 此子存储柜受密码保护，请输入密码查看内容
                                    </p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($is_unlocked): ?>
                            <!-- 详细内容 (可收起) - 仅在解锁状态下显示 -->
                            <div class="sub-storage-details collapsed" id="details-<?php echo $sub_storage['id']; ?>">
                            
                            <!-- 显示存储的文件 -->
                            <?php if (!empty($files)): ?>
                                <div class="content-section">
                                    <h5 class="section-title">📁 存储的文件 (<?php echo count($files); ?>)</h5>
                                    <div class="content-list">
                                        <?php foreach ($files as $file): ?>
                                            <div class="content-item">
                                                <div class="content-item-info">
                                                    <div class="content-item-title">
                                                        📎 <?php echo htmlspecialchars($file['original_name']); ?>
                                                    </div>
                                                    <div class="content-item-meta">
                                                        📏 <?php echo formatFileSize($file['file_size']); ?> • 
                                                        🕐 <?php echo date('m-d H:i', strtotime($file['created_at'])); ?>
                                                        <?php if ($file['description']): ?>
                                                            • 💬 <?php echo htmlspecialchars($file['description']); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="content-item-actions">
                                                    <a href="download.php?file_id=<?php echo $file['id']; ?>" class="btn btn-primary btn-sm">📥 下载</a>
                                                    <?php if ($action === 'store' || $action === 'manage'): ?>
                                                        <form method="post" style="display: inline;">
                                                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                                            <button type="submit" name="delete_file" class="btn btn-danger btn-sm" 
                                                                    onclick="return confirm('确定要删除这个文件吗？')">🗑️</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        
                        <!-- 显示存储的文本 -->
                        <?php if (!empty($texts)): ?>
                            <div class="content-section">
                                <h5 class="section-title">📝 存储的文本 (<?php echo count($texts); ?>)</h5>
                                <div class="content-list">
                                    <?php foreach ($texts as $text): ?>
                                        <div class="content-item">
                                            <div class="content-item-info">
                                                <div class="content-item-title">
                                                    📄 <?php echo htmlspecialchars($text['title']); ?>
                                                    <span class="content-type-badge"><?php echo strtoupper($text['content_type']); ?></span>
                                                </div>
                                                <div class="content-item-meta">
                                                    📊 <?php echo mb_strlen($text['content']); ?> 字符 • 
                                                    🕐 <?php echo date('m-d H:i', strtotime($text['created_at'])); ?>
                                                </div>
                                                <div class="text-preview-mini">
                                                    <?php 
                                                    $preview = mb_substr(strip_tags($text['content']), 0, 80);
                                                    echo htmlspecialchars($preview) . (mb_strlen($text['content']) > 80 ? '...' : '');
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="content-item-actions">
                                                <button onclick="viewFullText(<?php echo $text['id']; ?>, '<?php echo htmlspecialchars($text['title']); ?>', '<?php echo $text['content_type']; ?>')" class="btn btn-primary btn-sm">👀 查看</button>
                                                <?php if ($action === 'store' || $action === 'manage'): ?>
                                                    <form method="post" style="display: inline;">
                                                        <input type="hidden" name="text_id" value="<?php echo $text['id']; ?>">
                                                        <button type="submit" name="delete_text" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('确定要删除这个文本吗？')">🗑️</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 空内容提示 -->
                        <?php if (empty($files) && empty($texts)): ?>
                            <div style="text-align: center; color: #666; padding: 20px; background: #f9f9f9; border-radius: 5px; margin: 10px 0;">
                                <?php if ($action === 'retrieve'): ?>
                                    📭 此子存储柜暂无内容
                                <?php elseif ($action === 'store' || $action === 'manage'): ?>
                                    📦 此子存储柜暂无内容，您可以上传文件或保存文本
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- 操作按钮 (仅存取和管理模式) -->
                        <?php if ($action === 'store' || $action === 'manage'): ?>
                            <div class="sub-storage-actions">
                                <?php if ($is_unlocked): ?>
                                    <button type="button" class="btn btn-primary" onclick="openSubStorageModal(<?php echo $sub_storage['id']; ?>, '<?php echo htmlspecialchars($sub_storage['sub_number']); ?>')">
                                        📦 添加内容
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-secondary" onclick="openPasswordModal(<?php echo $sub_storage['id']; ?>, '<?php echo htmlspecialchars($sub_storage['sub_number']); ?>', <?php echo $has_password ? 'true' : 'false'; ?>)">
                                    <?php echo $has_password ? '🔑 管理密码' : '🔒 设置密码'; ?>
                                </button>
                                <!-- 体积限制设置按钮已移除，用户只能查看体积信息 -->
                            </div>
                        <?php endif; ?>
                        
                            </div>
                            <?php endif; // 结束解锁检查 ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- 分页导航 -->
                <?php if ($total_pages > 1): ?>
                <div class="sub-storage-pagination">
                    <div class="pagination-nav">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>" class="page-btn prev-btn">
                                ← 上一页
                            </a>
                        <?php endif; ?>
                        
                        <div class="page-numbers">
                            <?php
                            // 显示所有页码
                            for ($i = 1; $i <= $total_pages; $i++): ?>
                                <a href="?page=<?php echo $i; ?>" 
                                   class="page-number <?php echo $i == $page ? 'current' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>" class="page-btn next-btn">
                                下一页 →
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="page-info">
                        显示 <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_items); ?> 项，共 <?php echo $total_items; ?> 个子存储柜
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 文本查看模态框 -->
    <div id="textModal" class="text-modal">
        <div class="text-modal-content">
            <span class="text-modal-close" onclick="closeTextModal()">&times;</span>
            <h2 id="textModalTitle"></h2>
            <div id="textModalContent"></div>
        </div>
    </div>

    <!-- 子存储柜操作弹窗 -->
    <div id="subStorageModal" class="sub-storage-modal">
        <div class="sub-storage-modal-content">
            <div class="sub-storage-modal-header">
                <h2 id="subStorageModalTitle">📦 子存储柜操作</h2>
                <button class="sub-storage-modal-close" onclick="closeSubStorageModal()">&times;</button>
            </div>
            
            <div class="modal-tabs">
                <button type="button" class="modal-tab-btn active" onclick="switchModalTab('file')">📁 上传文件</button>
                <button type="button" class="modal-tab-btn" onclick="switchModalTab('text')">📝 保存文本</button>
            </div>
            
            <!-- 文件上传标签页 -->
            <div id="modal-file-tab" class="modal-tab-content active">
                <form id="fileUploadForm" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="sub_storage_id" id="fileSubStorageId" value="">
                    
                    <div class="modal-form-group">
                        <label>📎 选择文件:</label>
                        <input type="file" name="file" required class="modal-form-control">
                    </div>
                    
                    <div class="modal-form-group">
                        <label>📝 文件描述:</label>
                        <input type="text" name="description" placeholder="可选，为文件添加描述" class="modal-form-control">
                    </div>
                    
                    <button type="submit" name="upload_file" class="modal-submit-btn">
                        📁 上传文件
                    </button>
                </form>
            </div>
            
            <!-- 文本保存标签页 -->
            <div id="modal-text-tab" class="modal-tab-content">
                <form id="textSaveForm" method="post">
                    <input type="hidden" name="sub_storage_id" id="textSubStorageId" value="">
                    
                    <div class="modal-form-group">
                        <label>📄 文本标题:</label>
                        <input type="text" name="title" required placeholder="请输入文本标题" class="modal-form-control">
                    </div>
                    
                    <div class="modal-form-group">
                        <label>🎨 文本类型:</label>
                        <select name="content_type" required class="modal-form-control">
                            <option value="plain">📄 纯文本</option>
                            <option value="markdown">📋 Markdown</option>
                            <option value="html">🌐 HTML</option>
                        </select>
                    </div>
                    
                    <div class="modal-form-group">
                        <label>✍️ 文本内容:</label>
                        <textarea name="content" rows="10" required placeholder="请输入文本内容..." class="modal-form-control modal-textarea"></textarea>
                    </div>
                    
                    <button type="submit" name="save_text" class="modal-submit-btn">
                        📝 保存文本
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- 子存储柜密码管理弹窗 -->
    <div id="passwordModal" class="sub-storage-modal">
        <div class="sub-storage-modal-content">
            <div class="sub-storage-modal-header">
                <h2 id="passwordModalTitle">🔑 密码管理</h2>
                <button class="sub-storage-modal-close" onclick="closePasswordModal()">&times;</button>
            </div>
            
            <div id="passwordModalContent">
                <!-- 设置密码表单 -->
                <div id="setPasswordForm" style="display: none;">
                    <form method="post">
                        <input type="hidden" name="sub_storage_id" id="setPasswordSubStorageId" value="">
                        
                        <div class="modal-form-group">
                            <label>🔒 设置密码:</label>
                            <input type="password" name="sub_password" required placeholder="请输入新密码" class="modal-form-control">
                        </div>
                        
                        <div class="modal-form-group">
                            <label>💡 密码提示 (可选):</label>
                            <input type="text" name="password_hint" placeholder="帮助记忆的提示信息" class="modal-form-control">
                        </div>
                        
                        <button type="submit" name="set_sub_password" class="modal-submit-btn">
                            🔒 设置密码
                        </button>
                    </form>
                </div>
                
                <!-- 管理密码表单 -->
                <div id="managePasswordForm" style="display: none;">
                    <div class="password-actions">
                        <button type="button" class="btn btn-warning" onclick="showChangePasswordForm()">
                            🔄 修改密码
                        </button>
                        <button type="button" class="btn btn-danger" onclick="removePassword()">
                            🗑️ 移除密码
                        </button>
                    </div>
                    
                    <!-- 修改密码表单 -->
                    <div id="changePasswordForm" style="display: none; margin-top: 20px;">
                        <form method="post">
                            <input type="hidden" name="sub_storage_id" id="changePasswordSubStorageId" value="">
                            
                            <div class="modal-form-group">
                                <label>🔒 新密码:</label>
                                <input type="password" name="sub_password" required placeholder="请输入新密码" class="modal-form-control">
                            </div>
                            
                            <div class="modal-form-group">
                                <label>💡 新密码提示 (可选):</label>
                                <input type="text" name="password_hint" placeholder="帮助记忆的提示信息" class="modal-form-control">
                            </div>
                            
                            <button type="submit" name="set_sub_password" class="modal-submit-btn">
                                🔄 修改密码
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 用户端不提供体积限制设置功能，只显示体积信息 -->

    <script>
        // 查看完整文本
        function viewFullText(textId, title, contentType) {
            fetch('get_text_content.php?text_id=' + textId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('textModalTitle').textContent = title;
                        
                        let contentDiv = document.getElementById('textModalContent');
                        if (contentType === 'html') {
                            contentDiv.innerHTML = data.content;
                        } else if (contentType === 'markdown') {
                            // 简单的markdown显示，可以后续改进
                            contentDiv.innerHTML = '<pre style="white-space: pre-wrap; font-family: inherit;">' + 
                                                 data.content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
                        } else {
                            contentDiv.innerHTML = '<pre style="white-space: pre-wrap; font-family: inherit;">' + 
                                                 data.content.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</pre>';
                        }
                        
                        document.getElementById('textModal').style.display = 'block';
                    } else {
                        alert('无法加载文本内容');
                    }
                })
                .catch(error => {
                    alert('加载文本时出错');
                });
        }
        
        // 关闭文本模态框
        function closeTextModal() {
            document.getElementById('textModal').style.display = 'none';
        }

        function deleteSubStorage(subStorageId) {
            if (confirm('确定要删除这个子存储柜吗？所有文件和文本都将被删除！')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_sub_storage';
                input.value = '1';
                form.appendChild(input);

                const subStorageInput = document.createElement('input');
                subStorageInput.type = 'hidden';
                subStorageInput.name = 'sub_storage_id';
                subStorageInput.value = subStorageId;
                form.appendChild(subStorageInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        // 子存储柜弹窗相关函数
        function openSubStorageModal(subStorageId, subStorageNumber) {
            document.getElementById('subStorageModalTitle').textContent = `📦 子存储柜 #${subStorageNumber} - 添加内容`;
            document.getElementById('fileSubStorageId').value = subStorageId;
            document.getElementById('textSubStorageId').value = subStorageId;
            
            // 重置表单
            document.getElementById('fileUploadForm').reset();
            document.getElementById('textSaveForm').reset();
            document.getElementById('fileSubStorageId').value = subStorageId;
            document.getElementById('textSubStorageId').value = subStorageId;
            
            // 默认显示文件上传标签页
            switchModalTab('file');
            
            document.getElementById('subStorageModal').style.display = 'block';
        }

        function closeSubStorageModal() {
            document.getElementById('subStorageModal').style.display = 'none';
        }

        function switchModalTab(tabType) {
            // 移除所有标签页的活动状态
            document.querySelectorAll('.modal-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.modal-tab-content').forEach(content => content.classList.remove('active'));
            
            // 激活选中的标签页
            if (tabType === 'file') {
                document.getElementById('modal-file-tab').classList.add('active');
                document.querySelectorAll('.modal-tab-btn')[0].classList.add('active');
            } else {
                document.getElementById('modal-text-tab').classList.add('active');
                document.querySelectorAll('.modal-tab-btn')[1].classList.add('active');
            }
        }

        // 密码管理相关函数
        function openPasswordModal(subStorageId, subStorageNumber, hasPassword) {
            document.getElementById('passwordModalTitle').textContent = `🔑 子存储柜 #${subStorageNumber} - 密码管理`;
            
            if (hasPassword) {
                // 显示管理密码表单
                document.getElementById('setPasswordForm').style.display = 'none';
                document.getElementById('managePasswordForm').style.display = 'block';
                document.getElementById('changePasswordSubStorageId').value = subStorageId;
            } else {
                // 显示设置密码表单
                document.getElementById('setPasswordForm').style.display = 'block';
                document.getElementById('managePasswordForm').style.display = 'none';
                document.getElementById('setPasswordSubStorageId').value = subStorageId;
            }
            
            // 隐藏修改密码表单
            document.getElementById('changePasswordForm').style.display = 'none';
            
            document.getElementById('passwordModal').style.display = 'block';
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }

        function showChangePasswordForm() {
            document.getElementById('changePasswordForm').style.display = 'block';
        }

        function removePassword() {
            if (confirm('确定要移除此子存储柜的密码保护吗？移除后任何人都可以查看内容。')) {
                const subStorageId = document.getElementById('changePasswordSubStorageId').value;
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_sub_password';
                input.value = '1';
                form.appendChild(input);

                const subStorageInput = document.createElement('input');
                subStorageInput.type = 'hidden';
                subStorageInput.name = 'sub_storage_id';
                subStorageInput.value = subStorageId;
                form.appendChild(subStorageInput);

                document.body.appendChild(form);
                form.submit();
            }
        }

        function lockSubStorage(subStorageId) {
            // 从会话中移除解锁状态
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'lock_sub_storage';
            input.value = '1';
            form.appendChild(input);

            const subStorageInput = document.createElement('input');
            subStorageInput.type = 'hidden';
            subStorageInput.name = 'sub_storage_id';
            subStorageInput.value = subStorageId;
            form.appendChild(subStorageInput);

            document.body.appendChild(form);
            form.submit();
        }
        
        // 切换子存储柜详情显示
        function toggleDetails(subStorageId) {
            const details = document.getElementById('details-' + subStorageId);
            const toggleText = document.getElementById('toggle-text-' + subStorageId);
            
            if (details.classList.contains('collapsed')) {
                details.classList.remove('collapsed');
                details.classList.add('expanded');
                toggleText.textContent = '收起详情';
            } else {
                details.classList.remove('expanded');
                details.classList.add('collapsed');
                toggleText.textContent = '展开详情';
            }
        }

        // 布局和排序功能
        function changeLayout() {
            const layoutSelector = document.getElementById('layoutSelector');
            const grid = document.querySelector('.sub-storages-grid');
            
            // 移除所有布局类
            grid.classList.remove('layout-list', 'layout-compact', 'layout-wide');
            
            // 添加选中的布局类
            if (layoutSelector.value !== 'grid') {
                grid.classList.add('layout-' + layoutSelector.value);
            }
            
            // 保存用户偏好
            localStorage.setItem('subStorageLayout', layoutSelector.value);
        }
        
        function sortSubStorages() {
            const sortSelector = document.getElementById('sortSelector');
            const grid = document.querySelector('.sub-storages-grid');
            const storages = Array.from(grid.children);
            
            storages.sort((a, b) => {
                switch (sortSelector.value) {
                    case 'number':
                        const numA = parseInt(a.querySelector('.sub-storage-number').textContent);
                        const numB = parseInt(b.querySelector('.sub-storage-number').textContent);
                        return numA - numB;
                    
                    case 'content':
                        const contentA = a.querySelectorAll('.content-item').length;
                        const contentB = b.querySelectorAll('.content-item').length;
                        return contentB - contentA; // 降序
                    
                    case 'date':
                        // 这里可以根据创建时间排序，暂时按编号排序
                        const dateNumA = parseInt(a.querySelector('.sub-storage-number').textContent);
                        const dateNumB = parseInt(b.querySelector('.sub-storage-number').textContent);
                        return dateNumB - dateNumA; // 降序
                    
                    case 'name':
                        const nameA = a.querySelector('h4').textContent.toLowerCase();
                        const nameB = b.querySelector('h4').textContent.toLowerCase();
                        return nameA.localeCompare(nameB);
                    
                    default:
                        return 0;
                }
            });
            
            // 重新插入排序后的元素
            storages.forEach(storage => grid.appendChild(storage));
            
            // 保存用户偏好
            localStorage.setItem('subStorageSort', sortSelector.value);
        }
        
        // 页面加载时恢复用户偏好
        function restoreUserPreferences() {
            const savedLayout = localStorage.getItem('subStorageLayout');
            const savedSort = localStorage.getItem('subStorageSort');
            
            if (savedLayout) {
                document.getElementById('layoutSelector').value = savedLayout;
                changeLayout();
            }
            
            if (savedSort) {
                document.getElementById('sortSelector').value = savedSort;
                sortSubStorages();
            }
        }
        
        // 页面加载完成后执行
        document.addEventListener('DOMContentLoaded', function() {
            restoreUserPreferences();
        });

        // 点击模态框外部关闭
        window.onclick = function(event) {
            let textModal = document.getElementById('textModal');
            let subStorageModal = document.getElementById('subStorageModal');
            let passwordModal = document.getElementById('passwordModal');
            
            if (event.target == textModal) {
                closeTextModal();
            }
            if (event.target == subStorageModal) {
                closeSubStorageModal();
            }
            if (event.target == passwordModal) {
                closePasswordModal();
            }
        }

        // 消息弹窗系统
        function showRightMessage(message, type = 'info') {
            // 创建消息元素
            const messageDiv = document.createElement('div');
            messageDiv.className = `right-message ${type}`;
            messageDiv.innerHTML = `
                <div class="message-content">
                    <span class="message-text">${message}</span>
                    <button class="message-close" onclick="closeRightMessage(this)">×</button>
                </div>
            `;

            // 添加到容器
            let container = document.getElementById('rightMessageContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'rightMessageContainer';
                container.className = 'right-message-container';
                document.body.appendChild(container);
            }

            container.appendChild(messageDiv);

            // 显示动画
            setTimeout(() => {
                messageDiv.classList.add('show');
            }, 10);

            // 自动隐藏
            setTimeout(() => {
                closeRightMessage(messageDiv.querySelector('.message-close'));
            }, 5000);
        }

        function closeRightMessage(closeBtn) {
            const messageDiv = closeBtn.closest('.right-message');
            messageDiv.classList.remove('show');
            setTimeout(() => {
                messageDiv.remove();
            }, 300);
        }

        // 页面加载时显示PHP消息
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (!empty($message)): ?>
                // 提取消息类型和内容
                const messageHtml = `<?php echo addslashes($message); ?>`;
                const matches = messageHtml.match(/class=['"]message\s+(\w+)['"][^>]*>(.+?)<\/div>/);
                if (matches) {
                    const type = matches[1];
                    const content = matches[2];
                    showRightMessage(content, type);
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>
