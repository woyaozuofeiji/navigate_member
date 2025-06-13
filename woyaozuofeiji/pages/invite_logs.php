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

// 构建查询条件
$where_clause = [];
$params = [];

if (!empty($search)) {
    $where_clause[] = "(inviter.username LIKE ? OR inviter.email LIKE ? OR invited.username LIKE ? OR invited.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($date_start)) {
    $where_clause[] = "invited.created_at >= ?";
    $params[] = $date_start . ' 00:00:00';
}

if (!empty($date_end)) {
    $where_clause[] = "invited.created_at <= ?";
    $params[] = $date_end . ' 23:59:59';
}

$where_sql = '';
if (!empty($where_clause)) {
    $where_sql = ' WHERE ' . implode(' AND ', $where_clause);
}

// 获取总记录数
$total_count = 0;
try {
    $sql = "SELECT COUNT(*) 
            FROM members invited
            JOIN members inviter ON invited.referrer_id = inviter.id
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

// 获取邀请记录
$invite_logs = [];
try {
    $sql = "SELECT 
                inviter.id as inviter_id, 
                inviter.username as inviter_username, 
                inviter.email as inviter_email,
                invited.id as invited_id,
                invited.username as invited_username,
                invited.email as invited_email,
                invited.created_at as invite_time
            FROM members invited
            JOIN members inviter ON invited.referrer_id = inviter.id
            $where_sql
            ORDER BY invited.created_at DESC
            LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $invite_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $invite_logs = [];
}

// 获取邀请统计信息
$invite_stats = [
    'total_invites' => 0,
    'today_invites' => 0,
    'top_inviters' => []
];

try {
    // 总邀请数
    $sql = "SELECT COUNT(*) FROM members WHERE referrer_id IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $invite_stats['total_invites'] = $stmt->fetchColumn();

    // 今日邀请数
    $sql = "SELECT COUNT(*) FROM members WHERE referrer_id IS NOT NULL AND DATE(created_at) = CURRENT_DATE()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $invite_stats['today_invites'] = $stmt->fetchColumn();

    // 邀请人排行榜
    $sql = "SELECT 
                inviter.id,
                inviter.username,
                COUNT(invited.id) as invite_count
            FROM members inviter
            JOIN members invited ON invited.referrer_id = inviter.id
            GROUP BY inviter.id
            ORDER BY invite_count DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $invite_stats['top_inviters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // 出错时保持默认值
}

?>

<!-- 搜索表单 -->
<div class="card">
    <div class="card-header">
        <h3>邀请记录</h3>
    </div>
    <div class="card-body">
        <form action="" method="get" class="search-form">
            <input type="hidden" name="page" value="invite_logs">
            
            <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
                <div style="flex: 1; min-width: 200px;">
                    <label for="search">搜索用户</label>
                    <input type="text" name="search" id="search" class="form-control" placeholder="邀请人或被邀请人的用户名/邮箱" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div style="flex: 1; min-width: 150px;">
                    <label for="date_start">开始日期</label>
                    <input type="date" name="date_start" id="date_start" class="form-control" value="<?php echo htmlspecialchars($date_start); ?>">
                </div>
                
                <div style="flex: 1; min-width: 150px;">
                    <label for="date_end">结束日期</label>
                    <input type="date" name="date_end" id="date_end" class="form-control" value="<?php echo htmlspecialchars($date_end); ?>">
                </div>
            </div>
            
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="?page=invite_logs" class="btn btn-secondary">重置</a>
            </div>
        </form>
    </div>
</div>

<!-- 统计卡片 -->
<div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
    <div class="stat-card" style="flex: 1; min-width: 200px; background-color: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
        <div style="font-size: 16px; color: #666;">总邀请人数</div>
        <div style="font-size: 24px; font-weight: bold; margin-top: 5px; color: var(--primary-color);">
            <?php echo number_format($invite_stats['total_invites']); ?>
        </div>
    </div>
    
    <div class="stat-card" style="flex: 1; min-width: 200px; background-color: #fff; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);">
        <div style="font-size: 16px; color: #666;">今日邀请人数</div>
        <div style="font-size: 24px; font-weight: bold; margin-top: 5px; color: #28a745;">
            <?php echo number_format($invite_stats['today_invites']); ?>
        </div>
    </div>
</div>

<!-- 邀请人排行榜 -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>邀请排行榜</h3>
    </div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>排名</th>
                    <th>用户名</th>
                    <th>邀请人数</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invite_stats['top_inviters'])): ?>
                    <?php foreach ($invite_stats['top_inviters'] as $key => $inviter): ?>
                        <tr>
                            <td><?php echo $key + 1; ?></td>
                            <td>
                                <a href="?page=members&action=edit&id=<?php echo $inviter['id']; ?>">
                                    <?php echo htmlspecialchars($inviter['username']); ?>
                                </a>
                            </td>
                            <td><?php echo number_format($inviter['invite_count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">暂无数据</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 邀请记录列表 -->
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3>邀请记录</h3>
        <div>共 <?php echo number_format($total_count); ?> 条记录</div>
    </div>
    <div class="card-body">
        <table class="data-table">
            <thead>
                <tr>
                    <th>邀请人</th>
                    <th>邀请人邮箱</th>
                    <th>被邀请人</th>
                    <th>被邀请人邮箱</th>
                    <th>注册时间</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invite_logs)): ?>
                    <?php foreach ($invite_logs as $log): ?>
                        <tr>
                            <td>
                                <a href="?page=members&action=edit&id=<?php echo $log['inviter_id']; ?>">
                                    <?php echo htmlspecialchars($log['inviter_username']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($log['inviter_email']); ?></td>
                            <td>
                                <a href="?page=members&action=edit&id=<?php echo $log['invited_id']; ?>">
                                    <?php echo htmlspecialchars($log['invited_username']); ?>
                                </a>
                            </td>
                            <td><?php echo htmlspecialchars($log['invited_email']); ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($log['invite_time'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center;">暂无数据</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- 分页导航 -->
        <?php if ($total_pages > 1): ?>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li><a href="?page=invite_logs&p=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?>">&laquo;</a></li>
                    <li><a href="?page=invite_logs&p=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?>">&lsaquo;</a></li>
                <?php endif; ?>
                
                <?php
                // 计算显示的页码范围
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <li><a href="?page=invite_logs&p=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?>" <?php echo $i == $page ? 'class="active"' : ''; ?>><?php echo $i; ?></a></li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <li><a href="?page=invite_logs&p=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?>">&rsaquo;</a></li>
                    <li><a href="?page=invite_logs&p=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_start) ? '&date_start=' . urlencode($date_start) : ''; ?><?php echo !empty($date_end) ? '&date_end=' . urlencode($date_end) : ''; ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </div>
</div> 