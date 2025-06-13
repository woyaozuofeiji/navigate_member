<?php
require_once dirname(dirname(__DIR__)) . '/includes/db_connect.php';

// 获取数据库连接
$pdo = getDbConnection();

// 当前页码
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;

// 每页显示条数
$per_page = 15;

// 计算偏移量
$offset = ($page - 1) * $per_page;

// 搜索条件
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];

if (!empty($search)) {
    $search_condition = " WHERE username LIKE ? OR email LIKE ? OR invite_code = ? ";
    $search_params = ["%$search%", "%$search%", $search];
}

// 获取会员总数
$total_count = 0;
try {
    $sql = "SELECT COUNT(*) FROM members" . $search_condition;
    $stmt = $pdo->prepare($sql);
    if (!empty($search_params)) {
        $stmt->execute($search_params);
    } else {
        $stmt->execute();
    }
    $total_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    $total_count = 0;
}

// 计算总页数
$total_pages = ceil($total_count / $per_page);
if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

// 获取会员列表
$members = [];
try {
    $sql = "SELECT m.id, m.username, m.email, m.points, m.invite_code, m.register_ip, m.last_login, 
                  m.created_at, m.updated_at,
                  (SELECT COUNT(*) FROM members m2 WHERE m2.referrer_id = m.id) as referrals_count,
                  (SELECT ip_address FROM login_logs 
                   WHERE member_id = m.id AND status = 'success' 
                   ORDER BY created_at DESC LIMIT 1) as last_login_ip
           FROM members m
           $search_condition
           ORDER BY m.created_at DESC
           LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    if (!empty($search_params)) {
        $stmt->execute($search_params);
    } else {
        $stmt->execute();
    }
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $members = [];
}

// 处理会员删除
$delete_result = '';
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $member_id = (int)$_GET['id'];
    try {
        // 开始事务
        $pdo->beginTransaction();
        
        // 删除积分交易记录
        $stmt = $pdo->prepare("DELETE FROM point_transactions WHERE member_id = ?");
        $stmt->execute([$member_id]);
        
        // 将引用此会员作为推荐人的记录设为NULL
        $stmt = $pdo->prepare("UPDATE members SET referrer_id = NULL WHERE referrer_id = ?");
        $stmt->execute([$member_id]);
        
        // 删除会员记录
        $stmt = $pdo->prepare("DELETE FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        
        // 提交事务
        $pdo->commit();
        
        $delete_result = '会员删除成功';
    } catch (PDOException $e) {
        // 回滚事务
        $pdo->rollBack();
        $delete_result = '会员删除失败: ' . $e->getMessage();
    }
}

// 处理会员编辑
$edit_member = null;
$edit_result = '';

if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $member_id = (int)$_GET['id'];
    try {
        // 获取会员信息
        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $edit_member = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $edit_result = '获取会员信息失败: ' . $e->getMessage();
    }
}

// 处理更新会员信息
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $member_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $points = isset($_POST['points']) ? (int)$_POST['points'] : 0;
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    
    try {
        // 检查用户名是否已存在
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE username = ? AND id != ?");
        $stmt->execute([$username, $member_id]);
        if ($stmt->fetchColumn() > 0) {
            $edit_result = '用户名已存在';
        } else {
            // 检查邮箱是否已存在
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM members WHERE email = ? AND id != ?");
            $stmt->execute([$email, $member_id]);
            if ($stmt->fetchColumn() > 0) {
                $edit_result = '邮箱已存在';
            } else {
                // 开始事务
                $pdo->beginTransaction();
                
                // 更新会员信息
                if (!empty($new_password)) {
                    // 如果提供了新密码，则更新密码
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("
                        UPDATE members 
                        SET username = ?, email = ?, points = ?, password = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $email, $points, $hashedPassword, $member_id]);
                } else {
                    // 不更新密码
                    $stmt = $pdo->prepare("
                        UPDATE members 
                        SET username = ?, email = ?, points = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$username, $email, $points, $member_id]);
                }
                
                // 获取原会员信息
                $stmt = $pdo->prepare("SELECT points FROM members WHERE id = ?");
                $stmt->execute([$member_id]);
                $old_points = $stmt->fetchColumn();
                
                // 如果积分有变化，添加积分交易记录
                $points_diff = $points - $old_points;
                if ($points_diff != 0) {
                    $description = "管理员调整积分";
                    $stmt = $pdo->prepare("
                        INSERT INTO point_transactions (member_id, points, description)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$member_id, $points_diff, $description]);
                }
                
                // 提交事务
                $pdo->commit();
                
                $edit_result = '会员信息更新成功';
                
                // 重新获取会员信息
                $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                $stmt->execute([$member_id]);
                $edit_member = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } catch (PDOException $e) {
        // 回滚事务
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $edit_result = '会员信息更新失败: ' . $e->getMessage();
    }
}
?>

