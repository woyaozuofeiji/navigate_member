<?php
/**
 * 常用函数库
 */

/**
 * 获取用户真实IP地址
 * 支持Cloudflare和其他CDN/代理
 * 
 * @return string 用户的真实IP地址
 */
function getRealIpAddr() {
    // 检查Cloudflare特定的头
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    
    // 检查X-Forwarded-For头
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // X-Forwarded-For可能包含多个IP，第一个通常是用户真实IP
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    }
    
    // 检查其他常见代理头
    if (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        return $_SERVER['HTTP_X_REAL_IP'];
    }
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    
    // 如果以上都不存在，返回REMOTE_ADDR
    return $_SERVER['REMOTE_ADDR'];
}

/**
 * 检查邮箱格式是否有效
 * 
 * @param string $email 邮箱地址
 * @return bool 是否有效
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * 将时间戳格式化为友好的时间显示
 * 
 * @param int $timestamp 时间戳
 * @return string 格式化后的时间
 */
function formatTime($timestamp) {
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return '刚刚';
    } elseif ($diff < 3600) {
        return floor($diff / 60) . '分钟前';
    } elseif ($diff < 86400) {
        return floor($diff / 3600) . '小时前';
    } elseif ($diff < 2592000) {
        return floor($diff / 86400) . '天前';
    } else {
        return date('Y-m-d', $timestamp);
    }
} 