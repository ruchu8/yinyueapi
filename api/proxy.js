const axios = require('axios');
const axiosRetry = require('axios-retry');
const { pipeline } = require('stream/promises');

// 配置axios重试机制
axiosRetry(axios, {
  retries: 3, // 重试3次
  retryDelay: (retryCount) => {
    // 指数退避策略：1s, 2s, 4s...
    return retryCount * 1000;
  },
  retryCondition: (error) => {
    // 需要重试的条件
    return (
      error.code === 'ETIMEDOUT' ||
      error.code === 'ECONNRESET' ||
      error.code === 'ENOTFOUND' ||
      (error.response && error.response.status >= 500)
    );
  }
});

// 全局超时设置
const GLOBAL_TIMEOUT = 30000; // 30秒

module.exports = async (req, res) => {
  // 设置CORS头，允许跨域请求
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS, HEAD');
  res.setHeader('Access-Control-Allow-Headers', 'Range, Content-Type, Authorization');
  res.setHeader('Access-Control-Expose-Headers', 'Accept-Ranges, Content-Range, Content-Length');
  
  // 处理预检请求
  if (req.method === 'OPTIONS') {
    return res.status(200).end();
  }
  
  // 处理HEAD请求
  if (req.method === 'HEAD') {
    res.setHeader('Accept-Ranges', 'bytes');
    return res.status(200).end();
  }

  // 获取请求参数
  const { id, type, proxy } = req.query;
  
  // 验证必要参数
  if (!id) {
    return res.status(400).json({ error: '缺少必要的id参数' });
  }

  try {
    // 目标API地址
    const targetUrl = 'https://www.eev3.com/js/play.php';
    
    // 准备请求配置
    const requestConfig = {
      method: 'post',
      url: targetUrl,
      data: new URLSearchParams({ id, type: 'music' }),
      headers: {
        'Referer': `https://www.eev3.com/mp3/${id}.html`,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0',
        'Content-Type': 'application/x-www-form-urlencoded',
        'cache-control': 'max-age=0',
        'accept-language': 'zh-CN,zh;q=0.9',
        'priority': 'u=0, i' // 补充原PHP中的请求头
      },
      timeout: GLOBAL_TIMEOUT
    };
    
    // 如果提供了代理参数，则使用代理
    if (proxy) {
      try {
        const proxyConfig = JSON.parse(proxy);
        requestConfig.proxy = proxyConfig;
      } catch (e) {
        console.warn('无效的代理配置，将忽略代理');
      }
    }
    
    // 获取音乐信息
    const response = await axios(requestConfig);
    const data = response.data;
    
    // 根据类型处理响应
    switch (type?.toLowerCase()) {
      case 'pic':
        await handleImageRequest(data.pic, res);
        break;
        
      case 'url':
        if (data.url) {
          await handleAudioRequest(data.url, req, res, proxy);
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
    console.error('请求错误详情:', {
      message: error.message,
      code: error.code,
      stack: error.stack,
      id: id,
      type: type
    });
    
    // 根据错误类型返回更具体的错误信息
    let errorMessage = '请求失败';
    let statusCode = 500;
    
    if (error.code === 'ETIMEDOUT') {
      errorMessage = '连接超时，请稍后重试';
    } else if (error.code === 'ENOTFOUND') {
      errorMessage = '无法找到目标服务器';
    } else if (error.code === 'ECONNREFUSED') {
      errorMessage = '连接被拒绝';
    } else if (error.response) {
      errorMessage = `目标服务器返回错误: ${error.response.status}`;
      statusCode = error.response.status;
    }
    
    res.status(statusCode).json({
      error: errorMessage,
      details: process.env.NODE_ENV === 'development' ? error.message : undefined,
      code: error.code
    });
  }
};

// 处理图片请求
async function handleImageRequest(imageUrl, res) {
  if (!imageUrl) {
    return res.status(404).json({ error: '未找到图片资源' });
  }
  
  try {
    const response = await axios.get(imageUrl, {
      responseType: 'stream',
      timeout: GLOBAL_TIMEOUT
    });
    
    res.setHeader('Content-Type', response.headers['content-type'] || 'image/jpeg');
    res.setHeader('Cache-Control', 'public, max-age=86400'); // 缓存1天
    
    await pipeline(response.data, res);
  } catch (error) {
    console.error('图片处理错误:', error);
    throw new Error(`图片请求失败: ${error.message}`);
  }
}

// 处理音频请求，支持范围请求
async function handleAudioRequest(audioUrl, req, res, proxyConfig) {
  try {
    // 准备请求配置
    const requestConfig = {
      timeout: GLOBAL_TIMEOUT,
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36 Edg/138.0.0.0'
      }
    };
    
    // 如果提供了代理参数，则使用代理
    if (proxyConfig) {
      try {
        requestConfig.proxy = JSON.parse(proxyConfig);
      } catch (e) {
        console.warn('无效的代理配置，将忽略代理');
      }
    }
    
    // 先发送HEAD请求获取文件大小
    const headResponse = await axios.head(audioUrl, requestConfig);
    const fileSize = parseInt(headResponse.headers['content-length'] || '0', 10);
    
    // 设置基础响应头
    res.setHeader('Content-Type', headResponse.headers['content-type'] || 'audio/mp4');
    res.setHeader('Accept-Ranges', 'bytes');
    res.setHeader('Content-Disposition', 'inline');
    res.setHeader('Cache-Control', 'public, max-age=3600'); // 缓存1小时
    
    // 处理范围请求
    const rangeHeader = req.headers.range;
    if (rangeHeader && fileSize > 0) {
      // 解析Range头 (格式: bytes=start-end)
      const rangeMatch = rangeHeader.match(/bytes=(\d+)-(\d*)/);
      if (rangeMatch) {
        const start = parseInt(rangeMatch[1], 10);
        const end = rangeMatch[2] ? Math.min(parseInt(rangeMatch[2], 10), fileSize - 1) : fileSize - 1;
        
        // 确保范围有效
        if (start >= fileSize) {
          return res.status(416).setHeader('Content-Range', `bytes */${fileSize}`).end();
        }
        
        const chunkSize = end - start + 1;
        
        // 设置206 Partial Content响应头
        res.statusCode = 206;
        res.setHeader('Content-Range', `bytes ${start}-${end}/${fileSize}`);
        res.setHeader('Content-Length', chunkSize);
        
        // 请求并传输指定范围的内容
        const audioResponse = await axios.get(audioUrl, {
          ...requestConfig,
          responseType: 'stream',
          headers: {
            ...requestConfig.headers,
            Range: `bytes=${start}-${end}`
          }
        });
        
        await pipeline(audioResponse.data, res);
        return;
      }
    }
    
    // 如果没有范围请求或文件大小未知，返回完整文件
    if (fileSize > 0) {
      res.setHeader('Content-Length', fileSize);
    }
    
    const audioResponse = await axios.get(audioUrl, {
      ...requestConfig,
      responseType: 'stream'
    });
    
    await pipeline(audioResponse.data, res);
    
  } catch (error) {
    console.error('音频处理错误:', error);
    
    // 对于HEAD请求失败的情况，尝试直接获取完整内容
    if (error.config && error.config.method === 'head') {
      console.log('HEAD请求失败，尝试直接获取完整音频内容');
      try {
        const audioResponse = await axios.get(audioUrl, {
          ...(proxyConfig ? { proxy: JSON.parse(proxyConfig) } : {}),
          responseType: 'stream',
          timeout: GLOBAL_TIMEOUT
        });
        
        res.setHeader('Content-Type', audioResponse.headers['content-type'] || 'audio/mp4');
        res.setHeader('Accept-Ranges', 'bytes');
        res.setHeader('Content-Disposition', 'inline');
        
        await pipeline(audioResponse.data, res);
        return;
      } catch (e) {
        console.error('直接获取音频失败:', e);
        throw new Error(`音频请求失败: ${e.message}`);
      }
    }
    
    throw new Error(`音频请求失败: ${error.message}`);
  }
}
