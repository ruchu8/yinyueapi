<?php
namespace Metowolf;

// 1. 配置网易云音乐Cookie（若获取失效，替换为自己的Cookie）
$netease_cookie = 'NMTID=00OXmulainmPg73okSRvUAdXFM3Gk4AAAGScBw7gw; JSESSIONID-WYYY=Za34q%2BSACyZKfxmcVTiCGuiubVb63hHPbpWBzZUM1ur2Jn2XvXVyg6EDs93gUQ23xrQufKuAQlVlggkFp7HipKa37s%2Fk6cXpcEgdFbKA%5CAElO03N%2BX8NseWmPVQzZ7bvUngbR%2FAO6lHR32Cf%2BVMx80%5C9yXOuQRaN5YI8V9n7k5%2FtSt6m%3A1728459551542; _iuqxldmzr_=32; _ntes_nnid=8ca1df07817e02979e9f7b55155bf6ef,1728457751559; _ntes_nuid=8ca1df07817e02979e9f7b55155bf6ef; WEVNSM=1.0.0; WNMCID=uaabkp.1728457752773.01.0; WM_NI=cmGJdvXChwWAAPme2p8wqkJQu7UYbQwhxw9PiuzXA2I6uHplOvM1BZ1SIdHMEAXxscCV0UyZbObqCuzAqiMEYKkoMWeP9ZoyHyTe4wM8wnv28OIciuwWx7ByNIoHnYnkdHM%3D; WM_NIKE=9ca17ae2e6ffcda170e2e6ee8ad042b5b186d5ef3fbcb08ea6c15a968b8facc76098ac9eb6aa5bac869683b82af0fea7c3b92aa1b389abe77e9c9da898d64b8390a386b17cfca9bf8cc95bb7e99bbae15bb5b8b898bc5cababb6b9c43a8beabba9f3688daa83d2c152f38d8785cb47879f9899f269818a00b6fc52a291ae92c27fba9fbf8ad04f9b95a590d05da790f7bbee738ca79c86b77d8fe8b98ff53afc918784ec7fb589b8d8c1738e97a1a9ed53aeb5aca9d037e2a3; WM_TID=MVWFox6vlNlBVAQAUFaGWS4UV6R1YcO5; sDeviceId=YD-Rrv0ryUqWVxFVgUREAfDHDoVV6EkYrow; ntes_utid=tid._.c14VwfI8PR1BAwEAUAPCWG9QUrQkfW%252FI._.0';

// 2. 引入精简后的Meting核心类（仅保留网易云相关逻辑）
class Meting {
    public $raw;
    public $info;
    public $error;
    public $status;
    public $header;
    public $temp = [];

    // 初始化：固定为网易云音乐源
    public function __construct() {
        $this->header = $this->curlset();
    }

    // 设置Cookie
    public function cookie($value) {
        $this->header['Cookie'] = $value;
        return $this;
    }

    // 网易云音乐请求头配置
    private function curlset() {
        return [
            'Referer'         => 'https://music.163.com/',
            'Cookie'          => 'appver=8.2.30; os=iPhone OS; osver=15.0; EVNSM=1.0.0;',
            'User-Agent'      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 CloudMusic/8.2.30',
            'X-Real-IP'       => long2ip(mt_rand(1884815360, 1884890111)),
            'Accept'          => '*/*',
            'Content-Type'    => 'application/x-www-form-urlencoded',
        ];
    }

    // 获取网易云音乐播放地址
    public function url($id, $br = 320) {
        $this->temp['br'] = $br;
        // 网易云音乐URL接口配置
        $api = [
            'method' => 'POST',
            'url'    => 'http://music.163.com/api/song/enhance/player/url',
            'body'   => ['ids' => [$id], 'br' => $br * 1000],
            'encode' => 'netease_AESCBC',
            'decode' => 'netease_url',
        ];
        return $this->exec($api);
    }

    // 执行API请求（含加密/解密）
    private function exec($api) {
        // 执行网易云AES加密
        if (isset($api['encode'])) {
            $api = $this->{$api['encode']}($api);
        }
        // 处理GET/POST请求
        if ($api['method'] == 'GET' && isset($api['body'])) {
            $api['url'] .= '?' . http_build_query($api['body']);
            $api['body'] = null;
        }
        // 发送curl请求
        $this->curl($api['url'], $api['body']);
        // 执行解密处理
        if (isset($api['decode'])) {
            return $this->{$api['decode']}($this->raw);
        }
        return $this->raw;
    }

