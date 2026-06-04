<?php
/**
 * 酷我音乐 IP 封锁测试脚本 (PHP 版)
 * 用于检测当前 PHP 服务器 IP 是否被酷我列入黑名单
 */

header('Content-Type: application/json; charset=utf-8');

$testRids = ['3971725', '228911'];
$results = [];

$testUrls = [
    [
        "name" => "Kuwo Main Website (HTTP)",
        "url" => "http://www.kuwo.cn/"
    ],
    [
        "name" => "Antiserver API (Plain Text)",
        "url" => "http://antiserver.kuwo.cn/anti.s?type=convert_url&rid={$testRids[0]}&format=mp3&response=url"
    ],
    [
        "name" => "Mobile API (Encrypted - Example Query)",
        "url" => "http://mobi.kuwo.cn/mobi.s?f=kuwo&q=QTTCEVWADWjGHNKyqOt6peSJECe9IlwYOThEXM42tOPUM09JJgqs4koq6HW+DmLo6NvDv+yKU0JVRFu8k+uReMgqO9c3DBQehRhuLv8hLwiRAcRvUqhAdgBiZRX9VKg739nvVkYYODS+8UeZJD8h7bH3LC47wUeiwiGV2y87hLCVTQibrzg3XnTw3qNdXC2bMihICLmHhbRvkiLF8sBnkYTZ3RhEMTBVRPtwTIzgPRobEaRr+RGWZ17u8Hu2TzY+W4fcEwbu9OXtmvRvD7HuNNR/cSqQlJnzVupFaDeTvUeDcjBVG8upjIDPUZA6mv1u7u2Mq9uUROM/eTr+3ZrL1G1wmU7dvhqEYHw1F2WiWk8="
    ]
];

foreach ($testUrls as $test) {
    $startTime = microtime(true);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // 不自动跳转，观察原始响应
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    // 模拟一个随机 IP
    $randomIp = rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Forwarded-For: $randomIp"]);

    $bodyText = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $duration = round((microtime(true) - $startTime) * 1000);
    $error = curl_error($ch);
    curl_close($ch);

    if ($bodyText === false) {
        $results[] = [
            "test" => $test['name'],
            "url" => $test['url'],
            "error" => $error,
            "isBlocked" => true
        ];
    } else {
        $isBlocked = (strpos($bodyText, '1325645003') !== false || strpos($bodyText, '588957081') !== false || $status === 403);
        
        $results[] = [
            "test" => $test['name'],
            "url" => $test['url'],
            "status" => $status,
            "duration" => "{$duration}ms",
            "isBlocked" => $isBlocked,
            "bodySnippet" => mb_substr($bodyText, 0, 300, 'utf-8')
        ];
    }
}

echo json_encode([
    "platform" => "PHP Server",
    "timestamp" => date('Y-m-d H:i:s'),
    "server_ip" => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
    "results" => $results
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
