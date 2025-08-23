const axios = require('axios');
const { pipeline } = require('stream/promises');

// 生成随机IP（可选：从IP池随机选择，增加真实性）
const ipPool = [
    '220.165.132.28',
    '113.108.209.58',
    '183.232.77.106',
    '59.44.155.112',
    '120.236.178.93'
];
function getRandomIp() {
    return ipPool[Math.floor(Math.random() * ipPool.length)];
}

// 生成随机浏览器User-Agent（模拟不同设备）
const userAgents = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Safari/605.1.15',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/114.0.1823.67',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 Safari/604.1'
];
function getRandomUserAgent() {
    return userAgents[Math.floor(Math.random() * userAgents.length)];
}

module.exports = async (req, res) => {
    // 设置CORS头
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS, HEAD');
    res.setHeader('Access-Control-Allow-Headers', 'Range, Content-Type, Cookie');
    res.setHeader('Access-Control-Expose-Headers', 'Accept-Ranges, Content-Range, Content-Length, Set-Cookie');
    
    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }
    
    if (req.method === 'HEAD') {
        res.setHeader('Accept-Ranges', 'bytes');
        return res.status(200).end();
    }

    const { id, type } = req.query;
    if (!id) {
        return res.status(400).json({ error: '缺少必要的id参数' });
    }

    const fakeIp = getRandomIp();
    const userAgent = getRandomUserAgent();
    const baseUrl = 'https://www.eev3.com';
    const referer = `${baseUrl}/mp3/${id}.html`;

    try {
        // 1. 先请求目标页面获取Cookie（模拟浏览器首次访问）
        const pageResponse = await axios.get(referer, {
            headers: {
                'User-Agent': userAgent,
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language': 'zh-CN,zh;q=0.8,zh-TW;q=0.7,zh-HK;q=0.5,en-US;q=0.3,en;q=0.2',
                'Accept-Encoding': 'gzip, deflate, br',
                'Connection': 'keep-alive',
                'Upgrade-Insecure-Requests': '1',
                'Sec-Fetch-Dest': 'document',
                'Sec-Fetch-Mode': 'navigate',
                'Sec-Fetch-Site': 'none',
                'Sec-Fetch-User': '?1',
                ...getFakeIpHeaders(fakeIp)
            },
            maxRedirects: 0, // 不自动重定向，保留原始Cookie
            validateStatus: (status) => status < 500 // 允许4xx状态（可能有临时重定向）
        });

        // 提取页面返回的Cookie（用于后续请求）
        const cookies = pageResponse.headers['set-cookie'] || [];
        const cookieHeader = cookies.map(c => c.split(';')[0]).join('; ');

        // 2. 请求音乐信息API（携带页面Cookie）
        const apiHeaders = {
            'User-Agent': userAgent,
            'Accept': 'application/json, text/javascript, */*; q=0.01',
            'Accept-Language': 'zh-CN,zh;q=0.9',
            'Accept-Encoding': 'gzip, deflate, br',
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With': 'XMLHttpRequest', // 模拟AJAX请求
            'Referer': referer,
            'Connection': 'keep-alive',
            'Cookie': cookieHeader, // 携带从页面获取的Cookie
            'Sec-Fetch-Dest': 'empty',
            'Sec-Fetch-Mode': 'cors',
            'Sec-Fetch-Site': 'same-origin',
            ...getFakeIpHeaders(fakeIp)
        };

        const response = await axios.post(`${baseUrl}/js/play.php`, 
            new URLSearchParams({ id, type: 'music' }),
            { headers: apiHeaders }
        );

        const data = response.data;
        
        // 3. 根据类型处理响应（所有请求均携带Cookie和完整头）
        switch (type?.toLowerCase()) {
            case 'pic':
                if (data.pic) {
                    const picResponse = await axios.get(data.pic, { 
                        responseType: 'stream',
                        headers: {
                            'User-Agent': userAgent,
                            'Accept': 'image/avif,image/webp,*/*',
                            'Referer': referer,
                            'Connection': 'keep-alive',
                            'Cookie': cookieHeader,
                            ...getFakeIpHeaders(fakeIp)
                        }
                    });
                    res.setHeader('Content-Type', 'image/jpeg');
                    await pipeline(picResponse.data, res);
                } else {
                    res.status(404).json({ error: '未找到图片资源' });
                }
                break;
                
            case 'url':
                if (data.url) {
                    await handleAudioRequest(data.url, req, res, {
                        userAgent,
                        cookieHeader,
                        fakeIp,
                        referer
                    });
                } else {
                    res.status(404).json({ error: '未找到音频资源' });
                }
                break;
                
            case 'lkid':
                res.setHeader('Content-Type', 'text/plain');
                res.send(data.lkid || '未找到lkid值');
                break;
                
            default:
                res.setHeader('Content-Type', 'application/json');
                res.json(data);
        }
    } catch (error) {
        console.error('请求错误:', {
            message: error.message,
            status: error.response?.status,
            headers: error.response?.headers,
            ip: fakeIp
        });
        res.status(500).json({ 
            error: '请求失败', 
            details: error.message,
            status: error.response?.status
        });
    }
};

