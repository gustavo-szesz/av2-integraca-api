const express = require('express');
const axios = require('axios');
const redis = require('redis');
const { promisify } = require('util');

const PORT = process.env.PORT || 3000;
const PYTHON_API_URL = process.env.PYTHON_API_URL || 'http://localhost:3001';
const CACHE_EXPIRATION = 60; // 1 minuto

// REDIS
const redisClient = redis.createClient({
    url: process.env.REDIS_URL || 'redis://localhost:6379'
});

redisClient.on('error', (err) => {
    console.error('Erro de conexão com o Redis:', err);
});

const getAsync = promisify(redisClient.get).bind(redisClient);
const setAsync = promisify(redisClient.set).bind(redisClient);

const app = express();
app.use(express.json());

app.get('/sensor-data', async (req, res) => {
  try {
    // reber do cache
    const cachedData = await getAsync('sensor-data');
    
    if (cachedData) {
      console.log('Serving sensor data from cache');
      return res.json(JSON.parse(cachedData));
    }
    
    // gera novos dados, caso nao esteja no cache
    const sensorData = {
      timestamp: new Date().toISOString(),
      temperature: Math.random() * 30 + 10, // 10-40°C
      pressure: Math.random() * 50 + 950,   // 950-1000 hPa
    };
    
    // grava o cache
    await setAsync('sensor-data', JSON.stringify(sensorData), 'EX', CACHE_EXPIRATION);
    console.log('Generated new sensor data and cached it');
    
    res.json(sensorData);
  } catch (error) {
    console.error('Error serving sensor data:', error);
    res.status(500).json({ error: 'Failed to retrieve sensor data' });
  }
});


app.post('/alert', async (req, res) => {
    const alertData = req.body;
    console.log('Alerta recebido:', alertData);
    
    try {
        // envia o alerta para a API Python
        const response = await axios.post(`${PYTHON_API_URL}/event`, alertData);
        console.log('Alert forwarded to Python API, response:', response.data);
        
        res.status(201).json({ 
            message: 'Alerta recebido e encaminhado com sucesso',
            pythonResponse: response.data
        });
    } catch (error) {
        console.error('Error forwarding alert to Python API:', error);
        res.status(201).json({ 
            message: 'Alerta recebido com sucesso, mas falha ao encaminhar para API Python',
            error: error.message
        });
    }
});

app.listen(PORT, () => {
  console.log(`Servidor rodando na porta ${PORT}`);
});
