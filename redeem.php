<?php
// 启用详细错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 添加调试日志
function debug_log($message) {
    error_log("[REDEEM DEBUG] " . $message);
}

// 记录脚本执行开始
debug_log("脚本开始执行");

// 启用输出缓冲，防止"headers already sent"错误
ob_start();

try {
    session_start();
    debug_log("会话已启动，会话ID: " . session_id());
    debug_log("会员ID: " . (isset($_SESSION['member_id']) ? $_SESSION['member_id'] : '未设置'));
    
    require_once 'member_system.php';
    debug_log("成功加载member_system.php");

    // 检查会员是否登录
    if (!isset($_SESSION['member_id'])) {
        debug_log("未登录，重定向到login.php");
        header('Location: login.php');
        exit;
    }

    // 初始化会员系统
    $memberSystem = new MemberSystem();
    debug_log("MemberSystem实例已创建");

    // 检查奖励ID是否存在
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        debug_log("未提供奖励ID，重定向到member.php");
        header('Location: member.php?error=invalid_request');
        exit;
    }

    $rewardId = (int)$_GET['id'];
    $memberId = $_SESSION['member_id'];
    debug_log("奖励ID: $rewardId, 会员ID: $memberId");

    // 获取会员信息
    $memberInfo = $memberSystem->getMemberInfo($memberId);
    debug_log("获取会员信息结果: " . ($memberInfo['success'] ? '成功' : '失败'));
    if (!$memberInfo['success']) {
        debug_log("获取会员信息失败，重定向到member.php");
        header('Location: member.php?error=member_error');
        exit;
    }

    // 获取奖品信息
    try {
        // 确保point_transactions表存在
        require_once 'includes/db_connect.php';
        $pdo = getDbConnection();
        
        // 检查point_transactions表是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE 'point_transactions'");
        $tableExists = $stmt->rowCount() > 0;
        
        if (!$tableExists) {
            // 创建point_transactions表
            debug_log("point_transactions表不存在，正在创建");
            $pdo->exec("
                CREATE TABLE `point_transactions` (
                  `id` int(11) NOT NULL AUTO_INCREMENT,
                  `member_id` int(11) NOT NULL,
                  `points` int(11) NOT NULL,
                  `description` varchar(255) DEFAULT NULL,
                  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  KEY `member_id` (`member_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            debug_log("point_transactions表已创建");
        }
        
        $stmt = $pdo->prepare("SELECT * FROM rewards WHERE id = ? AND is_active = 1");
        $stmt->execute([$rewardId]);
        $reward = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reward) {
            header('Location: member.php?error=reward_not_found');
            exit;
        }
    } catch (PDOException $e) {
        header('Location: member.php?error=db_error');
        exit;
    }

    // 确认页面
    $confirmRedemption = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';

    // 检查是否有足够的积分和库存
    $hasEnoughPoints = $memberInfo['member']['points'] >= $reward['points_cost'];
    $hasStock = $reward['quantity'] > 0 || $reward['quantity'] === null;

    // 处理兑换请求
    if ($confirmRedemption && $hasEnoughPoints && $hasStock) {
        try {
            // 开启事务
            $pdo->beginTransaction();
            
            // 扣除积分
            $newPoints = $memberInfo['member']['points'] - $reward['points_cost'];
            $stmt = $pdo->prepare("UPDATE members SET points = ? WHERE id = ?");
            $stmt->execute([$newPoints, $memberId]);
            
            // 记录积分变动
            $description = "兑换商品: " . $reward['name'];
            $stmt = $pdo->prepare("
                INSERT INTO point_transactions (member_id, points, description, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$memberId, -$reward['points_cost'], $description]);
            
            // 减少库存（如果有库存限制）
            if ($reward['quantity'] !== null) {
                $stmt = $pdo->prepare("UPDATE rewards SET quantity = quantity - 1 WHERE id = ? AND quantity > 0");
                $stmt->execute([$rewardId]);
            }
            
            // 创建兑换记录
            $stmt = $pdo->prepare("
                INSERT INTO reward_redemptions (member_id, reward_id, points_used, status, created_at, updated_at)
                VALUES (?, ?, ?, 'pending', NOW(), NOW())
            ");
            $stmt->execute([$memberId, $rewardId, $reward['points_cost']]);
            
            // 提交事务
            $pdo->commit();
            
            // 更新会话中的积分
            $_SESSION['member_points'] = $newPoints;
            
            // 重定向到成功页面
            header('Location: member.php?success=redemption_success');
            exit;
            
        } catch (PDOException $e) {
            // 回滚事务
            $pdo->rollBack();
            header('Location: member.php?error=redemption_failed&msg=' . urlencode($e->getMessage()));
            exit;
        }
    }

    // 生成HTML页面内容
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>兑换确认 - 无极导航</title>
        <link rel="stylesheet" href="css/style.css">
        <style>
            .redemption-container {
                max-width: 600px;
                margin: 50px auto;
                padding: 30px;
                background: var(--card-bg);
                border-radius: 12px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            }
            
            .redemption-header {
                text-align: center;
                margin-bottom: 25px;
            }
            
            .redemption-header h1 {
                font-size: 22px;
                margin-bottom: 10px;
            }
            
            .redemption-details {
                margin-bottom: 30px;
                padding: 20px;
                background: var(--input-bg);
                border-radius: 8px;
            }
            
            .detail-item {
                display: flex;
                justify-content: space-between;
                margin-bottom: 15px;
            }
            
            .detail-item:last-child {
                margin-bottom: 0;
                padding-top: 15px;
                border-top: 1px solid var(--border-color);
            }
            
            .confirm-buttons {
                display: flex;
                justify-content: space-between;
            }
            
            .confirm-buttons a {
                flex: 1;
                text-align: center;
                margin: 0 10px;
                padding: 12px 20px;
                border-radius: 6px;
                font-weight: 500;
                transition: background 0.3s;
            }
            
            .btn-confirm {
                background: var(--primary-color);
                color: white;
            }
            
            .btn-confirm:hover {
                background: #4261e4;
            }
            
            .btn-cancel {
                background: var(--input-bg);
                color: var(--text-color);
            }
            
            .btn-cancel:hover {
                background: var(--hover-color);
            }
            
            .insufficient {
                background: #dc3545;
                color: white;
                padding: 10px;
                border-radius: 6px;
                text-align: center;
                margin-bottom: 20px;
            }
            
            .not-available {
                background: #6c757d;
                color: white;
                padding: 10px;
                border-radius: 6px;
                text-align: center;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="redemption-container">
            <div class="redemption-header">
                <h1>兑换确认</h1>
                <p>请确认以下兑换信息</p>
            </div>
            
            <div class="redemption-details">
                <div class="detail-item">
                    <span>商品名称：</span>
                    <span><?php echo htmlspecialchars($reward['name']); ?></span>
                </div>
                <div class="detail-item">
                    <span>所需积分：</span>
                    <span><?php echo number_format($reward['points_cost']); ?> 积分</span>
                </div>
                <div class="detail-item">
                    <span>您当前的积分：</span>
                    <span><?php echo number_format($memberInfo['member']['points']); ?> 积分</span>
                </div>
                <div class="detail-item">
                    <span>兑换后剩余积分：</span>
                    <span><?php echo number_format($memberInfo['member']['points'] - $reward['points_cost']); ?> 积分</span>
                </div>
            </div>
            
            <?php if (!$hasEnoughPoints): ?>
            <div class="insufficient">
                您的积分不足，无法兑换该商品
            </div>
            <?php endif; ?>
            
            <?php if (!$hasStock): ?>
            <div class="not-available">
                该商品库存不足，暂时无法兑换
            </div>
            <?php endif; ?>
            
            <div class="confirm-buttons">
                <a href="member.php" class="btn-cancel">取消</a>
                <?php if ($hasEnoughPoints && $hasStock): ?>
                <a href="redeem.php?id=<?php echo $rewardId; ?>&confirm=yes" class="btn-confirm">确认兑换</a>
                <?php else: ?>
                <a href="#" class="btn-confirm" style="opacity:0.5;cursor:not-allowed;">确认兑换</a>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // 检查是否有保存的主题
                const themeToggle = document.getElementById('themeToggle');
                if (localStorage.getItem('theme') === 'dark') {
                    document.body.classList.add('dark-theme');
                }
            });
        </script>
        
        <?php
        // 记录脚本执行完成
        debug_log("脚本执行完成");
        
        // 输出缓冲区的内容
        ob_end_flush();
        ?>
    </body>
    </html>
    <?php
} catch (Exception $e) {
    // 记录错误
    error_log("Redeem.php错误: " . $e->getMessage() . " 在 " . $e->getFile() . " 第 " . $e->getLine() . " 行");
    
    // 显示友好的错误页面而不是500错误
    ob_end_clean(); // 清除之前的输出
    
    // 输出HTML头部
    echo '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>页面错误 - 无极导航</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .error-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .error-icon {
            font-size: 50px;
            margin-bottom: 20px;
            color: #dc3545;
        }
        
        .error-title {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--text-color);
        }
        
        .error-message {
            margin-bottom: 30px;
            color: var(--text-light);
        }
        
        .error-details {
            margin-top: 30px;
            padding: 15px;
            background: var(--input-bg);
            border-radius: 6px;
            text-align: left;
            font-family: monospace;
            font-size: 14px;
            color: #dc3545;
            overflow-x: auto;
        }
        
        .btn-return {
            display: inline-block;
            padding: 12px 25px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-return:hover {
            background-color: #4261e4;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1 class="error-title">页面遇到错误</h1>
        <div class="error-message">
            <p>抱歉，在处理您的请求时遇到问题。</p>
            <p>请稍后再试或联系网站管理员。</p>
        </div>
        
        <a href="member.php" class="btn-return">返回会员中心</a>';
    
    // 如果启用了调试，显示错误详情
    if (ini_get('display_errors')) {
        echo '<div class="error-details">
            <strong>错误信息:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>
            <strong>文件:</strong> ' . htmlspecialchars($e->getFile()) . '<br>
            <strong>行号:</strong> ' . $e->getLine() . '
        </div>';
    }
    
    echo '</div>
</body>
</html>';
}
?> 