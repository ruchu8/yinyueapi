<?php
/**
 * 音乐资源代理脚本
 * 功能：通过酷我音乐接口获取音频资源并转发给播放器
 * 支持音质选择、断点续传和请求伪装
 */

// 配置参数
define('SECRET_KEY', 'ylzsxkwm');
define('SSL_VERIFY', false); // 生产环境建议改为true并配置CA证书
define('CURL_TIMEOUT', 10);  // CURL超时时间(秒)

// DES加密所需常量（封装为常量避免全局变量污染）
const DES_MODE_DECRYPT = 1;
const ARRAY_E = [31, 0, DES_MODE_DECRYPT, 2, 3, 4, -1, -1, 3, 4, 5, 6, 7, 8, -1, -1, 7, 8, 9, 10, 11, 12, -1, -1, 11, 12, 13, 14, 15, 16, -1, -1, 15, 16, 17, 18, 19, 20, -1, -1, 19, 20, 21, 22, 23, 24, -1, -1, 23, 24, 25, 26, 27, 28, -1, -1, 27, 28, 29, 30, 31, 30, -1, -1];
const ARRAY_IP = [57, 49, 41, 33, 25, 17, 9, DES_MODE_DECRYPT, 59, 51, 43, 35, 27, 19, 11, 3, 61, 53, 45, 37, 29, 21, 13, 5, 63, 55, 47, 39, 31, 23, 15, 7, 56, 48, 40, 32, 24, 16, 8, 0, 58, 50, 42, 34, 26, 18, 10, 2, 60, 52, 44, 36, 28, 20, 12, 4, 62, 54, 46, 38, 30, 22, 14, 6];
const ARRAY_IP_1 = [39, 7, 47, 15, 55, 23, 63, 31, 38, 6, 46, 14, 54, 22, 62, 30, 37, 5, 45, 13, 53, 21, 61, 29, 36, 4, 44, 12, 52, 20, 60, 28, 35, 3, 43, 11, 51, 19, 59, 27, 34, 2, 42, 10, 50, 18, 58, 26, 33, DES_MODE_DECRYPT, 41, 9, 49, 17, 57, 25, 32, 0, 40, 8, 48, 16, 56, 24];
const ARRAY_LS = [1, 1, 2, 2, 2, 2, 2, 2, 1, 2, 2, 2, 2, 2, 2, 1];
const ARRAY_LS_MASK = [0, 0x100001, 0x300003];
const ARRAY_MASK = [1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048, 4096, 8192, 16384, 32768, 65536, 131072, 262144, 524288, 1048576, 2097152, 4194304, 8388608, 16777216, 33554432, 67108864, 134217728, 268435456, 536870912, 1073741824, 2147483648, 4294967296, 8589934592, 17179869184, 34359738368, 68719476736, 137438953472, 274877906944, 549755813888, 1099511627776, 2199023255552, 4398046511104, 8796093022208, 17592186044416, 35184372088832, 70368744177664, 140737488355328, 281474976710656, 562949953421312, 1125899906842624, 2251799813685248, 4503599627370496, 9007199254740992, 18014398509481984, 36028797018963968, 72057594037927936, 144115188075855872, 288230376151711744, 576460752303423488, 1152921504606846976, 2305843009213693952, 4611686018427387904, -9223372036854775808];
const ARRAY_P = [15, 6, 19, 20, 28, 11, 27, 16, 0, 14, 22, 25, 4, 17, 30, 9, 1, 7, 23, 13, 31, 26, 2, 8, 18, 12, 29, 5, 21, 10, 3, 24];
const ARRAY_PC_1 = [56, 48, 40, 32, 24, 16, 8, 0, 57, 49, 41, 33, 25, 17, 9, 1, 58, 50, 42, 34, 26, 18, 10, 2, 59, 51, 43, 35, 62, 54, 46, 38, 30, 22, 14, 6, 61, 53, 45, 37, 29, 21, 13, 5, 60, 52, 44, 36, 28, 20, 12, 4, 27, 19, 11, 3];
const ARRAY_PC_2 = [13, 16, 10, 23, 0, 4, -1, -1, 2, 27, 14, 5, 20, 9, -1, -1, 22, 18, 11, 3, 25, 7, -1, -1, 15, 6, 26, 19, 12, 1, -1, -1, 40, 51, 30, 36, 46, 54, -1, -1, 29, 39, 50, 44, 32, 47, -1, -1, 43, 48, 38, 55, 33, 52, -1, -1, 45, 41, 49, 35, 28, 31, -1, -1];
const MATRIX_NS_BOX = [[14, 4, 3, 15, 2, 13, 5, 3, 13, 14, 6, 9, 11, 2, 0, 5, 4, 1, 10, 12, 15, 6, 9, 10, 1, 8, 12, 7, 8, 11, 7, 0, 0, 15, 10, 5, 14, 4, 9, 10, 7, 8, 12, 3, 13, 1, 3, 6, 15, 12, 6, 11, 2, 9, 5, 0, 4, 2, 11, 14, 1, 7, 8, 13], [15, 0, 9, 5, 6, 10, 12, 9, 8, 7, 2, 12, 3, 13, 5, 2, 1, 14, 7, 8, 11, 4, 0, 3, 14, 11, 13, 6, 4, 1, 10, 15, 3, 13, 12, 11, 15, 3, 6, 0, 4, 10, 1, 7, 8, 4, 11, 14, 13, 8, 0, 6, 2, 15, 9, 5, 7, 1, 10, 12, 14, 2, 5, 9], [10, 13, 1, 11, 6, 8, 11, 5, 9, 4, 12, 2, 15, 3, 2, 14, 0, 6, 13, 1, 3, 15, 4, 10, 14, 9, 7, 12, 5, 0, 8, 7, 13, 1, 2, 4, 3, 6, 12, 11, 0, 13, 5, 14, 6, 8, 15, 2, 7, 10, 8, 15, 4, 9, 11, 5, 9, 0, 14, 3, 10, 7, 1, 12], [7, 10, 1, 15, 0, 12, 11, 5, 14, 9, 8, 3, 9, 7, 4, 8, 13, 6, 2, 1, 6, 11, 12, 2, 3, 0, 5, 14, 10, 13, 15, 4, 13, 3, 4, 9, 6, 10, 1, 12, 11, 0, 2, 5, 0, 13, 14, 2, 8, 15, 7, 4, 15, 1, 10, 7, 5, 6, 12, 11, 3, 8, 9, 14], [2, 4, 8, 15, 7, 10, 13, 6, 4, 1, 3, 12, 11, 7, 14, 0, 12, 2, 5, 9, 10, 13, 0, 3, 1, 11, 15, 5, 6, 8, 9, 14, 14, 11, 5, 6, 4, 1, 3, 10, 2, 12, 15, 0, 13, 2, 8, 5, 11, 8, 0, 15, 7, 14, 9, 4, 12, 7, 10, 9, 1, 13, 6, 3], [12, 9, 0, 7, 9, 2, 14, 1, 10, 15, 3, 4, 6, 12, 5, 11, 1, 14, 13, 0, 2, 8, 7, 13, 15, 5, 4, 10, 8, 3, 11, 6, 10, 4, 6, 11, 7, 9, 0, 6, 4, 2, 13, 1, 9, 15, 3, 8, 15, 3, 1, 14, 12, 5, 11, 0, 2, 12, 14, 7, 5, 10, 8, 13], [4, 1, 3, 10, 15, 12, 5, 0, 2, 11, 9, 6, 8, 7, 6, 9, 11, 4, 12, 15, 0, 3, 10, 5, 14, 13, 7, 8, 13, 14, 1, 2, 13, 6, 14, 9, 4, 1, 2, 14, 11, 13, 5, 0, 1, 10, 8, 3, 0, 11, 3, 5, 9, 4, 15, 2, 7, 8, 12, 15, 10, 7, 6, 12], [13, 7, 10, 0, 6, 9, 5, 15, 8, 4, 3, 10, 11, 14, 12, 5, 2, 11, 9, 6, 15, 12, 0, 3, 4, 1, 14, 13, 1, 2, 7, 8, 1, 2, 12, 15, 10, 4, 0, 3, 13, 14, 6, 9, 7, 8, 9, 6, 15, 1, 5, 12, 3, 10, 14, 5, 8, 7, 11, 0, 4, 13, 2, 11]];

