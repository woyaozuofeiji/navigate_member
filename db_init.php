<?php
/**
 * 数据库初始化文件
 * 用于向后兼容，所有功能已移至includes/db_connect.php
 */

// 引入数据库连接文件
require_once __DIR__ . '/includes/db_connect.php';

// 以下变量保留以兼容旧代码
$dbHost = $currentConfig['db']['host'];
$dbName = $currentConfig['db']['name'];
$dbUser = $currentConfig['db']['user'];
$dbPass = $currentConfig['db']['pass'];

// 如果有动作参数，进行表初始化
if (isset($_GET['action']) && $_GET['action'] == 'init') {
    // 创建数据库连接并初始化表
    try {
        $pdo = getDbConnection(true);
        echo '<div style="padding:20px; background:#d4edda; color:#155724; border-radius:5px; margin:20px auto; max-width:800px; font-family:Arial,sans-serif;">';
        echo '<h2>数据库初始化成功</h2>';
        echo '<p>所有必要的表已创建。</p>';
        echo '<p><a href="db_test.php" style="color:#155724;">返回数据库测试页面</a></p>';
        echo '</div>';
    } catch (Exception $e) {
        echo '<div style="padding:20px; background:#f8d7da; color:#721c24; border-radius:5px; margin:20px auto; max-width:800px; font-family:Arial,sans-serif;">';
        echo '<h2>数据库初始化失败</h2>';
        echo '<p>错误: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><a href="db_test.php" style="color:#721c24;">返回数据库测试页面</a></p>';
        echo '</div>';
    }
    exit;
}

// 如果有重置参数，进行数据库重置
if (isset($_GET['action']) && $_GET['action'] == 'reinit') {
    // 清空并重建数据库
    try {
        $dsn = "mysql:host={$dbHost};port={$currentConfig['db']['port']};charset={$currentConfig['db']['charset']}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $server_pdo = new PDO($dsn, $dbUser, $dbPass, $options);
        $server_pdo->exec("DROP DATABASE IF EXISTS `{$dbName}`");
        $server_pdo->exec("CREATE DATABASE `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        
        // 重新连接并创建表
        $pdo = getDbConnection(true);
        
        echo '<div style="padding:20px; background:#d4edda; color:#155724; border-radius:5px; margin:20px auto; max-width:800px; font-family:Arial,sans-serif;">';
        echo '<h2>数据库重置成功</h2>';
        echo '<p>数据库已重置，所有必要的表已重新创建。</p>';
        echo '<p><a href="db_test.php" style="color:#155724;">返回数据库测试页面</a></p>';
        echo '</div>';
    } catch (Exception $e) {
        echo '<div style="padding:20px; background:#f8d7da; color:#721c24; border-radius:5px; margin:20px auto; max-width:800px; font-family:Arial,sans-serif;">';
        echo '<h2>数据库重置失败</h2>';
        echo '<p>错误: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><a href="db_test.php" style="color:#721c24;">返回数据库测试页面</a></p>';
        echo '</div>';
    }
    exit;
} 
?> 