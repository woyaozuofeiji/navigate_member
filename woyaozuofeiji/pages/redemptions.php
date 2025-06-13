<?php
// 启用输出缓冲，防止"headers already sent"错误
ob_start();

// 检查管理员是否登录（不要重复启动会话）
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../admin_login.php');
    exit;
}

// 更新最后活动时间
$_SESSION['admin_last_activity'] = time();

// 数据库连接 - 修正路径
require_once dirname(dirname(__DIR__)) . '/includes/db_connect.php';

// 每页显示记录数
$records_per_page = 10;

// 获取当前页码
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

// 计算偏移量
$offset = ($page - 1) * $records_per_page;

// 搜索条件
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// 处理状态更新
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $redemption_id = isset($_POST['redemption_id']) ? (int)$_POST['redemption_id'] : 0;
    $new_status = isset($_POST['new_status']) ? $_POST['new_status'] : '';
    $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
    
    // 验证状态值
    $valid_statuses = ['pending', 'processing', 'completed', 'cancelled', 'rejected'];
    if (in_array($new_status, $valid_statuses) && $redemption_id > 0) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("
                UPDATE reward_redemptions 
                SET status = ?, admin_notes = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $admin_notes, $redemption_id]);
            
            // 成功消息
            $success_message = '兑换记录状态已更新';
        } catch (PDOException $e) {
            // 错误消息
            $error_message = '数据库错误: ' . $e->getMessage();
        }
    } else {
        $error_message = '无效的状态值或记录ID';
    }
}

// 构建查询
try {
    $pdo = getDbConnection();
    
    // 构建WHERE子句
    $where_clause = "";
    $params = [];
    
    if (!empty($search)) {
        $where_clause .= " AND (m.username LIKE ? OR m.email LIKE ? OR r.name LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    if (!empty($status_filter)) {
        $where_clause .= " AND rr.status = ?";
        $params[] = $status_filter;
    }
    
    // 获取总记录数
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM reward_redemptions rr
        JOIN members m ON rr.member_id = m.id
        JOIN rewards r ON rr.reward_id = r.id
        WHERE 1=1 $where_clause
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = $row['total'];
    
    // 计算总页数
    $total_pages = ceil($total_records / $records_per_page);
    
    // 查询兑换记录
    $sql = "
        SELECT rr.*, m.username, m.email, r.name as reward_name, r.description, r.points_cost, r.category
        FROM reward_redemptions rr
        JOIN members m ON rr.member_id = m.id
        JOIN rewards r ON rr.reward_id = r.id
        WHERE 1=1 $where_clause
        ORDER BY rr.created_at DESC
        LIMIT $offset, $records_per_page
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $redemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = '数据库错误: ' . $e->getMessage();
    $redemptions = [];
    $total_pages = 0;
}

// 函数：获取状态标签类
function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'badge-warning';
        case 'processing':
            return 'badge-info';
        case 'completed':
            return 'badge-success';
        case 'cancelled':
            return 'badge-secondary';
        case 'rejected':
            return 'badge-danger';
        default:
            return 'badge-dark';
    }
}

// 函数：获取状态显示名称
function getStatusName($status) {
    switch ($status) {
        case 'pending':
            return '待处理';
        case 'processing':
            return '处理中';
        case 'completed':
            return '已完成';
        case 'cancelled':
            return '已取消';
        case 'rejected':
            return '已拒绝';
        default:
            return '未知';
    }
}

