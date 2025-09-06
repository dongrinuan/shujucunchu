<?php
// 启用错误显示用于调试
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查管理员权限
if (!isAdmin()) {
    redirect('login.php');
}

// 分页配置
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 5; // 每页显示5个存储柜
$offset = ($page - 1) * $limit;

// 获取存储柜总数
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM main_storages");
    $total_storages = $stmt->fetch()['total'];
    $total_pages = ceil($total_storages / $limit);
} catch (PDOException $e) {
    $total_storages = 0;
    $total_pages = 1;
}

// 获取当前页的主存储柜
try {
    $stmt = $pdo->prepare("SELECT * FROM main_storages ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $storages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $storages = [];
    error_log("Error getting storages: " . $e->getMessage());
}

// 获取系统统计
$stats = [];
try {
    $stats['total_storages'] = $total_storages;

    $stmt = $pdo->query("SELECT COUNT(*) as total_sub_storages FROM sub_storages");
    $stats['total_sub_storages'] = $stmt->fetch()['total_sub_storages'];

    $stmt = $pdo->query("SELECT COUNT(*) as total_files FROM stored_files");
    $stats['total_files'] = $stmt->fetch()['total_files'];

    $stmt = $pdo->query("SELECT SUM(file_size) as total_size FROM stored_files");
    $stats['total_size'] = $stmt->fetch()['total_size'] ?: 0;
} catch (PDOException $e) {
    $stats = [
        'total_storages' => 0,
        'total_sub_storages' => 0,
        'total_files' => 0,
        'total_size' => 0
    ];
}

// 处理表单提交
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_storage'])) {
        // 创建存储柜
        $storage_name = $_POST['storage_name'];
        $password1 = $_POST['password1'];
        $max_sub_storages = (int)$_POST['max_sub_storages'];
        
        $storage_id = generateUniqueStorageId($pdo);
        
        try {
            // 只设置一级密码，二级密码留空等待用户首次设置
            $stmt = $pdo->prepare("INSERT INTO main_storages (storage_id, storage_name, password1, password2, max_sub_storages, created_at) VALUES (?, ?, ?, '', ?, NOW())");
            
            if ($stmt->execute([$storage_id, $storage_name, password_hash($password1, PASSWORD_DEFAULT), $max_sub_storages])) {
                $message = '<div class="message success">存储柜创建成功！编号：' . $storage_id . '<br>用户首次使用时需设置二级密码</div>';
                // 重新计算总数和刷新列表
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM main_storages");
                $total_storages = $stmt->fetch()['total'];
                $total_pages = ceil($total_storages / $limit);
                
                // 如果新创建的存储柜导致页数增加，跳转到最后一页
                if ($total_storages > $page * $limit) {
                    $page = $total_pages;
                    $offset = ($page - 1) * $limit;
                }
                
                $stmt = $pdo->prepare("SELECT * FROM main_storages ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $storages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 更新统计信息
                $stats['total_storages'] = $total_storages;
            } else {
                $message = '<div class="message error">存储柜创建失败！</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="message error">存储柜创建失败：' . $e->getMessage() . '</div>';
        }
    } elseif (isset($_POST['reset_password'])) {
        // 重置一级密码
        $storage_id = $_POST['storage_id'];
        $new_password1 = $_POST['new_password1'];
        
        try {
            // 重置一级密码并清空二级密码
            $stmt = $pdo->prepare("UPDATE main_storages SET password1 = ?, password2 = '' WHERE storage_id = ?");
            
            if ($stmt->execute([password_hash($new_password1, PASSWORD_DEFAULT), $storage_id])) {
                $message = '<div class="message success">一级密码重置成功！二级密码已失效，用户需重新设置。</div>';
                
                // 重新获取存储柜列表
                $stmt = $pdo->prepare("SELECT * FROM main_storages ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $storages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $message = '<div class="message error">密码重置失败！</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="message error">密码重置失败：' . $e->getMessage() . '</div>';
        }
    } elseif (isset($_POST['clear_storage'])) {
        // 清理存储柜
        $storage_id = $_POST['storage_id'];
        try {
            $storage = getMainStorage($pdo, $storage_id);
            
            if ($storage && clearMainStorage($pdo, $storage['id'])) {
                $message = '<div class="message success">存储柜清理成功！</div>';
                
                // 重新获取存储柜列表
                $stmt = $pdo->prepare("SELECT * FROM main_storages ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $storages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $message = '<div class="message error">存储柜清理失败！</div>';
            }
        } catch (Exception $e) {
            $message = '<div class="message error">存储柜清理失败：' . $e->getMessage() . '</div>';
        }
    } elseif (isset($_POST['delete_storage'])) {
        // 删除存储柜
        $storage_id = $_POST['storage_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM main_storages WHERE storage_id = ?");
            
            if ($stmt->execute([$storage_id])) {
                $message = '<div class="message success">存储柜删除成功！</div>';
                // 重新计算总数和刷新列表
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM main_storages");
                $total_storages = $stmt->fetch()['total'];
                $total_pages = ceil($total_storages / $limit);
                
                // 如果当前页超出范围，回到第一页
                if ($page > $total_pages && $total_pages > 0) {
                    $page = $total_pages;
                    $offset = ($page - 1) * $limit;
                }
                
                $stmt = $pdo->prepare("SELECT * FROM main_storages ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $storages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // 更新统计信息
                $stats['total_storages'] = $total_storages;
            } else {
                $message = '<div class="message error">存储柜删除失败！</div>';
            }
        } catch (PDOException $e) {
            $message = '<div class="message error">存储柜删除失败：' . $e->getMessage() . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - 数字存储系统</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <h1>数字存储系统管理后台</h1>
        
        <div class="admin-header">
            <div class="admin-nav">
                <a href="index.php" class="nav-link active">存储柜管理</a>
                <a href="logs.php" class="nav-link">操作日志</a>
                <a href="logout.php" class="nav-link logout">退出登录</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>
        
        <!-- 系统统计卡片 -->
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-icon">📦</div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $stats['total_storages']; ?></div>
                    <div class="stat-label">存储柜总数</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📁</div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $stats['total_sub_storages']; ?></div>
                    <div class="stat-label">子存储柜</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📄</div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo $stats['total_files']; ?></div>
                    <div class="stat-label">文件总数</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💾</div>
                <div class="stat-info">
                    <div class="stat-number"><?php echo formatFileSize($stats['total_size']); ?></div>
                    <div class="stat-label">存储空间</div>
                </div>
            </div>
        </div>
        
        <!-- 管理操作区域 -->
        <div class="admin-layout">
            <!-- 左侧创建面板 -->
            <div class="sidebar">
                <div class="create-panel">
                    <div class="panel-header">
                        <h3>📦 创建存储柜</h3>
                    </div>
                    <form method="post" class="create-form">
                        <div class="form-fields">
                            <div class="input-group">
                                <label>存储柜名称</label>
                                <input type="text" name="storage_name" required placeholder="输入存储柜名称">
                            </div>
                            
                            <div class="input-group">
                                <label>一级密码（管理员密码）</label>
                                <input type="password" name="password1" required placeholder="设置一级密码">
                                <small style="color: rgba(255,255,255,0.7); font-size: 12px; margin-top: 5px;">
                                    用户将在首次使用时设置二级密码
                                </small>
                            </div>
                            
                            <div class="input-group">
                                <label>最大子存储柜数量</label>
                                <input type="number" name="max_sub_storages" value="10" min="1" max="50" required>
                            </div>
                        </div>
                        
                        <button type="submit" name="create_storage" class="create-btn">
                            ✨ 创建存储柜
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- 右侧存储柜列表 -->
            <div class="main-content">
                <div class="content-header">
                    <h3>📦 存储柜列表</h3>
                    <div class="header-stats">
                        <span class="count-badge">第 <?php echo $page; ?> 页 (共 <?php echo $total_storages; ?> 个) - 当前显示 <?php echo count($storages); ?> 个</span>
                    </div>
                </div>
                
                <?php if (empty($storages)): ?>
                    <div class="empty-hint">
                        <div class="empty-illustration">
                            <div class="empty-box">📦</div>
                            <div class="empty-text">
                                <h4>还没有存储柜</h4>
                                <p>使用左侧面板创建你的第一个存储柜</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="storage-table">
                        <div class="table-header">
                            <div class="col-name">名称</div>
                            <div class="col-id">编号</div>
                            <div class="col-stats">统计</div>
                            <div class="col-date">创建日期</div>
                            <div class="col-password">密码状态</div>
                            <div class="col-status">状态</div>
                            <div class="col-actions">操作</div>
                        </div>
                        
                        <div class="table-body">
                            <?php foreach ($storages as $storage): ?>
                                <?php
                                // 获取统计信息
                                try {
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as sub_count FROM sub_storages WHERE main_storage_id = ?");
                                    $stmt->execute([$storage['id']]);
                                    $sub_count = $stmt->fetch()['sub_count'];
                                    
                                    $stmt = $pdo->prepare("SELECT COUNT(*) as file_count FROM stored_files WHERE main_storage_id = ?");
                                    $stmt->execute([$storage['id']]);
                                    $file_count = $stmt->fetch()['file_count'];
                                } catch (PDOException $e) {
                                    $sub_count = 0;
                                    $file_count = 0;
                                }
                                ?>
                                <div class="table-row">
                                    <div class="col-name">
                                        <div class="storage-name">
                                            <?php echo htmlspecialchars($storage['storage_name']); ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-id">
                                        <code class="storage-code"><?php echo htmlspecialchars($storage['storage_id']); ?></code>
                                    </div>
                                    
                                    <div class="col-stats">
                                        <div class="stats-group">
                                            <span class="stat-item">📁 <?php echo $sub_count; ?>/<?php echo $storage['max_sub_storages']; ?></span>
                                            <span class="stat-item">📄 <?php echo $file_count; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="col-date">
                                        <span class="date-text"><?php echo date('m-d', strtotime($storage['created_at'])); ?></span>
                                    </div>
                                    
                                    <div class="col-password">
                                        <?php
                                        // 简单判断：如果password2为空或为空字符串，则显示待设置
                                        if (empty($storage['password2'])) {
                                            echo '<span class="password-status pending">⏳ 待设置</span>';
                                        } else {
                                            echo '<span class="password-status set">🔒 已设置</span>';
                                        }
                                        ?>
                                    </div>
                                    
                                    <div class="col-status">
                                        <span class="status-dot active">●</span>
                                        <span class="status-text">正常</span>
                                    </div>
                                    
                                    <div class="col-actions">
                                        <div class="action-group">
                                            <button type="button" class="action-btn reset-pwd" onclick="showResetPasswordModal('<?php echo $storage['storage_id']; ?>')" title="重置一级密码">
                                                🔑
                                            </button>
                                            
                                            <button type="button" class="action-btn volume-manage" onclick="showVolumeManageModal('<?php echo $storage['storage_id']; ?>', '<?php echo htmlspecialchars($storage['storage_name']); ?>')" title="体积管理">
                                                📊
                                            </button>
                                            
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="storage_id" value="<?php echo $storage['storage_id']; ?>">
                                                <button type="submit" name="clear_storage" class="action-btn clear" onclick="return confirm('确定要清理此存储柜吗？')" title="清理">
                                                    🧹
                                                </button>
                                            </form>
                                            
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="storage_id" value="<?php echo $storage['storage_id']; ?>">
                                                <button type="submit" name="delete_storage" class="action-btn delete" onclick="return confirm('确定要删除此存储柜吗？')" title="删除">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- 分页导航 -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <div class="pagination-info">
                                    共 <?php echo $total_storages; ?> 个存储柜，第 <?php echo $page; ?> 页，共 <?php echo $total_pages; ?> 页
                                </div>
                                <div class="pagination-controls">
                                    <?php if ($page > 1): ?>
                                        <a href="?page=1" class="pagination-btn">首页</a>
                                        <a href="?page=<?php echo $page - 1; ?>" class="pagination-btn">上一页</a>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <a href="?page=<?php echo $i; ?>" 
                                           class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <a href="?page=<?php echo $page + 1; ?>" class="pagination-btn">下一页</a>
                                        <a href="?page=<?php echo $total_pages; ?>" class="pagination-btn">末页</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 重置密码模态框 -->
    <div id="resetPasswordModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🔑 重置一级密码</h3>
                <button type="button" class="modal-close" onclick="hideResetPasswordModal()">&times;</button>
            </div>
            <form method="post" class="reset-password-form">
                <input type="hidden" id="resetStorageId" name="storage_id" value="">
                <div class="input-group">
                    <label>新的一级密码</label>
                    <input type="password" name="new_password1" required placeholder="输入新的一级密码" autocomplete="new-password">
                </div>
                <div class="modal-notice">
                    <p>⚠️ 重置一级密码后，该存储柜的二级密码将失效，用户需要重新设置二级密码。</p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="hideResetPasswordModal()">取消</button>
                    <button type="submit" name="reset_password" class="btn-confirm">确认重置</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 体积管理模态框 -->
    <div id="volumeManageModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="volumeModalTitle">📊 体积管理</h3>
                <button type="button" class="modal-close" onclick="hideVolumeManageModal()">&times;</button>
            </div>
            <div id="volumeModalContent">
                <!-- 动态加载内容 -->
            </div>
        </div>
    </div>

    <script>
        // 处理弹窗显示
        document.addEventListener('DOMContentLoaded', function() {
            const message = document.querySelector('.message');
            if (message) {
                // 添加show类触发动画
                setTimeout(() => {
                    message.classList.add('show');
                }, 100);
                
                // 3秒后自动隐藏
                setTimeout(() => {
                    message.style.right = '-400px';
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 400);
                }, 3000);
                
                // 点击弹窗可手动关闭
                message.addEventListener('click', function() {
                    message.style.right = '-400px';
                    message.style.opacity = '0';
                    setTimeout(() => {
                        message.remove();
                    }, 400);
                });
            }
        });
        
        // 重置密码模态框控制
        function showResetPasswordModal(storageId) {
            document.getElementById('resetStorageId').value = storageId;
            document.getElementById('resetPasswordModal').style.display = 'flex';
        }
        
        function hideResetPasswordModal() {
            document.getElementById('resetPasswordModal').style.display = 'none';
            document.querySelector('input[name="new_password1"]').value = '';
        }

        // 体积管理模态框控制
        function showVolumeManageModal(storageId, storageName) {
            document.getElementById('volumeModalTitle').textContent = `📊 ${storageName} - 体积管理`;
            
            // 加载子存储柜体积信息
            fetch('get_storage_volume_info.php?storage_id=' + encodeURIComponent(storageId))
                .then(response => {
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('volumeModalContent').innerHTML = generateVolumeManageHTML(data.sub_storages, storageId);
                    } else {
                        console.error('API Error:', data.message);
                        document.getElementById('volumeModalContent').innerHTML = `<p class="error">加载失败: ${data.message}</p>`;
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    document.getElementById('volumeModalContent').innerHTML = '<p class="error">加载失败，请重试</p>';
                });
            
            document.getElementById('volumeManageModal').style.display = 'flex';
        }

        function hideVolumeManageModal() {
            document.getElementById('volumeManageModal').style.display = 'none';
        }

        function generateVolumeManageHTML(subStorages, storageId) {
            if (!subStorages || subStorages.length === 0) {
                return '<p class="no-data">此存储柜暂无子存储柜</p>';
            }

            let html = '<div class="volume-manage-container">';
            
            subStorages.forEach(sub => {
                // 确保数值类型正确
                const volumeLimit = parseInt(sub.volume_limit);
                const currentVolume = parseInt(sub.current_volume);
                const usagePercent = volumeLimit > 0 ? ((currentVolume / volumeLimit) * 100).toFixed(2) : 0;
                const warningClass = usagePercent >= 80 ? 'warning' : '';
                
                html += `
                    <div class="sub-storage-volume-item ${warningClass}">
                        <div class="sub-storage-header">
                            <h4>子存储柜 #${sub.sub_number}</h4>
                            <div class="volume-stats">
                                <span class="usage">${formatBytes(currentVolume)}</span>
                                <span class="separator">/</span>
                                <span class="limit">${formatBytes(volumeLimit)}</span>
                                <span class="percent">(${usagePercent}%)</span>
                            </div>
                        </div>
                        <div class="volume-bar">
                            <div class="volume-progress ${warningClass}" style="width: ${usagePercent}%"></div>
                        </div>
                        <div class="volume-actions">
                            <form method="post" class="volume-form" onsubmit="return updateSubStorageVolume(event, ${sub.id})">
                                <input type="hidden" name="sub_storage_id" value="${sub.id}">
                                <input type="hidden" name="storage_id" value="${storageId}">
                                <div class="volume-input-group">
                                    <label>体积限制 (MB):</label>
                                    <input type="number" name="volume_limit" value="${Math.round(volumeLimit / 1024 / 1024)}" min="1" max="2048" required>
                                    <button type="submit" name="update_sub_volume" class="btn-update">更新</button>
                                </div>
                                <div class="quick-actions">
                                    <button type="button" onclick="setQuickVolume(this, 50)">50MB</button>
                                    <button type="button" onclick="setQuickVolume(this, 100)">100MB</button>
                                    <button type="button" onclick="setQuickVolume(this, 200)">200MB</button>
                                    <button type="button" onclick="setQuickVolume(this, 500)">500MB</button>
                                    <button type="button" onclick="setQuickVolume(this, 1024)">1GB</button>
                                </div>
                            </form>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            return html;
        }

        function formatBytes(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;
            
            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }
            
            return size.toFixed(unitIndex === 0 ? 0 : 2) + ' ' + units[unitIndex];
        }

        function setQuickVolume(button, volumeMB) {
            const form = button.closest('form');
            const input = form.querySelector('input[name="volume_limit"]');
            input.value = volumeMB;
        }

        function updateSubStorageVolume(event, subStorageId) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            
            fetch('update_sub_storage_volume.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 重新加载体积信息
                    const storageId = form.querySelector('input[name="storage_id"]').value;
                    showVolumeManageModal(storageId, document.getElementById('volumeModalTitle').textContent.split(' - ')[0].replace('📊 ', ''));
                    
                    // 显示成功消息
                    showMessage('体积限制更新成功！', 'success');
                } else {
                    showMessage(data.message || '更新失败', 'error');
                }
            })
            .catch(error => {
                showMessage('更新失败，请重试', 'error');
            });
            
            return false;
        }

        function showMessage(text, type) {
            // 创建消息元素
            const message = document.createElement('div');
            message.className = `message ${type}`;
            message.innerHTML = `<span>${text}</span>`;
            
            document.body.appendChild(message);
            
            // 显示动画
            setTimeout(() => message.classList.add('show'), 100);
            
            // 自动消失
            setTimeout(() => {
                message.classList.remove('show');
                setTimeout(() => message.remove(), 400);
            }, 3000);
        }
        
        // 点击模态框外部关闭
        window.onclick = function(event) {
            const resetModal = document.getElementById('resetPasswordModal');
            const volumeModal = document.getElementById('volumeManageModal');
            
            if (event.target === resetModal) {
                hideResetPasswordModal();
            }
            if (event.target === volumeModal) {
                hideVolumeManageModal();
            }
        }
    </script>
</body>
</html>
