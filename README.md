<h3>Explicação</h3>

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
Recebe os alertas da API Modulo de sensores (sensores-api/alert), detalhe
é que essa comunicação é síncrona, para que a mensagem chegue inteira
O redis aqui é utlizado para cachear a lista de eventos recebidos, mantendo também uma variavel em memória como reserva

/events (GET)
Lista todos os eventos que recebe pelo RabbitMQ x  /dispatch (php)


<h2>API Modulo de Logistica</h2>
/equipaments (GET)
Retorna um Json de todos os equipamentos, dados mockados 

/dispatch (POST)
Envia mensagem de logistica para a fila do RabbitMQ
a comunicação é assincrona pela biblioteca AMQPS, que é a padrão do Rabbit 
as mensagens disponiveis no Rabbit são marcadas como persistentes para não serem perdidas


<h2>Rodar o programa</h2>
cd av2-integraca-api

Iniciando os serviços: 

docker-compose up -d

Verificar se esta tudo rodando
docker-compose ps

# - API Node.js: http://localhost:3000
# - API Python: http://localhost:3001
# - API PHP: http://localhost:3004
# - Interface RabbitMQ: http://localhost:15672




