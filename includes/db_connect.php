<?php
/**
 * 数据库连接文件
 * 用于在整个应用程序中提供一致的数据库连接
 */

// 引入配置文件
require_once __DIR__ . '/config.php';

/**
 * 获取数据库连接
 * 
 * @param bool $create_if_not_exists 如果数据库不存在是否创建
 * @return PDO 返回一个PDO连接实例
 */
function getDbConnection($create_if_not_exists = true) {
    static $pdo = null;
    
    if ($pdo === null) {
        // 从配置文件获取数据库连接参数
        global $currentConfig;
        $db = $currentConfig['db'];
        
        $host = $db['host'];
        $port = $db['port'];
        $dbname = $db['name'];
        $username = $db['user'];
        $password = $db['pass'];
        $charset = $db['charset'];
        
        try {
            // 标准PDO连接选项
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 5, // 5秒超时
            ];
            
            // 首先尝试直接连接到完整数据库
            try {
                $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
                $pdo = new PDO($dsn, $username, $password, $options);
                error_log("MySQL连接成功：连接到 $host:$port/$dbname");
                
                // 如果需要，创建表结构
                if ($create_if_not_exists) {
                    createTablesIfNotExist($pdo);
                }
                
                return $pdo;
            } catch (PDOException $e) {
                // 如果连接到指定数据库失败，尝试仅连接到MySQL服务器
                error_log("连接到指定数据库失败: " . $e->getMessage());
                
                // 检查是否是因为数据库不存在
                $server_dsn = "mysql:host=$host;port=$port;charset=$charset";
                $server_pdo = new PDO($server_dsn, $username, $password, $options);
                
                // 检查数据库是否存在
                $stmt = $server_pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
                $db_exists = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // 如果数据库不存在且设置了自动创建
                if (!$db_exists && $create_if_not_exists) {
                    $server_pdo->exec("CREATE DATABASE `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
                    error_log("数据库 '$dbname' 已创建");
                } else if (!$db_exists) {
                    throw new PDOException("数据库 '$dbname' 不存在，且未设置自动创建");
                }
                
                // 重新连接到创建的数据库
                $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=$charset", $username, $password, $options);
                
                // 创建必要的表
                if ($create_if_not_exists) {
                    createTablesIfNotExist($pdo);
                }
                
                return $pdo;
            }
        } catch (PDOException $e) {
            // 完整的错误信息
            $error_msg = "数据库连接错误: " . $e->getMessage();
            error_log($error_msg);
            
            // 用户友好的错误显示
            $user_msg = "<div style='padding:20px; background:#f8d7da; color:#721c24; border-radius:5px; margin:20px auto; max-width:800px; font-family:Arial,sans-serif;'>";
            $user_msg .= "<h3 style='margin-top:0'>数据库连接错误</h3>";
            $user_msg .= "<p>无法连接到数据库服务器。请确保MySQL服务正在运行且配置正确。</p>";
            // $user_msg .= "<p>错误详情：" . htmlspecialchars($e->getMessage()) . "</p>";
            $user_msg .= "<p style='margin-top:20px;'>";
            // $user_msg .= "<a href='db_test.php' style='display:inline-block; padding:10px 15px; background:#0275d8; color:white; text-decoration:none; border-radius:4px;'>运行数据库诊断工具</a>";
            $user_msg .= "</p>";
            $user_msg .= "</div>";
            
            die($user_msg);
        }
    }
    
    return $pdo;
}

/**
 * 创建系统所需的数据表
 * 
 * @param PDO $pdo 数据库连接
 */