/**
 * 十六进制转字符串
 * @param string $hex 十六进制字符串
 * @return string 转换后的字符串
 */
function hex2Str($hex) {
    $hex = strtolower($hex);
    $length = intval(strlen($hex) / 2);
    $str = "";
    for ($i = 0; $i < $length; $i++) {
        $pos = $i * 2;
        $char = chr(hexdec(substr($hex, $pos, 2)) & 255);
        $str .= $char;
    }
    return $str;
}

/**
 * 字符串转十六进制
 * @param string $str 原始字符串
 * @return string 转换后的十六进制字符串
 */
function byte2hex($str) {
    $hex = "";
    for ($i = 0; $i < strlen($str); $i++) {
        $current = strtoupper(dechex(ord($str[$i])));
        if (strlen($current) > 2) {
            $hex .= substr($current, 6);
        } else {
            $hex .= str_pad($current, 2, '0', STR_PAD_LEFT);
        }
    }
    return $hex;
}

/**
 * 位运算转换
 * @param array $transformArray 转换规则数组
 * @param int $count 数组长度
 * @param int $value 要转换的值
 * @return int 转换后的值
 */
function bit_transform($transformArray, $count, $value) {
    $result = 0;
    for ($i = 0; $i < $count; $i++) {
        $pos = $transformArray[$i];
        if ($pos < 0 || !($value & ARRAY_MASK[$pos])) {
            continue;
        }
        $result |= ARRAY_MASK[$i];
    }
    return $result;
}