// 处理音频请求（携带完整头信息）
async function handleAudioRequest(audioUrl, req, res, options) {
    const { userAgent, cookieHeader, fakeIp, referer } = options;
    try {
        // 音频请求头（模拟浏览器播放行为）
        const audioHeaders = {
            'User-Agent': userAgent,
            'Accept': 'audio/webm,audio/ogg,audio/wav,audio/*;q=0.9,application/ogg;q=0.7,*/*;q=0.5',
            'Referer': referer,
            'Connection': 'keep-alive',
            'Cookie': cookieHeader,
            ...getFakeIpHeaders(fakeIp)
        };

        // 先发送HEAD请求获取文件大小
        const headResponse = await axios.head(audioUrl, { headers: audioHeaders });
        const fileSize = parseInt(headResponse.headers['content-length'], 10) || 0;
        
        // 设置响应头
        res.setHeader('Content-Type', 'audio/mp4');
        res.setHeader('Accept-Ranges', 'bytes');
        res.setHeader('Content-Disposition', 'inline');
        
        // 处理范围请求
        const rangeHeader = req.headers.range;
        if (fileSize > 0 && rangeHeader) {
            const rangeMatch = rangeHeader.match(/bytes=(\d+)-(\d*)/);
            if (rangeMatch) {
                const start = Math.max(0, parseInt(rangeMatch[1], 10));
                const end = rangeMatch[2] ? Math.min(parseInt(rangeMatch[2], 10), fileSize - 1) : fileSize - 1;
                const chunkSize = end - start + 1;

                res.statusCode = 206;
                res.setHeader('Content-Range', `bytes ${start}-${end}/${fileSize}`);
                res.setHeader('Content-Length', chunkSize);

                const audioResponse = await axios.get(audioUrl, {
                    responseType: 'stream',
                    headers: { ...audioHeaders, 'Range': `bytes=${start}-${end}` }
                });
                await pipeline(audioResponse.data, res);
                return;
            }
        }
        
        // 返回完整文件
        const audioResponse = await axios.get(audioUrl, { 
            responseType: 'stream',
            headers: audioHeaders
        });
        if (audioResponse.headers['content-length']) {
            res.setHeader('Content-Length', audioResponse.headers['content-length']);
        }
        await pipeline(audioResponse.data, res);
        
    } catch (error) {
        console.error('音频处理错误:', error.message);
        throw error;
    }
}

// 生成伪造IP的头信息集合
function getFakeIpHeaders(ip) {
    return {
        'x-forwarded-for': ip,
        'x-remote-IP': ip,
        'x-remote-ip': ip,
        'x-client-ip': ip,
        'X-Real-IP': ip,
        'client-IP': ip,
        'x-originating-IP': ip,
        'x-remote-addr': ip,
        'Remote_Addr': ip
    };
}
