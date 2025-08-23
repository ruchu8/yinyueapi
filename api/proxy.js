const axios = require('axios');
const { pipeline } = require('stream/promises');

module.exports = async (req, res) => {
    // 设置CORS头
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS, HEAD');
    res.setHeader('Access-Control-Allow-Headers', 'Range, Content-Type');
    res.setHeader('Access-Control-Expose-Headers', 'Accept-Ranges, Content-Range, Content-Length');
    
    // 处理预检请求和HEAD请求
    if (req.method === 'OPTIONS') {
        return res.status(200).end();
    }
    
    if (req.method === 'HEAD') {
        res.setHeader('Accept-Ranges', 'bytes');
        return res.status(200).end();
    }

    // 获取请求参数
    const { id, type } = req.query;
    
    if (!id) {
        return res.status(400).json({ error: '缺少必要的id参数' });
    }

    try {
        // 目标API地址
        const targetUrl = 'https://www.eev3.com/js/play.php';
        
        // 获取音乐信息
        const response = await axios.post(targetUrl, 
            new URLSearchParams({ id, type: 'music' }),
            {
                headers: {
                    'Referer': `https://www.eev3.com/mp3/${id}.html`,
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            }
        );

        const data = response.data;
        
        // 根据类型处理响应
        switch (type?.toLowerCase()) {
            case 'pic':
                if (data.pic) {
                    const picResponse = await axios.get(data.pic, { responseType: 'stream' });
                    res.setHeader('Content-Type', 'image/jpeg');
                    await pipeline(picResponse.data, res);
                } else {
                    res.status(404).json({ error: '未找到图片资源' });
                }
                break;
                
            case 'url':
                if (data.url) {
                    // 处理音频请求，支持范围请求
                    await handleAudioRequest(data.url, req, res);
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
        console.error('请求错误:', error);
        res.status(500).json({ 
            error: '请求失败', 
            details: error.message 
        });
    }
};

// 处理音频请求，支持范围请求
async function handleAudioRequest(audioUrl, req, res) {
    try {
        // 先发送HEAD请求获取文件大小
        const headResponse = await axios.head(audioUrl);
        const fileSize = parseInt(headResponse.headers['content-length'], 10);
        
        // 设置基础响应头
        res.setHeader('Content-Type', 'audio/mp4');
        res.setHeader('Accept-Ranges', 'bytes');
        res.setHeader('Content-Disposition', 'inline');
        
        // 处理范围请求
        const rangeHeader = req.headers.range;
        if (rangeHeader) {
            // 解析Range头 (格式: bytes=start-end)
            const rangeMatch = rangeHeader.match(/bytes=(\d+)-(\d*)/);
            if (rangeMatch) {
                const start = parseInt(rangeMatch[1], 10);
                const end = rangeMatch[2] ? parseInt(rangeMatch[2], 10) : fileSize - 1;
                
                // 确保范围有效
                const actualEnd = Math.min(end, fileSize - 1);
                const chunkSize = actualEnd - start + 1;
                
                // 设置206 Partial Content响应头
                res.statusCode = 206;
                res.setHeader('Content-Range', `bytes ${start}-${actualEnd}/${fileSize}`);
                res.setHeader('Content-Length', chunkSize);
                
                // 请求并传输指定范围的内容
                const audioResponse = await axios.get(audioUrl, {
                    responseType: 'stream',
                    headers: { Range: `bytes=${start}-${actualEnd}` }
                });
                
                await pipeline(audioResponse.data, res);
                return;
            }
        }
        
        // 如果没有范围请求，返回完整文件
        res.setHeader('Content-Length', fileSize);
        const audioResponse = await axios.get(audioUrl, { responseType: 'stream' });
        await pipeline(audioResponse.data, res);
        
    } catch (error) {
        console.error('音频处理错误:', error);
        throw error;
    }
}