<!-- 搜索表单 -->
<div class="card">
    <div class="card-header">
        <h3>会员搜索</h3>
    </div>
    <div class="card-body">
        <style>
            .search-form {
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .search-input {
                flex: 1;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                transition: border-color 0.3s;
            }
            .search-input:focus {
                border-color: #3498db;
                outline: none;
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
            }
            .search-btn {
                padding: 8px 15px;
                background-color: #3498db;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                transition: background-color 0.3s;
                font-size: 14px;
                font-weight: 500;
            }
            .search-btn:hover {
                background-color: #2980b9;
            }
            .reset-btn {
                padding: 8px 15px;
                background-color: #f5f5f5;
                color: #333;
                border: 1px solid #ddd;
                border-radius: 4px;
                cursor: pointer;
                transition: all 0.3s;
                font-size: 14px;
            }
            .reset-btn:hover {
                background-color: #e9e9e9;
            }
        </style>
        <form action="" method="get" class="search-form">
            <input type="hidden" name="page" value="members">
            <div style="flex: 1;">
                <input type="text" name="search" class="search-input" placeholder="搜索：用户名、邮箱或邀请码" value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <button type="submit" class="search-btn">
                <i class="fas fa-search"></i> 搜索
            </button>
            <a href="?page=members" class="reset-btn">
                <i class="fas fa-redo"></i> 重置
            </a>
        </form>
    </div>
</div>

<?php if (!empty($delete_result)): ?>
    <div class="alert <?php echo strpos($delete_result, '成功') !== false ? 'alert-success' : 'alert-danger'; ?>" style="margin-top: 20px; padding: 10px; border-radius: 4px; background-color: <?php echo strpos($delete_result, '成功') !== false ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo strpos($delete_result, '成功') !== false ? '#155724' : '#721c24'; ?>;">
        <?php echo $delete_result; ?>
    </div>
<?php endif; ?>

<?php if ($edit_member): ?>
<!-- 编辑会员表单 -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>编辑会员</h3>
    </div>
    <div class="card-body">
        <?php if (!empty($edit_result)): ?>
            <div class="alert <?php echo strpos($edit_result, '成功') !== false ? 'alert-success' : 'alert-danger'; ?>" style="margin-bottom: 20px; padding: 10px; border-radius: 4px; background-color: <?php echo strpos($edit_result, '成功') !== false ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo strpos($edit_result, '成功') !== false ? '#155724' : '#721c24'; ?>;">
                <?php echo $edit_result; ?>
            </div>
        <?php endif; ?>
        
        <form action="" method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?php echo $edit_member['id']; ?>">
            
            <div class="row" style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label for="username">用户名</label>
                    <input type="text" name="username" id="username" class="form-control" value="<?php echo htmlspecialchars($edit_member['username']); ?>" required>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label for="email">邮箱</label>
                    <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($edit_member['email']); ?>" required>
                </div>
            </div>
            
            <div class="row" style="display: flex; gap: 20px;">
                <div class="form-group" style="flex: 1;">
                    <label for="points">积分</label>
                    <input type="number" name="points" id="points" class="form-control" value="<?php echo $edit_member['points']; ?>" required>
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label for="invite_code">邀请码（不可修改）</label>
                    <input type="text" id="invite_code" class="form-control" value="<?php echo htmlspecialchars($edit_member['invite_code']); ?>" readonly>
                </div>
            </div>
            
            <div class="form-group">
                <label for="new_password">新密码（留空表示不修改）</label>
                <input type="password" name="new_password" id="new_password" class="form-control">
            </div>
            
            <div class="form-group">
                <label>注册时间</label>
                <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i:s', strtotime($edit_member['created_at'])); ?>" readonly>
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary">保存修改</button>
                <a href="?page=members" class="btn btn-secondary">取消</a>
            </div>
        </form>
    </div>
</div>
<?php else: ?>
<!-- 会员列表 -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>会员列表</h3>
        <div>共 <?php echo number_format($total_count); ?> 条记录</div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <style>
                .member-table {
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 14px;
                }
                .member-table th, .member-table td {
                    padding: 10px;
                    text-align: left;
                    border: 1px solid #ddd;
                    vertical-align: middle;
                }
                .member-table th {
                    background-color: #f5f5f5;
                    font-weight: 600;
                    position: sticky;
                    top: 0;
                    z-index: 10;
                }
                .member-table tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .member-table tr:hover {
                    background-color: #f0f7ff;
                }
                .member-table .email-cell {
                    max-width: 200px;
                    word-break: break-all;
                }
                .member-table .invite-cell {
                    font-family: monospace;
                }
                .member-table .ip-cell {
                    font-family: monospace;
                    white-space: nowrap;
                }
                .member-table .date-cell {
                    white-space: nowrap;
                }
                .member-table .actions-cell {
                    white-space: nowrap;
                    width: 1%;
                }
                .btn-sm {
                    padding: 4px 8px;
                    font-size: 12px;
                }
            </style>
            <table class="member-table">
                <thead>
                    <tr>
                        <th width="4%">ID</th>
                        <th width="10%">用户名</th>
                        <th width="20%">电子邮件</th>
                        <th width="6%">积分</th>
                        <th width="10%">邀请码</th>
                        <th width="12%">注册IP</th>
                        <th width="12%">最后登录IP</th>
                        <th width="10%">最后登录时间</th>
                        <th width="10%">注册时间</th>
                        <th width="6%">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($members)): ?>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($member['id']); ?></td>
                                <td><?php echo htmlspecialchars($member['username']); ?></td>
                                <td class="email-cell"><?php echo htmlspecialchars($member['email']); ?></td>
                                <td><?php echo number_format($member['points']); ?></td>
                                <td class="invite-cell"><?php echo htmlspecialchars($member['invite_code']); ?></td>
                                <td class="ip-cell"><?php echo htmlspecialchars($member['register_ip'] ?? '未记录'); ?></td>
                                <td class="ip-cell"><?php echo htmlspecialchars($member['last_login_ip'] ?? '未登录'); ?></td>
                                <td class="date-cell"><?php echo $member['last_login'] ? date('Y-m-d<br>H:i:s', strtotime($member['last_login'])) : '未登录'; ?></td>
                                <td class="date-cell"><?php echo date('Y-m-d<br>H:i:s', strtotime($member['created_at'])); ?></td>
                                <td class="actions-cell">
                                    <a href="?page=members&action=edit&id=<?php echo $member['id']; ?>" class="btn btn-primary btn-sm">编辑</a>
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            onclick="confirmDelete(<?php echo $member['id']; ?>, '<?php echo htmlspecialchars($member['username']); ?>')">
                                        删除
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center">没有找到会员</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- 分页导航 -->
        <?php if ($total_pages > 1): ?>
            <style>
                .pagination {
                    display: flex;
                    justify-content: center;
                    list-style: none;
                    padding: 0;
                    margin: 20px 0 0 0;
                }
                .pagination li {
                    margin: 0 2px;
                }
                .pagination li a {
                    display: block;
                    padding: 8px 12px;
                    text-decoration: none;
                    color: #333;
                    background-color: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    transition: all 0.3s;
                }
                .pagination li a:hover {
                    background-color: #f0f7ff;
                    border-color: #3498db;
                }
                .pagination li a.active {
                    background-color: #3498db;
                    color: white;
                    border-color: #3498db;
                }
                .pagination-info {
                    text-align: center;
                    margin-top: 10px;
                    font-size: 14px;
                    color: #666;
                }
            </style>
            <div class="pagination-container">
                <ul class="pagination">
                    <?php if ($page > 1): ?>
                        <li><a href="?page=members&p=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&laquo; 首页</a></li>
                        <li><a href="?page=members&p=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">&lsaquo; 上一页</a></li>
                    <?php endif; ?>
                    
                    <?php
                    // 计算显示的页码范围
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li><a href="?page=members&p=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" <?php echo $i == $page ? 'class="active"' : ''; ?>><?php echo $i; ?></a></li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li><a href="?page=members&p=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">下一页 &rsaquo;</a></li>
                        <li><a href="?page=members&p=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">末页 &raquo;</a></li>
                    <?php endif; ?>
                </ul>
                <div class="pagination-info">
                    当前第 <?php echo $page; ?> 页，共 <?php echo $total_pages; ?> 页，每页 <?php echo $per_page; ?> 条记录
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- 删除确认对话框 -->
<div id="deleteModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5);">
    <style>
        .delete-modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 25px;
            border-radius: 8px;
            width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            animation: modalFadeIn 0.3s;
        }
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-20px);}
            to {opacity: 1; transform: translateY(0);}
        }
        .delete-modal-title {
            margin-top: 0;
            color: #333;
            font-size: 18px;
            font-weight: 600;
        }
        .delete-modal-message {
            margin: 15px 0;
            color: #555;
            line-height: 1.5;
        }
        .delete-modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        .delete-modal-cancel {
            padding: 8px 15px;
            background-color: #f5f5f5;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .delete-modal-cancel:hover {
            background-color: #e9e9e9;
        }
        .delete-modal-confirm {
            padding: 8px 15px;
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
        }
        .delete-modal-confirm:hover {
            background-color: #c0392b;
        }
    </style>
    <div class="delete-modal-content">
        <h3 class="delete-modal-title">确认删除</h3>
        <p class="delete-modal-message">您确定要删除会员 <span id="deleteMemberName" style="font-weight: bold;"></span> 吗？</p>
        <p class="delete-modal-message" style="color: #e74c3c;">注意：此操作将永久删除该会员的所有数据，且无法恢复。</p>
        <div class="delete-modal-buttons">
            <button onclick="closeDeleteModal()" class="delete-modal-cancel">取消</button>
            <a id="deleteConfirmButton" href="#" class="delete-modal-confirm">确认删除</a>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id, username) {
        document.getElementById('deleteMemberName').textContent = username;
        document.getElementById('deleteConfirmButton').href = '?page=members&action=delete&id=' + id;
        document.getElementById('deleteModal').style.display = 'block';
    }
    
    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }
    
    // 点击模态框外部关闭
    window.onclick = function(event) {
        var modal = document.getElementById('deleteModal');
        if (event.target == modal) {
            closeDeleteModal();
        }
    }
</script> 