<?php
require_once '../db_init.php';

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
$date_start = isset($_GET['date_start']) ? trim($_GET['date_start']) : '';
$date_end = isset($_GET['date_end']) ? trim($_GET['date_end']) : '';
$points_type = isset($_GET['points_type']) ? trim($_GET['points_type']) : '';

// 构建查询条件
$where_clause = [];
$params = [];

if (!empty($search)) {
    $where_clause[] = "(m.username LIKE ? OR m.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_start)) {
    $where_clause[] = "pt.created_at >= ?";
    $params[] = $date_start . ' 00:00:00';
}

if (!empty($date_end)) {
    $where_clause[] = "pt.created_at <= ?";
    $params[] = $date_end . ' 23:59:59';
}

if ($points_type === 'positive') {
    $where_clause[] = "pt.points > 0";
} elseif ($points_type === 'negative') {
    $where_clause[] = "pt.points < 0";
}

$where_sql = '';
if (!empty($where_clause)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clause);
}

// 获取总记录数
$total_count = 0;
try {
    $sql = "SELECT COUNT(*) 
            FROM point_transactions pt
            JOIN members m ON pt.member_id = m.id
            $where_sql";
    $stmt = $pdo->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
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

// 获取积分交易记录
$point_logs = [];
try {
    $sql = "SELECT pt.id, pt.member_id, m.username, m.email, pt.points, pt.description, pt.created_at
            FROM point_transactions pt
            JOIN members m ON pt.member_id = m.id
            $where_sql
            ORDER BY pt.created_at DESC
            LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $point_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $point_logs = [];
}

// 获取积分统计信息
$points_stats = [
    'total' => 0,
    'positive' => 0,
    'negative' => 0
];

try {
    // 总积分变动
    $sql = "SELECT 
                SUM(pt.points) as total_points,
                SUM(CASE WHEN pt.points > 0 THEN pt.points ELSE 0 END) as positive_points,
                SUM(CASE WHEN pt.points < 0 THEN pt.points ELSE 0 END) as negative_points
            FROM point_transactions pt
            JOIN members m ON pt.member_id = m.id
            $where_sql";
    $stmt = $pdo->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats) {
        $points_stats['total'] = $stats['total_points'] ?? 0;
        $points_stats['positive'] = $stats['positive_points'] ?? 0;
        $points_stats['negative'] = $stats['negative_points'] ?? 0;
    }
} catch (PDOException $e) {
    // 出错时保持默认值
}

?>

<!-- 搜索表单 -->
<div class="card">
    <div class="card-header">
        <h3>积分交易日志</h3>
    </div>
    <div class="card-body">
        <form action="" method="get" class="search-form">
            <input type="hidden" name="page" value="point_logs">
            
            <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <div style="flex: 1; min-width: 200px;">
                    <label for="search">会员名称/邮箱</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="搜索会员" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div style="flex: 1; min-width: 150px;">
                    <label for="date_start">开始日期</label>
                    <input type="date" name="date_start" id="date_start" class="form-control" value="<?php echo htmlspecialchars($date_start); ?>">
                </div>
                
                <div style="flex: 1; min-width: 150px;">
                    <label for="date_end">结束日期</label>
                    <input type="date" name="date_end" id="date_end" class="form-control" value="<?php echo htmlspecialchars($date_end); ?>">
                </div>
                
                <div style="flex: 1; min-width: 150px;">
                    <label for="points_type">积分类型</label>
                    <select name="points_type" id="points_type" class="form-control">
                        <option value="" <?php echo $points_type === '' ? 'selected' : ''; ?>>全部</option>
                        <option value="positive" <?php echo $points_type === 'positive' ? 'selected' : ''; ?>>积分增加</option>
                        <option value="negative" <?php echo $points_type === 'negative' ? 'selected' : ''; ?>>积分减少</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="?page=point_logs" class="btn btn-secondary">重置</a>
            </div>
        </form>
    </div>
</div>

<!-- 统计卡片 -->
<div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
    <div class="stat-card" style="flex: 1; min-width: 200px; background-color: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
        <div style="font-size: 16px; color: #666;">总积分变动</div>
        <div style="font-size: 24px; font-weight: bold; margin-top: 5px; color: var(--primary-color);">
            <?php echo number_format($points_stats['total']); ?>
        </div>
    </div>
    
    <div class="stat-card" style="flex: 1; min-width: 200px; background-color: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
        <div style="font-size: 16px; color: #666;">总积分增加</div>
        <div style="font-size: 24px; font-weight: bold; margin-top: 5px; color: #28a745;">
            +<?php echo number_format($points_stats['positive']); ?>
        </div>
    </div>
    
    <div class="stat-card" style="flex: 1; min-width: 200px; background-color: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
        <div style="font-size: 16px; color: #666;">总积分减少</div>
        <div style="font-size: 24px; font-weight: bold; margin-top: 5px; color: #dc3545;">
            <?php echo number_format($points_stats['negative']); ?>
        </div>
    </div>
</div>

<!-- 积分交易日志列表 -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>积分记录</h3>
        <div>共 <?php echo number_format($total_count); ?> 条记录</div>
    </div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>会员名称</th>
                    <th>邮箱</th>
                    <th>积分变动</th>
                    <th>描述</th>
                    <th>时间</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($point_logs)): ?>
                    <?php foreach ($point_logs as $log): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td>
                                <a href="?page=members&action=edit&id=<?php echo $log['member_id']; ?>">
                                    <?php echo htmlspecialchars($log['username']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($log['email']); ?></td>
                            <td style="color: <?php echo $log['points'] > 0 ? '#28a745' : '#dc3545'; ?>; font-weight: bold;">
                                <?php echo $log['points'] > 0 ? '+' . $log['points'] : $log['points']; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['description']); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">暂无数据</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- 分页导航 -->
        <?php if ($total_pages > 1): ?>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li><a href="?page=point_logs&p=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?><?php echo !empty($points_type) ? '&points_type=' . urlencode($points_type) : ''; ?>">&laquo;</a></li>
                    <li><a href="?page=point_logs&p=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?><?php echo !empty($points_type) ? '&points_type=' . urlencode($points_type) : ''; ?>">&lsaquo;</a></li>
                <?php endif; ?>
                
                <?php
                // 计算显示的页码范围
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li><a href="?page=point_logs&p=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?><?php echo !empty($points_type) ? '&points_type=' . urlencode($points_type) : ''; ?>" <?php echo $i == $page ? 'class="active"' : ''; ?>><?php echo $i; ?></a></li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li><a href="?page=point_logs&p=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?><?php echo !empty($points_type) ? '&points_type=' . urlencode($points_type) : ''; ?>">&rsaquo;</a></li>
                    <li><a href="?page=point_logs&p=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?><?php echo !empty($points_type) ? '&points_type=' . urlencode($points_type) : ''; ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
</div> 