/**
 * DES加密核心处理
 * @param array $keys 密钥数组
 * @param int $value 要加密的值
 * @return int 加密后的值
 */
function DES64($keys, $value) {
    $out = bit_transform(ARRAY_IP, 64, $value);
    
    $source = [
        0xFFFFFFFF & $out,
        (-4294967296 & $out) >> 32
    ];

    for ($i = 0; $i < 16; $i++) {
        $right = $source[1];
        $right = bit_transform(ARRAY_E, 64, $right);
        $right ^= $keys[$i];

        $rParts = [];
        for ($j = 0; $j < 8; $j++) {
            $rParts[$j] = 255 & ($right >> ($j * 8));
        }

        $sOut = 0;
        for ($sbi = 7; $sbi >= 0; $sbi--) {
            $sOut <<= 4;
            $sOut |= MATRIX_NS_BOX[$sbi][$rParts[$sbi]];
        }

        $right = bit_transform(ARRAY_P, 32, $sOut);
        $left = $source[0];

        $source[0] = $source[1];
        $source[1] = $left ^ $right;
    }

    $source = array_reverse($source);
    $out = (-4294967296 & ($source[1] << 32)) | (0xFFFFFFFF & $source[0]);
    return bit_transform(ARRAY_IP_1, 64, $out);
}

/**
 * 生成子密钥
 * @param int $key 原始密钥
 * @param array $subKeys 子密钥数组（引用传递）
 * @param int $mode 模式（0:加密, 1:解密）
 */
function sub_keys($key, &$subKeys, $mode) {
    $key = bit_transform(ARRAY_PC_1, 56, $key);

    for ($i = 0; $i < 16; $i++) {
        $shift = ARRAY_LS[$i];
        $key = (($key & ARRAY_LS_MASK[$shift]) << (28 - $shift)) | 
               (($key & ~ARRAY_LS_MASK[$shift]) >> $shift);
        $subKeys[$i] = bit_transform(ARRAY_PC_2, 64, $key);
    }

    // 解密模式需要反转密钥顺序
    if ($mode == 1) {
        $subKeys = array_reverse($subKeys);
    }
}

/**
 * DES加密
 * @param string $data 要加密的数据
 * @return string 加密后的二进制数据
 */
