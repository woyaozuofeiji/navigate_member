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

// 数据库连接 - 使用绝对路径
require_once dirname(dirname(__DIR__)) . '/includes/db_connect.php';

// 获取数据库连接
$pdo = getDbConnection();

// 当前操作类型
$action = isset($_GET['action']) ? $_GET['action'] : '';
$reward_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 初始化消息
$message = '';
$message_type = '';

// 处理删除操作
if ($action === 'delete' && $reward_id > 0) {
    try {
        $stmt = $pdo->prepare("DELETE FROM rewards WHERE id = ?");
        $result = $stmt->execute([$reward_id]);
        
        if ($result) {
            $message = '商品删除成功';
            $message_type = 'success';
        } else {
            $message = '商品删除失败';
            $message_type = 'error';
        }
    } catch (PDOException $e) {
        $message = '商品删除失败: ' . $e->getMessage();
        $message_type = 'error';
    }
    
    // 重定向以避免刷新页面时重复删除
    header('Location: ?page=rewards&msg=' . urlencode($message) . '&type=' . urlencode($message_type));
    exit;
}

// 从URL获取消息
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = $_GET['msg'];
    $message_type = $_GET['type'];
}

// 处理表单提交 - 添加或更新商品
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $points_cost = isset($_POST['points_cost']) ? (int)$_POST['points_cost'] : 0;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $image_url = isset($_POST['image_url']) ? trim($_POST['image_url']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : 'other';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // 验证数据
    $errors = [];
    
    if (empty($name)) {
        $errors[] = '商品名称不能为空';
    }
    
    if ($points_cost <= 0) {
        $errors[] = '积分值必须大于0';
    }
    
    if (empty($errors)) {
        try {
            // 新增或更新商品
            if (isset($_POST['id']) && (int)$_POST['id'] > 0) {
                // 更新
                $stmt = $pdo->prepare("
                    UPDATE rewards 
                    SET name = ?, description = ?, points_cost = ?, quantity = ?, image_url = ?, category = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $result = $stmt->execute([$name, $description, $points_cost, $quantity, $image_url, $category, $is_active, (int)$_POST['id']]);
                
                if ($result) {
                    $message = '商品更新成功';
                    $message_type = 'success';
                } else {
                    $message = '商品更新失败';
                    $message_type = 'error';
                }
            } else {
                // 新增
                $stmt = $pdo->prepare("
                    INSERT INTO rewards (name, description, points_cost, quantity, image_url, category, is_active, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $result = $stmt->execute([$name, $description, $points_cost, $quantity, $image_url, $category, $is_active]);
                
                if ($result) {
                    $message = '商品添加成功';
                    $message_type = 'success';
                } else {
                    $message = '商品添加失败';
                    $message_type = 'error';
                }
            }
            
            // 重定向以避免表单重复提交
            header('Location: ?page=rewards&msg=' . urlencode($message) . '&type=' . urlencode($message_type));
            exit;
        } catch (PDOException $e) {
            $message = '操作失败: ' . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $message_type = 'error';
    }
}

// 获取商品列表
$rewards = [];
try {
    $stmt = $pdo->query("
        SELECT id, name, description, points_cost, quantity, image_url, is_active, created_at, updated_at
        FROM rewards
        ORDER BY created_at DESC
    ");
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = '获取商品列表失败: ' . $e->getMessage();
    $message_type = 'error';
}

// 获取单个商品信息用于编辑
$edit_reward = null;
if ($action === 'edit' && $reward_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM rewards WHERE id = ?");
        $stmt->execute([$reward_id]);
        $edit_reward = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $message = '获取商品信息失败: ' . $e->getMessage();
        $message_type = 'error';
    }
}

// 检查rewards表是否存在，如果不存在则创建
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'rewards'");
    if ($stmt->rowCount() == 0) {
        // 表不存在，创建表
        $pdo->exec("
            CREATE TABLE `rewards` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(255) NOT NULL,
              `description` text DEFAULT NULL,
              `points_cost` int(11) NOT NULL,
              `quantity` int(11) DEFAULT 0,
              `image_url` varchar(255) DEFAULT NULL,
              `category` varchar(50) DEFAULT 'other',
              `is_active` tinyint(1) DEFAULT 1,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // 创建兑换记录表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `reward_redemptions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `member_id` int(11) NOT NULL,
              `reward_id` int(11) NOT NULL,
              `points_used` int(11) NOT NULL,
              `status` varchar(50) NOT NULL DEFAULT 'pending',
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `member_id` (`member_id`),
              KEY `reward_id` (`reward_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        $message = '奖品表创建成功，您现在可以开始添加奖品了';
        $message_type = 'success';
    } else {
        // 检查是否需要添加 category 字段
        $stmt = $pdo->query("SHOW COLUMNS FROM rewards LIKE 'category'");
        if ($stmt->rowCount() == 0) {
            // 添加 category 字段
            $pdo->exec("ALTER TABLE rewards ADD COLUMN category varchar(50) DEFAULT 'other' AFTER image_url");
            $message = '奖品表已更新，添加了分类字段';
            $message_type = 'success';
        }
    }
} catch (PDOException $e) {
    $message = '检查数据表时出错: ' . $e->getMessage();
    $message_type = 'error';
}
?>

<!-- 页面标题 -->
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h3><?php echo $edit_reward ? '编辑商品' : ($action === 'add' ? '添加新商品' : '积分商品管理'); ?></h3>
    </div>
    
    <!-- 消息提示 -->
    <?php if (!empty($message)): ?>
        <div class="alert <?php echo $message_type === 'success' ? 'alert-success' : 'alert-danger'; ?>" style="margin: 20px; padding: 10px; border-radius: 4px; background-color: <?php echo $message_type === 'success' ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $message_type === 'success' ? '#155724' : '#721c24'; ?>;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <!-- 添加/编辑表单 -->
        <div class="card-body">
            <form action="" method="post">
                <?php if ($edit_reward): ?>
                    <input type="hidden" name="id" value="<?php echo $edit_reward['id']; ?>">
                <?php endif; ?>
                
                <div class="row" style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <div class="form-group" style="flex: 2; min-width: 250px;">
                        <label for="name">商品名称</label>
                        <input type="text" name="name" id="name" class="form-control" value="<?php echo $edit_reward ? htmlspecialchars($edit_reward['name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group" style="flex: 1; min-width: 100px;">
                        <label for="points_cost">所需积分</label>
                        <input type="number" name="points_cost" id="points_cost" class="form-control" value="<?php echo $edit_reward ? $edit_reward['points_cost'] : ''; ?>" required>
                    </div>
                    
                    <div class="form-group" style="flex: 1; min-width: 100px;">
                        <label for="quantity">库存数量</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" value="<?php echo $edit_reward ? $edit_reward['quantity'] : '0'; ?>">
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label for="description">商品描述</label>
                    <textarea name="description" id="description" class="form-control" rows="4"><?php echo $edit_reward ? htmlspecialchars($edit_reward['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label for="image_url">图片URL</label>
                    <input type="text" name="image_url" id="image_url" class="form-control" value="<?php echo $edit_reward ? htmlspecialchars($edit_reward['image_url']) : ''; ?>">
                    <small class="text-muted">请输入商品图片的完整URL地址</small>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <label for="category">商品分类</label>
                    <select name="category" id="category" class="form-control">
                        <option value="crypto" <?php echo ($edit_reward && $edit_reward['category'] == 'crypto') ? 'selected' : ''; ?>>数字货币</option>
                        <option value="physical" <?php echo ($edit_reward && $edit_reward['category'] == 'physical') ? 'selected' : ''; ?>>实物奖励</option>
                        <option value="service" <?php echo ($edit_reward && $edit_reward['category'] == 'service') ? 'selected' : ''; ?>>服务类</option>
                        <option value="other" <?php echo ($edit_reward && $edit_reward['category'] == 'other') || !$edit_reward ? 'selected' : ''; ?>>其他</option>
                    </select>
                </div>
                
                <div class="form-check" style="margin-top: 20px;">
                    <input type="checkbox" name="is_active" id="is_active" class="form-check-input" <?php echo ($edit_reward && $edit_reward['is_active']) || !$edit_reward ? 'checked' : ''; ?>>
                    <label for="is_active" class="form-check-label">启用此商品</label>
                </div>
                
                <div style="margin-top: 30px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary"><?php echo $edit_reward ? '更新商品' : '添加商品'; ?></button>
                    <a href="?page=rewards" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
    <?php else: ?>
        <!-- 工具栏 -->
        <div class="card-body" style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <a href="?page=rewards&action=add" class="btn btn-primary">添加新商品</a>
            </div>
        </div>
        
        <!-- 商品列表 -->
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>商品名称</th>
                    <th>所需积分</th>
                    <th>分类</th>
                    <th>库存</th>
                    <th>状态</th>
                    <th>创建时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rewards)): ?>
                    <?php foreach ($rewards as $reward): ?>
                        <tr>
                            <td><?php echo $reward['id']; ?></td>
                            <td><?php echo htmlspecialchars($reward['name']); ?></td>
                            <td><?php echo number_format($reward['points_cost']); ?></td>
                            <td>
                                <?php
                                // 确保category字段存在，如果不存在则使用默认值'other'
                                $category = isset($reward['category']) ? $reward['category'] : 'other';
                                switch($category) {
                                    case 'crypto':
                                        echo '数字货币';
                                        break;
                                    case 'physical':
                                        echo '实物奖励';
                                        break;
                                    case 'service':
                                        echo '服务类';
                                        break;
                                    default:
                                        echo '其他';
                                }
                                ?>
                            </td>
                            <td><?php echo number_format($reward['quantity']); ?></td>
                            <td>
                                <?php if ($reward['is_active']): ?>
                                    <span style="color: #28a745; font-weight: bold;">启用</span>
                                <?php else: ?>
                                    <span style="color: #dc3545; font-weight: bold;">禁用</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($reward['created_at'])); ?></td>
                            <td>
                                <a href="?page=rewards&action=edit&id=<?php echo $reward['id']; ?>" class="btn btn-sm btn-primary">编辑</a>
                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $reward['id']; ?>, '<?php echo htmlspecialchars($reward['name'], ENT_QUOTES); ?>')" class="btn btn-sm btn-danger">删除</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center;">暂无商品数据</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- 删除确认对话框 -->
<div id="deleteModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5);">
    <div style="background-color: white; margin: 15% auto; padding: 20px; border-radius: 8px; width: 400px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);">
        <h3 style="margin-top: 0;">确认删除</h3>
        <p>您确定要删除商品 <span id="deleteRewardName" style="font-weight: bold;"></span> 吗？此操作不可恢复。</p>
        <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
            <button onclick="closeDeleteModal()" class="btn btn-secondary">取消</button>
            <a id="deleteConfirmButton" href="#" class="btn btn-danger">删除</a>
        </div>
    </div>
</div>

<script>
    function confirmDelete(id, name) {
        document.getElementById('deleteRewardName').textContent = name;
        document.getElementById('deleteConfirmButton').href = '?page=rewards&action=delete&id=' + id;
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

<?php
// 输出所有缓冲的内容并关闭缓冲
ob_end_flush();
?> 