    // CURL请求核心方法
    private function curl($url, $payload = null) {
        $header = array_map(function ($k, $v) {
            return $k . ': ' . $v;
        }, array_keys($this->header), $this->header);
        $curl = curl_init();
        // POST请求处理
        if (!is_null($payload)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($payload));
        }
        // CURL基础配置
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        // 执行请求
        $this->raw = curl_exec($curl);
        $this->info = curl_getinfo($curl);
        $this->error = curl_errno($curl);
        $this->status = $this->error ? curl_error($curl) : '';
        curl_close($curl);
        return $this;
    }

    // 网易云音乐AES-CBC加密（接口请求必须）
    private function netease_AESCBC($api) {
        $modulus = '157794750267131502212476817800345498121872783333389747424011531025366277535262539913701806290766479189477533597854989606803194253978660329941980786072432806427833685472618792592200595694346872951301770580765135349259590167490536138082469680638514416594216629258349130257685001248172188325316586707301643237607';
        $pubkey = '65537';
        $nonce = '0CoJUm6Qyw8W8jud';
        $vi = '0102030405060708';
        $skey = extension_loaded('bcmath') ? $this->getRandomHex(16) : 'B3v3kH4vRPWRJFfH';
        $body = json_encode($api['body']);

        // AES加密处理
        if (function_exists('openssl_encrypt')) {
            $body = openssl_encrypt($body, 'aes-128-cbc', $nonce, false, $vi);
            $body = openssl_encrypt($body, 'aes-128-cbc', $skey, false, $vi);
        } else {
            $pad = 16 - (strlen($body) % 16);
            $body = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $nonce, $body . str_repeat(chr($pad), $pad), MCRYPT_MODE_CBC, $vi));
            $pad = 16 - (strlen($body) % 16);
            $body = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $skey, $body . str_repeat(chr($pad), $pad), MCRYPT_MODE_CBC, $vi));
        }

        // RSA加密处理 - 修复utf8_encode() deprecated问题
        if (extension_loaded('bcmath')) {
            // 使用mb_convert_encoding替代utf8_encode
            $skey = strrev(mb_convert_encoding($skey, 'UTF-8', 'ISO-8859-1'));
            $skey = $this->bchexdec($this->str2hex($skey));
            $skey = bcpowmod($skey, $pubkey, $modulus);
            $skey = $this->bcdechex($skey);
            $skey = str_pad($skey, 256, '0', STR_PAD_LEFT);
        } else {
            $skey = '85302b818aea19b68db899c25dac229412d9bba9b3fcfe4f714dc016bc1686fc446a08844b1f8327fd9cb623cc189be00c5a365ac835e93d4858ee66f43fdc59e32aaed3ef24f0675d70172ef688d376a4807228c55583fe5bac647d10ecef15220feef61477c28cae8406f6f9896ed329d6db9f88757e31848a6c2ce2f94308';
        }

        // 重组请求参数
        $api['url'] = str_replace('/api/', '/weapi/', $api['url']);
        $api['body'] = ['params' => $body, 'encSecKey' => $skey];
        return $api;
    }

    // 网易云音乐URL解密（提取真实播放地址）
    private function netease_url($result) {
        $data = json_decode($result, true);
        // 处理可能的URL嵌套（部分场景URL在uf字段内）
        if (isset($data['data'][0]['uf']['url'])) {
            $data['data'][0]['url'] = $data['data'][0]['uf']['url'];
        }
        // 返回URL或空值（失败时）
        return isset($data['data'][0]['url']) ? $data['data'][0]['url'] : '';
    }

    // 辅助方法：生成随机16进制字符串
    private function getRandomHex($length) {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        } elseif (function_exists('mcrypt_create_iv')) {
            return bin2hex(mcrypt_create_iv($length / 2, MCRYPT_DEV_URANDOM));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            return bin2hex(openssl_random_pseudo_bytes($length / 2));
        }
        return substr(md5(rand()), 0, $length);
    }

    // 辅助方法：16进制转10进制（BCMath）
    private function bchexdec($hex) {
        $dec = 0;
        $len = strlen($hex);
        for ($i = 1; $i <= $len; $i++) {
            $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
        }
        return $dec;
    }

    // 辅助方法：10进制转16进制（BCMath）
    private function bcdechex($dec) {
        $hex = '';
        do {
            $last = bcmod($dec, 16);
            $hex = dechex($last) . $hex;
            $dec = bcdiv(bcsub($dec, $last), 16);
        } while ($dec > 0);
        return $hex;
    }

    // 辅助方法：字符串转16进制
    private function str2hex($string) {
        $hex = '';
        for ($i = 0; $i < strlen($string); $i++) {
            $hex .= substr('0' . dechex(ord($string[$i])), -2);
        }
        return $hex;
    }
}

// 关闭错误输出，防止header发送前有内容输出
error_reporting(0);
ini_set('display_errors', 0);

// 3. 核心业务逻辑：获取ID → 调用接口 → 302重定向
use Metowolf\Meting;
// 从URL参数获取网易云音乐ID（如 ?id=1449559488）
$musicId = trim(isset($_GET['id']) ? $_GET['id'] : '');

// 验证ID有效性（网易云ID为纯数字，长度10-12位左右）
if (empty($musicId) || !is_numeric($musicId) || strlen($musicId) < 8 || strlen($musicId) > 15) {
    http_response_code(400);
    echo "无效的音乐ID！请在URL后添加 ?id=网易云音乐ID（示例：?id=1449559488）";
    exit;
}

// 初始化Meting并获取播放地址
$api = new Meting();
$api->cookie($netease_cookie); // 设置网易云Cookie
$audioUrl = $api->url($musicId, 320); // 320kbps音质（可改为128/192）

// 验证播放地址有效性并执行302重定向
if (empty($audioUrl) || !filter_var($audioUrl, FILTER_VALIDATE_URL)) {
    http_response_code(404);
    echo "获取播放地址失败！可能原因：1. ID不存在 2. Cookie失效 3. API限制";
    exit;
}

// 执行302临时重定向（浏览器会直接打开MP3地址播放）
header("Location: " . $audioUrl, true, 302);
exit;
    