function encrypt($data) {
    $keyStr = SECRET_KEY;
    $key = 0;
    for ($i = 0; $i < 8; $i++) {
        $key |= ord($keyStr[$i]) << ($i * 8);
    }

    $blockCount = ceil(strlen($data) / 8);
    $subKeys = array_fill(0, 16, 0);
    sub_keys($key, $subKeys, 0);

    // 处理每个数据块
    $result = [];
    for ($i = 0; $i < $blockCount; $i++) {
        $block = 0;
        $blockData = substr($data, $i * 8, 8);
        for ($j = 0; $j < strlen($blockData); $j++) {
            $block |= ord($blockData[$j]) << ($j * 8);
        }
        $result[] = DES64($subKeys, $block);
    }

    // 转换为二进制字符串
    $output = '';
    foreach ($result as $block) {
        for ($i = 0; $i < 8; $i++) {
            $output .= chr(255 & ($block >> ($i * 8)));
        }
    }

    return $output;
}

/**
 * 加密并转为Base64
 * @param string $data 要处理的数据
 * @return string Base64编码的加密结果
 */
function base64_encrypt($data) {
    $encrypted = encrypt($data);
    $base64 = base64_encode($encrypted);
    return str_replace(["\r\n", "\n"], '', $base64);
}

/**
 * 获取随机国内IP地址
 * @return string 随机IP地址
 */
function getRandomChineseIP() {
    // 国内IP段列表（可根据需要扩展）
    $ipRanges = [
        ['36.56.0.0', '36.63.255.255'],
        ['42.80.0.0', '42.95.255.255'],
        ['112.64.0.0', '112.127.255.255'],
        ['117.136.0.0', '117.191.255.255']
    ];

    $range = $ipRanges[array_rand($ipRanges)];
    $start = ip2long($range[0]);
    $end = ip2long($range[1]);
    return long2ip(random_int($start, $end));
}

/**
 * 创建CURL句柄并设置通用选项
 * @param string $url 请求URL
 * @param array $headers 额外请求头
 * @param bool $returnTransfer 是否返回结果
 * @return resource CURL句柄
 */
function createCurlHandle($url, $headers = [], $returnTransfer = true) {
    $ch = curl_init($url);
    
    // 通用配置
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, $returnTransfer);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, SSL_VERIFY);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, SSL_VERIFY ? 2 : 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, CURL_TIMEOUT);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    // 设置手机UA
    $userAgent = 'Mozilla/5.0 (Linux; Android 13; SM-G9980) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36';
    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
    
    // 添加请求头
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    
    return $ch;
}

/**
 * 获取音乐资源URL
 * @param string $id 音乐ID
 * @param string $format 格式(mp3/acc/flac)
 * @param string $bitrate 比特率
 * @return string 资源请求URL
 */
function getMusicResourceUrl($id, $format, $bitrate) {
    $params = "user=0&android_id=0&prod=kwplayer_ar_8.5.5.0&corp=kuwo&newver=3&vipver=8.5.5.0&source=kwplayer_ar_8.5.5.0_apk_keluze.apk&p2p=1&notrace=0&type=convert_url2&br={$bitrate}&format={$format}&sig=0&rid={$id}&priority=bitrate&loginUid=0&network=WIFI&loginSid=0&mode=download";
    return "http://mobi.kuwo.cn/mobi.s?f=kuwo&q=" . base64_encrypt($params);
}

/**
 * 解析真实音频URL
 * @param string $response 接口响应内容
 * @return string|false 真实URL或false
 */
function parseRealAudioUrl($response) {
    if (preg_match('/url=(.*?)\s/', $response, $matches)) {
        $url = trim($matches[1]);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : false;
    }
    return false;
}

/**
 * 处理音频资源转发（支持断点续传）
 * @param string $audioUrl 真实音频URL
 */
