<h3>Explicacao</h3>

<h3>API Modulo de sensores</h3>
Fornece dados mockados para simular poço de petróleo e envia alertas 
para uma API de alertas

O endpoint 
/sensor-data irá retornar os dados
O redis foi usado aqui, ele armazena os dados mockados em cache para evitar criar novamente a toda requisicao (ttl 60)

/alert irá enviar os alertas 
Maneira simplificada, não tem adição de outros serviços.

<h2>API Modulo de eventos criticos</h2>
/event (POST)
Recebe os alertas da API Modulo de sensores (sensores-api/alert)

