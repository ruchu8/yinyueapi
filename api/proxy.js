const axios = require('axios');
const { createWriteStream } = require('fs');
const { pipeline } = require('stream/promises');

module.exports = async (req, res) => {
    // 设置CORS headers允许跨域请求
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
    
    // 处理OPTIONS请求
    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }

    // 获取请求参数
    const { id, type } = req.query;
    
    // 验证必要的ID参数
    if (!id) {
        return res.status(400).json({ error: '缺少必要的id参数' });
    }

    try {
        // 目标API地址
        const targetUrl = 'https://www.eev3.com/js/play.php';
        
        // 发送POST请求到目标服务器
        const response = await axios.post(targetUrl, 
            new URLSearchParams({ id, type: 'music' }),
            {
                headers: {
                    'Referer': `https://www.eev3.com/mp3/${id}.html`,
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                responseType: 'json'
            }
        );

        const data = response.data;
        
        // 根据类型返回不同结果
        switch (type?.toLowerCase()) {
            case 'pic':
                if (data.pic) {
                    // 获取图片并转发
                    const picResponse = await axios.get(data.pic, { responseType: 'stream' });
                    res.setHeader('Content-Type', 'image/jpeg');
                    await pipeline(picResponse.data, res);
                } else {
                    res.status(404).json({ error: '未找到图片资源' });
                }
                break;
                
            case 'url':
                if (data.url) {
                    // 获取音频并转发
                    const audioResponse = await axios.get(data.url, { responseType: 'stream' });
                    res.setHeader('Content-Type', 'audio/mp4');
                    res.setHeader('Content-Disposition', 'inline');
                    res.setHeader('Accept-Ranges', 'bytes');
                    await pipeline(audioResponse.data, res);
                } else {
                    res.status(404).json({ error: '未找到音频资源' });
                }
                break;
                
            case 'lkid':
                res.setHeader('Content-Type', 'text/plain');
                res.send(data.lkid || '未找到lkid值');
                break;
                
            default:
                // 返回原始JSON数据
                res.setHeader('Content-Type', 'application/json');
                res.json(data);
        }
    } catch (error) {
        console.error('请求错误:', error);
        res.status(500).json({ 
            error: '请求失败', 
            details: error.message 
        });
    }
};
