<?php
// 设置错误日志文件
ini_set('error_log', 'sign_in_error.log');
ini_set('log_errors', 1);
ini_set('display_errors', 0);

// 防止缓存
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 引入函数库
require_once 'includes/functions.php';

// 启用会话
session_start();

// 防止CSRF攻击，检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '无效的请求方法']);
    exit;
}

// 开启错误日志记录
error_log("===== 签到请求开始处理 - " . date('Y-m-d H:i:s') . " =====");
error_log("用户IP: " . getRealIpAddr());
error_log("用户ID: " . ($_SESSION['member_id'] ?? '未登录'));
error_log("会话数据: " . print_r($_SESSION, true));

// 判断用户是否已登录
if (!isset($_SESSION['member_id'])) {
    error_log("签到失败 - 用户未登录");
    // 返回JSON响应
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 更新会话活动时间
$_SESSION['last_activity'] = time();

// 引入会员系统
require_once 'member_system.php';
error_log("会员系统类已加载");

try {
    $memberSystem = new MemberSystem();
    error_log("MemberSystem实例已创建");
    
    error_log("开始执行签到 - 用户ID: " . $_SESSION['member_id']);
    
    // 执行签到
    $result = $memberSystem->doDailySignIn($_SESSION['member_id']);
    
    // 记录签到结果
    error_log("签到结果: " . ($result['success'] ? '成功' : '失败') . " - 消息: " . ($result['message'] ?? '无消息'));
    error_log("完整结果数据: " . print_r($result, true));
    
    // 如果签到成功，更新会话中的积分
    if ($result['success'] && isset($result['points'])) {
        $oldPoints = $_SESSION['member_points'];
        $_SESSION['member_points'] += $result['points'];
        error_log("积分已更新 - 旧积分: " . $oldPoints . ", 奖励积分: " . $result['points'] . ", 新积分: " . $_SESSION['member_points']);
    }
    
    // 返回JSON响应
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("签到过程中发生异常: " . $e->getMessage());
    error_log("异常堆栈: " . $e->getTraceAsString());
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => '签到过程发生错误，请联系管理员',
        'error' => $e->getMessage()
    ]);
}

error_log("===== 签到请求处理完成 - " . date('Y-m-d H:i:s') . " =====\n"); 