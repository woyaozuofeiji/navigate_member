<?php
/**
 * 权限验证检查
 * 用于检查用户是否已登录，如果未登录则重定向到登录页面
 * 请在需要登录才能访问的页面顶部包含此文件
 */

// 确保会话已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 获取当前脚本文件名和目录
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$scriptDir = dirname($_SERVER['SCRIPT_NAME']);
$loginPage = 'login.php';
$indexPage = 'index.php';

// 确定登录页面的完整URL
$baseDir = '';
if ($scriptDir != '/' && strpos($scriptDir, '/admin') !== false) {
    // 如果在admin目录下
    $baseDir = '..';
}

// 防止重定向循环
if ($currentScript == $loginPage || $currentScript == $indexPage) {
    // 如果当前已经在登录页或首页，不再重定向
    // 只清除未验证的会话
    if (!isset($_SESSION['member_id']) || !isset($_SESSION['verified']) || $_SESSION['verified'] !== true) {
        // 清除可能导致问题的会话变量
        unset($_SESSION['verified']);
        if (isset($_SESSION['member_id'])) {
            unset($_SESSION['member_id']);
        }
    }
} else {
    // 记录验证状态
    error_log("验证状态检查: 脚本={$currentScript}, 会话ID=".session_id().", 会员ID=".(isset($_SESSION['member_id']) ? $_SESSION['member_id'] : '未设置').", 验证状态=".(isset($_SESSION['verified']) && $_SESSION['verified'] === true ? 'true' : 'false'));
    
    // 检查会员是否登录
    if (!isset($_SESSION['member_id'])) {
        // 用户未登录，重定向到登录页面
        header("Location: {$baseDir}/{$loginPage}");
        exit;
    }

    // 检查会话是否已验证
    if (!isset($_SESSION['verified']) || $_SESSION['verified'] !== true) {
        // 会话未验证，重定向到登录页面
        header("Location: {$baseDir}/{$loginPage}");
        exit;
    }

    // 检查会话超时
    $sessionTimeout = 30 * 60; // 30分钟
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
        // 会话已超时，清除会话并重定向
        session_unset();
        session_destroy();
        header("Location: {$baseDir}/{$loginPage}?msg=timeout");
        exit;
    }
}

// 更新最后活动时间
$_SESSION['last_activity'] = time();
?> 