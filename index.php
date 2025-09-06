<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// 获取排序参数
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_desc';
$allowed_sorts = ['created_desc', 'created_asc', 'name_asc', 'name_desc', 'id_asc', 'id_desc'];

if (!in_array($sort, $allowed_sorts)) {
    $sort = 'created_desc';
}

// 分页参数
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12; // 每页显示12个存储柜
$offset = ($page - 1) * $per_page;

// 构建ORDER BY子句
$order_by = '';
switch ($sort) {
    case 'created_asc':
        $order_by = 'ORDER BY created_at ASC';
        break;
    case 'created_desc':
        $order_by = 'ORDER BY created_at DESC';
        break;
    case 'name_asc':
        $order_by = 'ORDER BY storage_name ASC';
        break;
    case 'name_desc':
        $order_by = 'ORDER BY storage_name DESC';
        break;
    case 'id_asc':
        $order_by = 'ORDER BY storage_id ASC';
        break;
    case 'id_desc':
        $order_by = 'ORDER BY storage_id DESC';
        break;
    default:
        $order_by = 'ORDER BY created_at DESC';
}

// 获取总存储柜数量
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM main_storages WHERE status = 'active'");
    $total_storages = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $total_storages = 0;
}

// 计算总页数
$total_pages = ceil($total_storages / $per_page);

// 获取当前页的存储柜
try {
    $stmt = $pdo->query("SELECT * FROM main_storages WHERE status = 'active' $order_by LIMIT $per_page OFFSET $offset");
    $storages = $stmt->fetchAll();
} catch (PDOException $e) {
    $storages = [];
}

