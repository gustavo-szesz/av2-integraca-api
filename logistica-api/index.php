<?php
require 'vendor/autoload.php';

use Slim\Factory\AppFactory;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Create Slim app
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// CORS headers
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

// GET /equipments - Return a simulated list of equipment
$app->get('/equipments', function (Request $request, Response $response) {
    $equipments = [
        [
            'id' => 1,
            'name' => 'Compressor Industrial',
            'category' => 'Maquinário',
            'status' => 'Disponível',
            'location' => 'Depósito A',
            'serial_number' => 'CP-2023-1001'
        ],
        [
            'id' => 2,
            'name' => 'Sensor de Temperatura',
            'category' => 'Sensores',
            'status' => 'Em manutenção',
            'location' => 'Laboratório',
            'serial_number' => 'ST-2023-4532'
        ],
        [
            'id' => 3,
            'name' => 'Válvula de Controle',
            'category' => 'Componentes',
            'status' => 'Disponível',
            'location' => 'Depósito B',
            'serial_number' => 'VC-2023-7845'
        ],
        [
            'id' => 4,
            'name' => 'Motor Elétrico',
            'category' => 'Maquinário',
            'status' => 'Em transporte',
            'location' => 'Em trânsito',
            'serial_number' => 'ME-2023-9987'
        ],
        [
            'id' => 5,
            'name' => 'Kit de Ferramentas',
            'category' => 'Ferramentas',
            'status' => 'Disponível',
            'location' => 'Depósito C',
            'serial_number' => 'KF-2023-1122'
        ]
    ];
    
    $payload = json_encode($equipments);
    $response->getBody()->write($payload);
    
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

$app->post('/dispatch', function (Request $request, Response $response) {
    $data = $request->getParsedBody();
    
    // Validate request data
    if (!isset($data['equipment_id']) || !isset($data['destination']) || !isset($data['priority'])) {
        $response->getBody()->write(json_encode([
            'error' => 'Missing required fields',
            'required' => ['equipment_id', 'destination', 'priority']
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }
    
    // Add timestamp
    $data['timestamp'] = date('Y-m-d H:i:s');
    
    try {
        // Connect to RabbitMQ
        $connection = new AMQPStreamConnection(
            'rabbitmq',  // host - use service name from docker-compose
            5672,        // port
            'guest',     // user
            'guest',     // password
            '/'          // vhost
        );
        
        $channel = $connection->channel();
        
        // Declare queue
        $channel->queue_declare(
            'logistics_urgent_queue', // queue name
            false,                   // passive
            true,                    // durable
            false,                   // exclusive
            false                    // auto_delete
        );
        
        // Create message
        $msg = new AMQPMessage(
            json_encode($data),
            ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]
        );
        
        // Publish message
        $channel->basic_publish($msg, '', 'logistics_urgent_queue');
        
        // Close connection
        $channel->close();
        $connection->close();
        
        // Return success response
        $responseData = [
            'status' => 'success',
            'message' => 'Dispatch request sent to logistics queue',
            'request_id' => uniqid(),
            'timestamp' => $data['timestamp']
        ];
        
        $response->getBody()->write(json_encode($responseData));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(202);  // Accepted
            
    } catch (Exception $e) {
        // Handle errors
        $response->getBody()->write(json_encode([
            'error' => 'Failed to dispatch message',
            'message' => $e->getMessage()
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

// Run app
$app->run();