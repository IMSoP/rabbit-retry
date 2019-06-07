<?php

require_once __DIR__ . '/../vendor/autoload.php';

list(, $rabbit_server, $port, $username, $password, $vhost) = $argv;

$conn = new \PhpAmqpLib\Connection\AMQPStreamConnection(
  $rabbit_server, $port, $username, $password, $vhost
);
$channel = $conn->channel();

$real_queue = 'rabbit_retry.v1.test';

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