function proxyAudioStream($audioUrl) {
    // 1. 获取文件头部信息
    $headers = [
        'X-Forwarded-For: ' . getRandomChineseIP(),
        'X-Real-IP: ' . getRandomChineseIP()
    ];
    
    $headCh = createCurlHandle($audioUrl, $headers, true);
    curl_setopt($headCh, CURLOPT_NOBODY, true); // 只请求头部
    curl_exec($headCh);
    
    $fileSize = curl_getinfo($headCh, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $mimeType = curl_getinfo($headCh, CURLINFO_CONTENT_TYPE) ?: 'audio/mpeg';
    $httpCode = curl_getinfo($headCh, CURLINFO_HTTP_CODE);
    
    curl_close($headCh);
    
    // 检查头部请求是否成功
    if ($httpCode < 200 || $httpCode >= 300) {
        http_response_code(502);
        die("获取资源信息失败 (HTTP {$httpCode})");
    }

    // 2. 处理范围请求
    $range = '';
    $start = 0;
    $end = $fileSize > 0 ? $fileSize - 1 : 0;
    $length = $fileSize;

    if (isset($_SERVER['HTTP_RANGE']) && $fileSize > 0) {
        if (preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $rangeMatches)) {
            $start = intval($rangeMatches[1]);
            $end = $rangeMatches[2] ? intval($rangeMatches[2]) : $end;

            // 验证范围有效性
            if ($start > $end || $start >= $fileSize) {
                http_response_code(416);
                header("Content-Range: bytes */{$fileSize}");
                exit;
            }

            $range = "bytes={$start}-{$end}";
            $length = $end - $start + 1;
            http_response_code(206);
        }
    } else {
        http_response_code(200);
    }

    // 3. 获取并转发音频数据
    $dataCh = createCurlHandle($audioUrl, array_merge($headers, $range ? ["Range: {$range}"] : []), true);
    $audioData = curl_exec($dataCh);
    $curlErr = curl_errno($dataCh);
    curl_close($dataCh);

    if ($curlErr || empty($audioData)) {
        http_response_code(500);
        die("获取音频数据失败 (错误码: {$curlErr})");
    }

    // 4. 设置响应头并输出数据
    header("Content-Type: {$mimeType}");
    header("Content-Length: {$length}");
    if ($fileSize > 0) {
        header("Content-Range: bytes {$start}-{$end}/{$fileSize}");
    }
    header("Accept-Ranges: bytes");
    header("Cache-Control: public, max-age=3600");
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');

    echo $audioData;
    exit;
}

// -------------------------- 主程序逻辑 --------------------------

// 获取并验证请求参数
$musicId = filter_input(INPUT_GET, var_name: 'rid', FILTER_SANITIZE_STRING);
$qualityLevel = filter_input(INPUT_GET, 'yz', FILTER_VALIDATE_INT, ['options' => ['default' => 3, 'min_range' => 1, 'max_range' => 5]]);

if (!$musicId) {
    http_response_code(400);
    die("参数错误：缺少有效的rid");
}

// 根据音质等级设置格式和比特率
$qualityMap = [
    1 => ['format' => 'acc', 'bitrate' => '64kacc'],
    2 => ['format' => 'mp3', 'bitrate' => '128kmp3'],
    3 => ['format' => 'mp3', 'bitrate' => '160kmp3'],
    4 => ['format' => 'mp3', 'bitrate' => '320kmp3'],
    5 => ['format' => 'flac', 'bitrate' => '2000flac']
];

$quality = $qualityMap[$qualityLevel];

try {
    // 1. 获取音乐资源请求URL
    $resourceUrl = getMusicResourceUrl($musicId, $quality['format'], $quality['bitrate']);
    
    // 2. 请求并获取资源信息
    $headers = [
        'X-Forwarded-For: ' . getRandomChineseIP(),
        'X-Real-IP: ' . getRandomChineseIP()
    ];
    
    $ch = createCurlHandle($resourceUrl, $headers, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_errno($ch);
    curl_close($ch);

    if ($curlErr || $httpCode < 200 || $httpCode >= 300) {
        throw new Exception("资源请求失败 (HTTP {$httpCode}, 错误码: {$curlErr})");
    }
    
    // 3. 解析真实音频URL
    $realAudioUrl = parseRealAudioUrl($response);
    if (!$realAudioUrl) {
        throw new Exception("未找到有效的音频URL");
    }
    
    // 4. 转发音频流
    proxyAudioStream($realAudioUrl);
    
} catch (Exception $e) {
    http_response_code(500);
    die("服务错误：{$e->getMessage()}");
}
?>
