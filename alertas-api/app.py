from flask import Flask, jsonify, request
from datetime import datetime
import logging
import json
import redis
import threading
import pika
import time
import os

# Configure logging
logging.basicConfig(level=logging.INFO, 
                    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s')
logger = logging.getLogger(__name__)

app = Flask(__name__)

# Redis 
redis_client = redis.Redis(
    host=os.environ.get('REDIS_HOST', 'localhost'),
    port=int(os.environ.get('REDIS_PORT', 6379)),
    decode_responses=True
)

# armazenamento em memória para eventos
events = []

def get_events_from_cache():
    """Retrieve events from Redis cache or memory if cache unavailable"""
    try:
        cached_events = redis_client.get('events')
        if cached_events:
            return json.loads(cached_events)
        return events
    except Exception as e:
        logger.error(f"Error retrieving events from Redis: {e}")
        return events

def update_events_cache():
    """Update Redis cache with current events"""
    try:
        redis_client.set('events', json.dumps(events))
        logger.info("Events cache updated")
    except Exception as e:
        logger.error(f"Error updating Redis cache: {e}")

def start_rabbitmq_consumer():
    """Start RabbitMQ consumer in a separate thread"""
    threading.Thread(target=rabbitmq_consumer, daemon=True).start()

def rabbitmq_consumer():
    """RabbitMQ consumer to receive logistics messages"""
    while True:
        try:
            # Connect to RabbitMQ
            connection = pika.BlockingConnection(
                pika.ConnectionParameters(
                    host=os.environ.get('RABBITMQ_HOST', 'localhost'),
                    heartbeat=600
                )
            )
            channel = connection.channel()
            
            # Declare the queue we want to consume from
            channel.queue_declare(queue='logistics_urgent_queue', durable=True)
            
            logger.info("Connected to RabbitMQ, waiting for messages...")
            
            # Set up the consumer callback
            def callback(ch, method, properties, body):
                try:
                    # Process the message
                    logistics_data = json.loads(body.decode('utf-8'))
                    
                    # Add timestamp and source
                    logistics_data['received_at'] = datetime.now().isoformat()
                    logistics_data['source'] = 'rabbitmq'
                    
                    # Store in events list
                    events.append(logistics_data)
                    
                    # Update cache
                    update_events_cache()
                    
                    logger.info(f"Received logistics message: {logistics_data}")
                    
                    # Acknowledge message
                    ch.basic_ack(delivery_tag=method.delivery_tag)
                except Exception as e:
                    logger.error(f"Error processing message: {e}")
                    ch.basic_nack(delivery_tag=method.delivery_tag, requeue=True)
            
            # Configure consumer
            channel.basic_qos(prefetch_count=1)
            channel.basic_consume(queue='logistics_urgent_queue', on_message_callback=callback)
            
            # Start consuming
            channel.start_consuming()
            
        except Exception as e:
            logger.error(f"RabbitMQ consumer error: {e}")
            time.sleep(5)  # Wait before reconnecting
            

@app.route('/event', methods=['POST'])
def receive_event():
    """Endpoint to receive alerts from Node.js API"""
    if not request.is_json:
        return jsonify({"error": "Invalid JSON"}), 400
    
    # recebe os dados do evento
    event_data = request.get_json()
    
    #  timestamp and source
    event_data['received_at'] = datetime.now().isoformat()
    event_data['source'] = 'http'
    events.append(event_data)
    
    # atualiza o cache
    update_events_cache()
    
    logger.info(f"Event received via HTTP: {event_data}")
    
    return jsonify({
        "message": "Event received successfully", 
        "event": event_data
    }), 201

@app.route('/events', methods=['GET'])
def get_events():
    """Endpoint to retrieve all received events"""
    # Try to get events from cache
    all_events = get_events_from_cache()
    
    return jsonify({
        "total_events": len(all_events),
        "events": all_events
    })

if __name__ == '__main__':
    # Start RabbitMQ consumer thread
    start_rabbitmq_consumer()
    
    logger.info("Starting Critical Events API")
    app.run(host='0.0.0.0', port=3001, debug=True)