function createTablesIfNotExist($pdo) {
    if (!$pdo) {
        error_log("createTablesIfNotExist: 无效的数据库连接");
        return;
    }
    
    try {
        // 创建会员表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `members` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `username` varchar(255) NOT NULL,
              `email` varchar(255) NOT NULL,
              `password` varchar(255) NOT NULL,
              `points` int(11) NOT NULL DEFAULT 0,
              `referrer_id` int(11) DEFAULT NULL,
              `invite_code` varchar(20) DEFAULT NULL,
              `is_active` tinyint(1) DEFAULT 1,
              `register_ip` varchar(45) DEFAULT NULL,
              `last_login` datetime DEFAULT NULL,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `email` (`email`),
              UNIQUE KEY `invite_code` (`invite_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // 检查last_login列是否存在，如果不存在则添加
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM members LIKE 'last_login'")->fetchAll();
            if (empty($columns)) {
                $pdo->exec("ALTER TABLE members ADD COLUMN last_login datetime DEFAULT NULL");
                error_log("已添加last_login列到members表结构中");
            }
        } catch (PDOException $e) {
            error_log("检查/添加last_login列失败: " . $e->getMessage());
        }
        
        // 检查register_ip列是否存在，如果不存在则添加
        try {
            $columns = $pdo->query("SHOW COLUMNS FROM members LIKE 'register_ip'")->fetchAll();
            if (empty($columns)) {
                $pdo->exec("ALTER TABLE members ADD COLUMN register_ip varchar(45) DEFAULT NULL");
                error_log("已添加register_ip列到members表结构中");
            }
        } catch (PDOException $e) {
            error_log("检查/添加register_ip列失败: " . $e->getMessage());
        }
        
        // 创建积分交易表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `point_transactions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `member_id` int(11) NOT NULL,
              `points` int(11) NOT NULL,
              `description` varchar(255) DEFAULT NULL,
              `created_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `member_id` (`member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // 创建奖品表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `rewards` (
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
        
        // 创建奖品兑换表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `reward_redemptions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `member_id` int(11) NOT NULL,
              `reward_id` int(11) NOT NULL,
              `points_used` int(11) NOT NULL,
              `status` varchar(50) NOT NULL DEFAULT 'pending',
              `admin_notes` text DEFAULT NULL,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `member_id` (`member_id`),
              KEY `reward_id` (`reward_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // 创建登录日志表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `login_logs` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `member_id` int(11) DEFAULT NULL,
              `username` varchar(255) DEFAULT NULL,
              `ip_address` varchar(45) DEFAULT NULL,
              `user_agent` text DEFAULT NULL,
              `status` varchar(20) DEFAULT NULL COMMENT '登录状态，如success、failed',
              `created_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `member_id` (`member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // 创建签到记录表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `daily_sign_ins` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `member_id` int(11) NOT NULL,
              `sign_date` date NOT NULL,
              `points_rewarded` int(11) NOT NULL DEFAULT 20,
              `created_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `member_sign_date` (`member_id`, `sign_date`),
              KEY `member_id` (`member_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // 创建积分规则配置表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `point_rules` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `rule_name` varchar(50) NOT NULL,
              `rule_key` varchar(50) NOT NULL,
              `points` int(11) NOT NULL DEFAULT 0,
              `description` text DEFAULT NULL,
              `is_active` tinyint(1) DEFAULT 1,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `rule_key` (`rule_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // 检查积分规则是否存在，如果不存在则创建默认规则
        $stmt = $pdo->query("SELECT COUNT(*) FROM point_rules");
        $rules_count = $stmt->fetchColumn();
        
        if ($rules_count == 0) {
            $current_time = date('Y-m-d H:i:s');
            $default_rules = [
                ['新用户注册奖励', 'register_bonus', 20, '新用户首次注册可获得20积分'],
                ['邀请注册奖励', 'invite_bonus', 50, '成功邀请新用户注册可获得50积分'],
                ['每日签到奖励', 'daily_signin', 20, '每日登录签到可获得20积分']
            ];
            
            $stmt = $pdo->prepare("
                INSERT INTO point_rules (rule_name, rule_key, points, description, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, 1, ?, ?)
            ");
            
            foreach ($default_rules as $rule) {
                $stmt->execute([$rule[0], $rule[1], $rule[2], $rule[3], $current_time, $current_time]);
            }
            
            error_log("已创建默认积分规则");
        }
        
        // 创建管理员表
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `admins` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `username` varchar(255) NOT NULL,
              `password` varchar(255) NOT NULL,
              `is_active` tinyint(1) DEFAULT 1,
              `last_login` datetime DEFAULT NULL,
              `created_at` datetime DEFAULT NULL,
              `updated_at` datetime DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `username` (`username`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ");
        
        // 检查管理员是否存在，如果不存在则创建默认管理员
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
        $admin_count = $stmt->fetchColumn();
        
        if ($admin_count == 0) {
            // 创建默认管理员账号 (admin/admin123)
            $admin_username = 'arms';
            $admin_password = password_hash('456123.0', PASSWORD_DEFAULT);
            $current_time = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("
                INSERT INTO admins (username, password, is_active, created_at, updated_at)
                VALUES (?, ?, 1, ?, ?)
            ");
            $stmt->execute([$admin_username, $admin_password, $current_time, $current_time]);
            
            error_log("已创建默认管理员账号: username=arms, password=456123.0");
        }
        
    } catch (PDOException $e) {
        error_log('创建数据表失败: ' . $e->getMessage());
        throw $e;
    }
} 