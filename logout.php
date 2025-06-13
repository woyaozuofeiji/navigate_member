<?php
session_start();
require_once 'member_system.php';

// 如果是会员登录，则调用会员系统的登出功能
if (isset($_SESSION['member_id'])) {
    $memberSystem = new MemberSystem();
    $memberSystem->logout();
}

// 清除所有会话数据
$_SESSION = array();

// 销毁会话cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// 销毁会话
session_destroy();

// 清除记住我的cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// 清除验证状态cookie
if (isset($_COOKIE['verified'])) {
    setcookie('verified', '', time() - 3600, '/');
}

// 重定向到登录页面
header('Location: index.php');
exit;
?> 
