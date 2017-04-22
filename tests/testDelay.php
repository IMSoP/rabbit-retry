<?php

require_once __DIR__ . '/../vendor/autoload.php';

$conn = new \PhpAmqpLib\Connection\AMQPStreamConnection(
  'localhost', 5672, 'guest', 'guest'
);
$channel = $conn->channel();

$real_queue = 'test1';

$channel->queue_declare($real_queue);

$channel->basic_consume(
    $real_queue,
    null,
    false,
    false,
    false,
    false,
    function($msg) {
        echo 'RECEIVED: ', $msg->body, PHP_EOL;
    }
);

$delay_loop = \IMSoP\RabbitRetry\DelayLoop::createForNamedQueue($channel, $real_queue, 5);

echo 'PUBLISHING WITH DELAY...';
$delay_loop->publishMessage(new \PhpAmqpLib\Message\AMQPMessage('Hello World'), 'test');

$channel->wait();