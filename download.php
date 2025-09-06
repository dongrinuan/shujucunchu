<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// 验证文件下载权限
if (!isset($_GET['file_id']) || !isset($_SESSION['storage_id'])) {
    die('无效的下载请求！');
}

$file_id = (int)$_GET['file_id'];
$storage_id = $_SESSION['storage_id'];

// 获取文件信息并验证权限
$stmt = $pdo->prepare("
    SELECT sf.*, ss.main_storage_id, ms.storage_id as storage_code
    FROM stored_files sf
    JOIN sub_storages ss ON sf.sub_storage_id = ss.id
    JOIN main_storages ms ON ss.main_storage_id = ms.id
    WHERE sf.id = ? AND ms.storage_id = ?
");

$stmt->execute([$file_id, $storage_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    die('文件不存在或无权限访问！');
}

// 检查文件是否存在
if (!file_exists($file['file_path'])) {
    die('文件已丢失！');
}

// 记录下载日志
$stmt = $pdo->prepare("INSERT INTO operation_logs (storage_id, operation_type, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([
    $storage_id,
    'retrieve',
    "下载文件: " . $file['original_name'],
    $_SERVER['REMOTE_ADDR'],
    $_SERVER['HTTP_USER_AGENT']
]);

// 设置下载头
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
header('Content-Length: ' . filesize($file['file_path']));
header('Cache-Control: must-revalidate');
header('Pragma: public');

// 输出文件内容
readfile($file['file_path']);
exit();
?>