// 函数：获取分类显示名称
function getCategoryName($category) {
    switch ($category) {
        case 'crypto':
            return '数字货币';
        case 'physical':
            return '实物奖励';
        case 'service':
            return '服务类';
        case 'other':
        default:
            return '其他';
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>兑换记录管理 - 管理后台</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .status-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .status-filter {
            padding: 6px 12px;
            border-radius: 15px;
            background-color: #f8f9fa;
            color: #495057;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .status-filter:hover {
            background-color: #e9ecef;
        }
        
        .status-filter.active {
            background-color: #4361ee;
            color: white;
        }
        
        .modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 500px;
            max-width: 95%;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            z-index: 1001;
        }
        
        .modal-header {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.25rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #888;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .modal-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .modal-textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            min-height: 100px;
            resize: vertical;
        }
        
        .modal-select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .reward-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        
        .reward-info-item {
            margin-bottom: 8px;
        }
        
        .reward-info-item strong {
            min-width: 100px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-content">
            <h1>兑换记录管理</h1>
            
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <!-- 搜索和筛选 -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="search-form">
                        <div class="search-container">
                            <input type="text" name="search" placeholder="搜索用户名、邮箱或商品名..." value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                            <button type="submit" class="search-btn">搜索</button>
                        </div>
                    </form>
                    
                    <!-- 状态筛选 -->
                    <div class="status-filters">
                        <a href="?<?php echo !empty($search) ? 'search='.urlencode($search).'&' : ''; ?>" class="status-filter <?php echo empty($status_filter) ? 'active' : ''; ?>">全部</a>
                        <a href="?<?php echo !empty($search) ? 'search='.urlencode($search).'&' : ''; ?>status=pending" class="status-filter <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">待处理</a>
                        <a href="?<?php echo !empty($search) ? 'search='.urlencode($search).'&' : ''; ?>status=processing" class="status-filter <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">处理中</a>
                        <a href="?<?php echo !empty($search) ? 'search='.urlencode($search).'&' : ''; ?>status=completed" class="status-filter <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">已完成</a>
                        <a href="?<?php echo !empty($search) ? 'search='.urlencode($search).'&' : ''; ?>status=cancelled" class="status-filter <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">已取消</a>
                        <a href="?<?php echo !empty($search) ? 'search='.urlencode($search).'&' : ''; ?>status=rejected" class="status-filter <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>">已拒绝</a>
                    </div>
                </div>
            </div>
            
            <!-- 兑换记录表格 -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户信息</th>
                            <th>商品信息</th>
                            <th>兑换积分</th>
                            <th>状态</th>
                            <th>兑换时间</th>
                            <th>更新时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($redemptions)): ?>
                            <tr>
                                <td colspan="8" class="text-center">没有找到兑换记录</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($redemptions as $redemption): ?>
                                <tr>
                                    <td><?php echo $redemption['id']; ?></td>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($redemption['username']); ?></strong></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($redemption['email']); ?></div>
                                    </td>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($redemption['reward_name']); ?></strong></div>
                                        <div class="text-muted small"><?php echo getCategoryName($redemption['category']); ?></div>
                                    </td>
                                    <td><?php echo number_format($redemption['points_used']); ?></td>
                                    <td>
                                        <span class="badge <?php echo getStatusClass($redemption['status']); ?>">
                                            <?php echo getStatusName($redemption['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($redemption['created_at'])); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($redemption['updated_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-primary view-btn" 
                                                data-id="<?php echo $redemption['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($redemption['username']); ?>"
                                                data-email="<?php echo htmlspecialchars($redemption['email']); ?>"
                                                data-reward="<?php echo htmlspecialchars($redemption['reward_name']); ?>"
                                                data-description="<?php echo htmlspecialchars($redemption['description']); ?>"
                                                data-category="<?php echo getCategoryName($redemption['category']); ?>"
                                                data-points="<?php echo number_format($redemption['points_used']); ?>"
                                                data-status="<?php echo $redemption['status']; ?>"
                                                data-notes="<?php echo htmlspecialchars($redemption['admin_notes'] ?? ''); ?>"
                                                data-created="<?php echo date('Y-m-d H:i:s', strtotime($redemption['created_at'])); ?>">
                                            查看/处理
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status='.urlencode($status_filter) : ''; ?>" class="page-link">&laquo; 上一页</a>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1) {
                        echo '<a href="?page=1' . (!empty($search) ? '&search='.urlencode($search) : '') . (!empty($status_filter) ? '&status='.urlencode($status_filter) : '') . '" class="page-link">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++) {
                        $active_class = ($i == $page) ? 'active' : '';
                        echo '<a href="?page=' . $i . (!empty($search) ? '&search='.urlencode($search) : '') . (!empty($status_filter) ? '&status='.urlencode($status_filter) : '') . '" class="page-link ' . $active_class . '">' . $i . '</a>';
                    }
                    
                    if ($end_page < $total_pages) {
                        if ($end_page < $total_pages - 1) {
                            echo '<span class="page-ellipsis">...</span>';
                        }
                        echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search='.urlencode($search) : '') . (!empty($status_filter) ? '&status='.urlencode($status_filter) : '') . '" class="page-link">' . $total_pages . '</a>';
                    }
                    ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status='.urlencode($status_filter) : ''; ?>" class="page-link">下一页 &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 查看/处理弹窗 -->
    <div class="modal-backdrop" id="modalBackdrop"></div>
    <div class="modal" id="redemptionModal">
        <div class="modal-header">
            <h2>兑换记录详情</h2>
            <button type="button" class="modal-close" id="closeModal">&times;</button>
        </div>
        <div class="modal-body">
            <div class="reward-details">
                <div class="reward-info-item">
                    <strong>用户名:</strong> <span id="modalUsername"></span>
                </div>
                <div class="reward-info-item">
                    <strong>邮箱:</strong> <span id="modalEmail"></span>
                </div>
                <div class="reward-info-item">
                    <strong>商品:</strong> <span id="modalReward"></span>
                </div>
                <div class="reward-info-item">
                    <strong>商品描述:</strong> <span id="modalDescription"></span>
                </div>
                <div class="reward-info-item">
                    <strong>商品分类:</strong> <span id="modalCategory"></span>
                </div>
                <div class="reward-info-item">
                    <strong>兑换积分:</strong> <span id="modalPoints"></span>
                </div>
                <div class="reward-info-item">
                    <strong>兑换时间:</strong> <span id="modalCreated"></span>
                </div>
                <div class="reward-info-item">
                    <strong>当前状态:</strong> <span id="modalCurrentStatus"></span>
                </div>
            </div>
            
            <form id="updateStatusForm" method="POST" action="">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="redemption_id" id="redemptionId">
                
                <label class="modal-label" for="newStatus">更新状态:</label>
                <select name="new_status" id="newStatus" class="modal-select">
                    <option value="pending">待处理</option>
                    <option value="processing">处理中</option>
                    <option value="completed">已完成</option>
                    <option value="cancelled">已取消</option>
                    <option value="rejected">已拒绝</option>
                </select>
                
                <label class="modal-label" for="adminNotes">管理员备注:</label>
                <textarea name="admin_notes" id="adminNotes" class="modal-textarea" placeholder="添加处理记录或备注..."></textarea>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancelModal">取消</button>
            <button type="button" class="btn btn-primary" id="submitModal">更新状态</button>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('redemptionModal');
            const modalBackdrop = document.getElementById('modalBackdrop');
            const closeBtn = document.getElementById('closeModal');
            const cancelBtn = document.getElementById('cancelModal');
            const submitBtn = document.getElementById('submitModal');
            const form = document.getElementById('updateStatusForm');
            
            // 打开弹窗
            document.querySelectorAll('.view-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const username = this.getAttribute('data-username');
                    const email = this.getAttribute('data-email');
                    const reward = this.getAttribute('data-reward');
                    const description = this.getAttribute('data-description');
                    const category = this.getAttribute('data-category');
                    const points = this.getAttribute('data-points');
                    const status = this.getAttribute('data-status');
                    const notes = this.getAttribute('data-notes');
                    const created = this.getAttribute('data-created');
                    
                    document.getElementById('redemptionId').value = id;
                    document.getElementById('modalUsername').textContent = username;
                    document.getElementById('modalEmail').textContent = email;
                    document.getElementById('modalReward').textContent = reward;
                    document.getElementById('modalDescription').textContent = description;
                    document.getElementById('modalCategory').textContent = category;
                    document.getElementById('modalPoints').textContent = points;
                    document.getElementById('modalCreated').textContent = created;
                    document.getElementById('modalCurrentStatus').textContent = getStatusName(status);
                    document.getElementById('adminNotes').value = notes;
                    
                    // 设置下拉框的默认值
                    document.getElementById('newStatus').value = status;
                    
                    // 显示弹窗
                    modal.style.display = 'block';
                    modalBackdrop.style.display = 'block';
                });
            });
            
            // 关闭弹窗
            function closeModal() {
                modal.style.display = 'none';
                modalBackdrop.style.display = 'none';
            }
            
            closeBtn.addEventListener('click', closeModal);
            cancelBtn.addEventListener('click', closeModal);
            modalBackdrop.addEventListener('click', closeModal);
            
            // 提交表单
            submitBtn.addEventListener('click', function() {
                form.submit();
            });
            
            // 辅助函数：获取状态显示名称
            function getStatusName(status) {
                const statusMap = {
                    'pending': '待处理',
                    'processing': '处理中',
                    'completed': '已完成',
                    'cancelled': '已取消',
                    'rejected': '已拒绝'
                };
                return statusMap[status] || '未知';
            }
        });
    </script>
</body>
</html>

<?php
// 输出所有缓冲的内容并关闭缓冲
ob_end_flush();
?> 