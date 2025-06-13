<?php
// 确保会话已启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 验证码配置
$width = 280;  // 增加宽度
$height = 60;  // 增加高度
$chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // 排除容易混淆的字符
$length = 4;   // 减少验证码长度

// 记录会话状态
error_log("验证码生成前会话状态: ID=" . session_id() . ", 活跃=" . (session_status() === PHP_SESSION_ACTIVE ? 'yes' : 'no'));

// 生成验证码
function generateSecureCaptcha($chars, $length) {
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// 创建图片
$image = imagecreatetruecolor($width, $height);

// 设置颜色
$bgColor = imagecolorallocate($image, 240, 240, 240);     // 浅灰色背景
$textColor = imagecolorallocate($image, 30, 144, 255);    // 更鲜艳的蓝色
$noiseColor = imagecolorallocate($image, 150, 150, 150);  // 浅灰色噪点

// 填充背景
imagefilledrectangle($image, 0, 0, $width, $height, $bgColor);

// 添加少量噪点
for($i = 0; $i < 30; $i++) {
    imagesetpixel($image, rand(0, $width), rand(0, $height), $noiseColor);
}

// 添加少量线条
for($i = 0; $i < 2; $i++) {
    imageline($image, 
        rand(0, $width/2), rand(0, $height), 
        rand($width/2, $width), rand(0, $height), 
        $noiseColor
    );
}

// 生成验证码
$code = generateSecureCaptcha($chars, $length);

// 确保代码存储在会话中，并记录日志
$_SESSION['captcha'] = $code;
$_SESSION['captcha_time'] = time();

// 强制写入会话数据
session_write_close();
session_start();

// 记录生成的验证码（仅用于调试）
error_log("生成验证码: " . $code . ", 会话中存储的验证码: " . $_SESSION['captcha'] . ", 会话ID=" . session_id());

// 在图片上写入文字
$fontSize = 8;  // 增大字体大小
$x = ($width - ($length * 30)) / 2;  // 增加字符间距
for($i = 0; $i < $length; $i++) {
    $char = $code[$i];
    $y = $height/2 - 10;  // 固定垂直位置，不再随机
    imagechar($image, $fontSize, $x + ($i * 35), $y, $char, $textColor);  // 增加字符间距
}

// 输出图片
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
imagepng($image);
imagedestroy($image); 