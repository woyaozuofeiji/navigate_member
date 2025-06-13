<?php
// 启用详细错误报告
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Redeem.php 调试页面</h2>";

// 1. 检查PHP版本
echo "<h3>1. PHP环境检查</h3>";
echo "<p>PHP版本: " . phpversion() . "</p>";
echo "<p>已加载扩展: ";
$extensions = get_loaded_extensions();
echo implode(', ', array_slice($extensions, 0, 10)) . "... 等" . count($extensions) . "个扩展</p>";

// 2. 检查会话状态
echo "<h3>2. 会话状态检查</h3>";
session_start();
echo "<p>会话ID: " . session_id() . "</p>";
echo "<p>会员ID是否存在: " . (isset($_SESSION['member_id']) ? "是 (ID: ".$_SESSION['member_id'].")" : "否") . "</p>";

// 3. 检查数据库连接
echo "<h3>3. 数据库连接检查</h3>";
try {
    require_once 'includes/db_connect.php';
    $pdo = getDbConnection();
    echo "<p style='color:green'>数据库连接成功</p>";
    
    // 检查表结构
    echo "<h3>4. 表结构检查</h3>";
    
    // 检查rewards表
    try {
        $stmt = $pdo->query("DESCRIBE rewards");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p style='color:green'>rewards表结构正常，包含 " . count($columns) . " 列</p>";
        echo "<p>列名: " . implode(', ', $columns) . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>rewards表检查失败: " . $e->getMessage() . "</p>";
    }
    
    // 检查reward_redemptions表
    try {
        $stmt = $pdo->query("DESCRIBE reward_redemptions");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p style='color:green'>reward_redemptions表结构正常，包含 " . count($columns) . " 列</p>";
        echo "<p>列名: " . implode(', ', $columns) . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>reward_redemptions表检查失败: " . $e->getMessage() . "</p>";
    }
    
    // 5. 检查积分交易表是否存在
    try {
        $stmt = $pdo->query("DESCRIBE point_transactions");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<p style='color:green'>point_transactions表结构正常，包含 " . count($columns) . " 列</p>";
        echo "<p>列名: " . implode(', ', $columns) . "</p>";
    } catch (PDOException $e) {
        echo "<p style='color:red'>point_transactions表可能不存在: " . $e->getMessage() . "</p>";
        
        // 如果point_transactions表不存在，提供创建SQL
        echo "<h4>创建point_transactions表的SQL</h4>";
        echo "<pre>
CREATE TABLE `point_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        </pre>";
    }
    
    // 6. 模拟redeem.php的主要操作
    echo "<h3>5. 模拟redeem.php的主要操作</h3>";
    
    // 检查是否有奖励ID
    if (isset($_GET['id']) && !empty($_GET['id'])) {
        $rewardId = (int)$_GET['id'];
        
        // 检查奖励是否存在
        $stmt = $pdo->prepare("SELECT * FROM rewards WHERE id = ?");
        $stmt->execute([$rewardId]);
        $reward = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($reward) {
            echo "<p style='color:green'>成功获取ID为 $rewardId 的奖励信息</p>";
            echo "<pre>";
            print_r($reward);
            echo "</pre>";
        } else {
            echo "<p style='color:red'>未找到ID为 $rewardId 的奖励</p>";
        }
    } else {
        echo "<p style='color:orange'>未提供奖励ID，请通过 ?id=数字 参数访问</p>";
    }
    
    // 7. 检查redeem.php文件
    echo "<h3>6. redeem.php文件检查</h3>";
    if (file_exists('redeem.php')) {
        echo "<p style='color:green'>redeem.php文件存在</p>";
        
        // 列出文件前20行进行检查
        $lines = file('redeem.php');
        if ($lines !== false) {
            echo "<p>文件前20行:</p>";
            echo "<pre>";
            for ($i = 0; $i < min(20, count($lines)); $i++) {
                echo htmlspecialchars($lines[$i]);
            }
            echo "</pre>";
        }
    } else {
        echo "<p style='color:red'>redeem.php文件不存在</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>数据库连接失败: " . $e->getMessage() . "</p>";
}

// 8. 检查point_transactions表并创建
echo "<h3>7. 尝试创建缺失的表</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'point_transactions'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        try {
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
            echo "<p style='color:green'>point_transactions表已成功创建</p>";
        } catch (PDOException $e) {
            echo "<p style='color:red'>创建point_transactions表失败: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p style='color:blue'>point_transactions表已存在</p>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red'>检查point_transactions表错误: " . $e->getMessage() . "</p>";
}

// 提供解决方案
echo "<h3>可能的解决方案</h3>";
echo "<ol>
<li>确保已登录 - 查看会话中是否有 member_id</li>
<li>检查所有必要的表是否已创建 - 特别是 point_transactions 表</li>
<li>检查PHP版本兼容性 - redeem.php 可能使用了更新版本的PHP语法</li>
<li>确保rewards表中有可兑换的商品记录</li>
</ol>";

echo "<div style='margin-top: 30px;'>
    <a href='fix_redeem_tables.php' style='display: inline-block; margin-right: 15px; padding: 10px 20px; background: #4261e4; color: white; text-decoration: none; border-radius: 5px;'>重新运行修复脚本</a>
    <a href='member.php' style='display: inline-block; margin-right: 15px; padding: 10px 20px; background: #38b2ac; color: white; text-decoration: none; border-radius: 5px;'>返回会员中心</a>
</div>";
?> 