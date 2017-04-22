<?php

require_once __DIR__ . '/../vendor/autoload.php';

$conn = new \PhpAmqpLib\Connection\AMQPStreamConnection(
    'localhost', 5672, 'guest', 'guest'
);
$channel = $conn->channel();

$real_queue = 'test1';

$channel->queue_declare($real_queue);

$retry_handler = new \IMSoP\RabbitRetry\RetryHandler($channel, $real_queue, 5, 5);

$channel->basic_consume(
    $real_queue,
    null,
    false,
    false,
    false,
    false,
    function($msg) use ($retry_handler) {
        echo 'RECEIVED: ', $msg->body, PHP_EOL;
        $remaining = $retry_handler->retryMessage($msg);
        echo $remaining, ' retries remaining...', PHP_EOL;
    }
);

echo 'PUBLISHING...', PHP_EOL;
$channel->basic_publish(new \PhpAmqpLib\Message\AMQPMessage('Hello World'), '', $real_queue);

while ( true ) {
    $channel->wait();
}