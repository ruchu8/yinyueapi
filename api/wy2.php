<?php
// 从URL参数获取音乐ID
$musicId = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($musicId) || !is_numeric($musicId)) {
    // 如果没有提供ID或ID格式不正确，显示错误
    header("HTTP/1.1 400 Bad Request");
    echo "请提供有效的音乐ID，格式: ?id=音乐ID（例如：?id=1449559488）";
    exit;
}

// 调用API获取实际播放地址
$apiUrl = "https://music-api.gdstudio.xyz/api.php?types=url&id={$musicId}&source=netease&br=320";

// 发送请求
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 检查API请求是否成功
if ($httpCode != 200 || empty($response)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "无法连接到音乐API，请稍后再试";
    exit;
}

// 解析API响应
$data = json_decode($response, true);
if (json_last_error() != JSON_ERROR_NONE || !isset($data['url'])) {
    header("HTTP/1.1 404 Not Found");
    echo "未找到该音乐的播放地址，ID: {$musicId}";
    exit;
}

// 执行302重定向到实际的MP3地址
header("Location: " . $data['url'], true, 302);
exit;
?>
    