// 获取系统统计
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_files FROM stored_files");
    $total_files = $stmt->fetch()['total_files'];
} catch (PDOException $e) {
    $total_files = 0;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数字存储系统</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="container">
        <h1>数字存储系统</h1>
        
        <!-- 标签页导航 -->
        <div class="tab-navigation">
            <button class="tab-btn active" onclick="switchTab('storage')">可用存储柜</button>
            <a href="admin/login.php" class="tab-btn admin-link">管理员入口</a>
        </div>
        
        <!-- 存储柜标签页内容 -->
        <div id="storage-tab" class="tab-content active">
            <div class="storage-list-section">
                <div class="stats">
                    <span class="stat-item">共 <?php echo $total_storages; ?> 个存储柜</span>
                    <span class="stat-item">已存储 <?php echo $total_files; ?> 个文件</span>
                </div>
                
                <!-- 排序和布局选择器 -->
                <div class="controls-section">
                    <div class="sort-section">
                        <label for="storage-sort">📊 排序方式：</label>
                        <select id="storage-sort" onchange="changeSort(this.value)">
                            <option value="created_desc" <?php echo $sort === 'created_desc' ? 'selected' : ''; ?>>最新创建</option>
                            <option value="created_asc" <?php echo $sort === 'created_asc' ? 'selected' : ''; ?>>最早创建</option>
                            <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>名称A-Z</option>
                            <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>名称Z-A</option>
                            <option value="id_asc" <?php echo $sort === 'id_asc' ? 'selected' : ''; ?>>编号升序</option>
                            <option value="id_desc" <?php echo $sort === 'id_desc' ? 'selected' : ''; ?>>编号降序</option>
                        </select>
                    </div>
                    
                    <div class="layout-section">
                        <label for="storage-layout">🎛️ 布局模式：</label>
                        <select id="storage-layout" onchange="changeLayout(this.value)">
                            <option value="grid">标准网格</option>
                            <option value="compact">紧凑型</option>
                            <option value="list">列表模式</option>
                            <option value="large">大卡片</option>
                        </select>
                    </div>
                </div>
                
                <div class="storage-grid" id="storage-container">
                    <?php if (empty($storages)): ?>
                        <div class="no-storage">
                            <p>暂无可用存储柜</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($storages as $storage): ?>
                        <div class="storage-card" onclick="fillStorageId('<?php echo htmlspecialchars($storage['storage_id']); ?>')">
                            <div class="storage-header">
                                <h3><?php echo htmlspecialchars($storage['storage_name']); ?></h3>
                                <span class="storage-id"><?php echo htmlspecialchars($storage['storage_id']); ?></span>
                            </div>
                            <div class="storage-info">
                                <small>创建时间: <?php echo date('Y-m-d', strtotime($storage['created_at'])); ?></small>
                            </div>
                            <div class="storage-action">
                                <span class="click-hint">点击打开操作面板</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- 分页导航 -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=1&sort=<?php echo $sort; ?>" class="pagination-btn">首页</a>
                            <a href="?page=<?php echo $page - 1; ?>&sort=<?php echo $sort; ?>" class="pagination-btn">上一页</a>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <a href="?page=<?php echo $i; ?>&sort=<?php echo $sort; ?>" 
                               class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&sort=<?php echo $sort; ?>" class="pagination-btn">下一页</a>
                            <a href="?page=<?php echo $total_pages; ?>&sort=<?php echo $sort; ?>" class="pagination-btn">末页</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pagination-info">
                        第 <?php echo $page; ?> 页，共 <?php echo $total_pages; ?> 页 | 
                        显示 <?php echo count($storages); ?> 个存储柜，共 <?php echo $total_storages; ?> 个
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 模态框 -->
        <div id="operationModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>🔒 存储柜操作</h2>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <form id="storageForm" class="storage-form">
                        <div class="form-group">
                            <label>存储柜编号:</label>
                            <input type="text" name="storage_id" required placeholder="点击存储柜或手动输入编号">
                        </div>
                        
                        <div class="form-group">
                            <label>一级密码:</label>
                            <input type="password" name="password1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="editMode" name="edit_mode" style="margin-right: 6px; transform: scale(1.3);">
                                二级密码（编辑模式）:
                            </label>
                            <input type="password" name="password2" id="password2" placeholder="勾选复选框后输入二级密码" disabled>
                            <small style="color: #666; margin-top: 5px; display: block;">
                                • 不勾选：仅查看和下载内容<br>
                                • 勾选：可以存取文件、管理存储柜<br>
                                • <strong>首次使用</strong>：输入的二级密码将成为该存储柜的固定二级密码
                            </small>
                        </div>
                        
                        <button type="submit" id="submitBtn">确定</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 错误提示模态框 -->
    <div id="errorModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 400px; margin: 15% auto;">
            <div class="modal-header" style="background: #dc3545; color: white;">
                <h2>❌ 操作失败</h2>
                <span class="close" onclick="closeErrorModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p id="errorMessage" style="text-align: center; font-size: 16px; color: #333;"></p>
                <div style="text-align: center; margin-top: 20px;">
                    <button onclick="closeErrorModal()" class="btn btn-primary">确定</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // 确保页面加载时模态框是隐藏的
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('operationModal').style.display = 'none';
    });
    
    // 标签页切换函数
    function switchTab(tabName) {
        // 只处理存储柜标签页，管理员入口是直接链接
        if (tabName !== 'storage') return;
        
        // 隐藏所有标签页内容
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.remove('active');
        });
        
        // 移除所有标签按钮的激活状态
        const tabBtns = document.querySelectorAll('.tab-btn:not(.admin-link)');
        tabBtns.forEach(btn => {
            btn.classList.remove('active');
        });
        
        // 显示存储柜标签页
        document.getElementById('storage-tab').classList.add('active');
        
        // 激活存储柜标签按钮
        event.target.classList.add('active');
    }
    
    // 模态框控制函数
    function openModal() {
        document.getElementById('operationModal').style.display = 'block';
        // 聚焦到第一个输入框
        setTimeout(function() {
            const storageIdInput = document.querySelector('input[name="storage_id"]');
            if (storageIdInput.value) {
                document.querySelector('input[name="password1"]').focus();
            } else {
                storageIdInput.focus();
            }
        }, 100);
    }
    
    function closeModal() {
        document.getElementById('operationModal').style.display = 'none';
    }
    
    // 点击存储柜卡片的函数
    function fillStorageId(storageId) {
        document.querySelector('input[name="storage_id"]').value = storageId;
        // 直接打开模态框
        openModal();
    }
    
    // 点击模态框外部关闭
    window.onclick = function(event) {
        const modal = document.getElementById('operationModal');
        const errorModal = document.getElementById('errorModal');
        if (event.target == modal) {
            closeModal();
        }
        if (event.target == errorModal) {
            closeErrorModal();
        }
    }
    
    // ESC键关闭模态框
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeModal();
        }
    });
    
    // 页面加载完成后设置事件监听
    document.addEventListener('DOMContentLoaded', function() {
        // 编辑模式复选框变化处理
        const editModeCheckbox = document.getElementById('editMode');
        const password2Input = document.getElementById('password2');
        
        editModeCheckbox.addEventListener('change', function() {
            if (this.checked) {
                password2Input.disabled = false;
                password2Input.required = true;
                password2Input.placeholder = "请输入二级密码启用编辑模式";
                password2Input.style.backgroundColor = '#fff';
                password2Input.focus();
            } else {
                password2Input.disabled = true;
                password2Input.required = false;
                password2Input.value = '';
                password2Input.placeholder = "勾选上方复选框后输入二级密码";
                password2Input.style.backgroundColor = '#f0f0f0';
            }
        });
        
        // 表单提交处理
        document.getElementById('storageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const editModeCheckbox = document.getElementById('editMode');
            const passwordInput = document.getElementById('password2');
            const submitBtn = document.getElementById('submitBtn');
            
            // 验证表单
            if (editModeCheckbox.checked && !passwordInput.value.trim()) {
                showErrorModal('编辑模式需要输入二级密码！');
                passwordInput.focus();
                return;
            }
            
            // 禁用提交按钮防止重复提交
            submitBtn.disabled = true;
            submitBtn.textContent = '验证中...';
            
            // 准备表单数据
            const formData = new FormData(this);
            formData.append('action', editModeCheckbox.checked ? 'manage' : 'retrieve');
            
            // AJAX提交
            fetch('user_action.php', {
                method: 'POST',
                body: formData,
                redirect: 'manual' // 防止自动跟随重定向
            })
            .then(response => {
                // 检查是否是重定向 (成功的情况)
                if (response.type === 'opaqueredirect' || response.status === 302 || response.status === 0) {
                    window.location.href = 'storage_interface.php';
                    return null;
                }
                
                // 如果不是重定向，获取响应内容检查错误
                return response.text();
            })
            .then(data => {
                if (data === null) return; // 已经处理了重定向
                
                // 检查是否包含错误信息
                if (data.includes('class="message error"') || data.includes('错误')) {
                    // 提取错误信息
                    const errorMatch = data.match(/<div class="message error"[^>]*>(.*?)<\/div>/s);
                    if (errorMatch) {
                        let errorText = errorMatch[1].replace(/<[^>]*>/g, '').trim();
                        // 清理可能的HTML实体
                        errorText = errorText.replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&');
                        showErrorModal(errorText);
                    } else {
                        // 尝试其他错误格式
                        if (data.includes('一级密码错误')) {
                            showErrorModal('一级密码错误！');
                        } else if (data.includes('二级密码错误')) {
                            showErrorModal('二级密码错误！');
                        } else if (data.includes('存储柜') && data.includes('不存在')) {
                            showErrorModal('存储柜不存在！');
                        } else {
                            showErrorModal('操作失败，请检查输入信息！');
                        }
                    }
                    
                    // 重新启用提交按钮
                    submitBtn.disabled = false;
                    submitBtn.textContent = '确定';
                } else {
                    // 如果没有明确的错误，可能是其他成功情况
                    window.location.href = 'storage_interface.php';
                }
            })
            .catch(error => {
                showErrorModal('网络错误，请稍后重试！');
                
                // 重新启用提交按钮
                submitBtn.disabled = false;
                submitBtn.textContent = '确定';
            });
        });
    });
    
    // 错误提示模态框
    function showErrorModal(message) {
        document.getElementById('errorMessage').textContent = message;
        document.getElementById('errorModal').style.display = 'block';
    }
    
    function closeErrorModal() {
        document.getElementById('errorModal').style.display = 'none';
    }
    
    // 检查存储柜状态并更新提示
    function updatePassword2Hint() {
        const storageIdInput = document.querySelector('input[name="storage_id"]');
        const password2Input = document.getElementById('password2');
        const hintElement = password2Input.parentNode.querySelector('small');
        
        if (storageIdInput.value.trim() === '') {
            return;
        }
        
        fetch(`check_storage_status.php?storage_id=${encodeURIComponent(storageIdInput.value.trim())}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.exists) {
                    if (data.hasPassword2) {
                        // 已设置二级密码
                        password2Input.placeholder = '请输入已设置的二级密码';
                        hintElement.innerHTML = `
                            • 不勾选：仅查看和下载内容<br>
                            • 勾选：可以存取文件、管理存储柜<br>
                            • <span style="color: #28a745;"><strong>此存储柜已设置二级密码</strong></span>
                        `;
                    } else {
                        // 未设置二级密码
                        password2Input.placeholder = '首次设置二级密码';
                        hintElement.innerHTML = `
                            • 不勾选：仅查看和下载内容<br>
                            • 勾选：可以存取文件、管理存储柜<br>
                            • <span style="color: #dc3545;"><strong>首次使用</strong>：输入的二级密码将成为该存储柜的固定二级密码</span>
                        `;
                    }
                } else if (data.success && !data.exists) {
                    // 存储柜不存在
                    password2Input.placeholder = '请先输入正确的存储柜编号';
                    hintElement.innerHTML = `
                        • 不勾选：仅查看和下载内容<br>
                        • 勾选：可以存取文件、管理存储柜<br>
                        • <span style="color: #ffc107;">存储柜编号不存在</span>
                    `;
                }
            })
            .catch(error => {
                // 检查存储柜状态时出错，不显示错误信息
            });
    }
    
    // 排序功能
    function changeSort(sortValue) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('sort', sortValue);
        currentUrl.searchParams.set('page', '1'); // 切换排序时回到第一页
        window.location.href = currentUrl.toString();
    }
    
    // 布局切换功能
    function changeLayout(layoutType) {
        const container = document.getElementById('storage-container');
        const cards = container.querySelectorAll('.storage-card');
        
        // 移除所有布局类
        container.classList.remove('grid-layout', 'compact-layout', 'list-layout', 'large-layout');
        
        // 添加新的布局类
        switch(layoutType) {
            case 'grid':
                container.classList.add('grid-layout');
                break;
            case 'compact':
                container.classList.add('compact-layout');
                break;
            case 'list':
                container.classList.add('list-layout');
                break;
            case 'large':
                container.classList.add('large-layout');
                break;
        }
        
        // 保存用户选择到localStorage
        localStorage.setItem('storageLayout', layoutType);
    }
    
    // 恢复用户上次选择的布局
    function restoreLayout() {
        const savedLayout = localStorage.getItem('storageLayout') || 'grid';
        const layoutSelect = document.getElementById('storage-layout');
        if (layoutSelect) {
            layoutSelect.value = savedLayout;
            changeLayout(savedLayout);
        }
    }
    
    // 监听存储柜编号输入变化
    document.addEventListener('DOMContentLoaded', function() {
        const storageIdInput = document.querySelector('input[name="storage_id"]');
        let checkTimeout;
        
        storageIdInput.addEventListener('input', function() {
            clearTimeout(checkTimeout);
            checkTimeout = setTimeout(updatePassword2Hint, 500); // 延迟500ms检查
        });
        
        storageIdInput.addEventListener('blur', updatePassword2Hint);
        
        // 恢复用户选择的布局
        restoreLayout();
    });
    </script>
</body>
</html>
