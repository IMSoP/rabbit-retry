<?php

namespace IMSoP\RabbitRetry;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class DelayLoop
{
    const NAME_PREFIX = 'rabbit_retry.v1';

    /**
     * @var \PhpAmqpLib\Channel\AMQPChannel $channel
     */
    private $channel;

    /**
     * @var string $waiting_exchange The exchange we publish messages to when we want to delay them
     */
    private $waiting_exchange;

    /**
     * Private constructor which sets up the actual delay loop.
     *
     * @param AMQPChannel $channel
     * @param string $done_exchange The exchange the message should end up in after the delay
     * @param string $delivery_suffix The suffix to use when naming additional queues and exchanges
     * @param int $delay_seconds
     */
    private function __construct(AMQPChannel $channel, $done_exchange, $delivery_suffix, $delay_seconds)
    {
        $this->channel = $channel;

        // Create a queue with no consumers, a TTL, and a DLX, as the actual delay loop
        // Note that different delays on the same queue/exchange require different queues
        //  e.g. delaying queue "foo" for 30 seconds uses "rabbit_retry.v1.waiting.30.queue.foo"
        $waiting_queue = self::NAME_PREFIX . '.waiting.'
            . (string)$delay_seconds
            . $delivery_suffix;
        $this->channel->queue_declare(
            $waiting_queue,
            false,
            true,
            false,
            false,
            false,
            array(
                // After a message has been here for X seconds...
                'x-message-ttl'          => array('I', $delay_seconds * 1000),
                // ... send it to the $done_exchange (and thus the target queue)
                'x-dead-letter-exchange' => array('S', $done_exchange),
                // If we don't mention this queue for twice the timeout, delete the whole queue
                #TODO This doesn't work, because we're not consuming from or re-declaring the queue
                # 'x-expires'              => array('I', $delay_seconds * 2000)
            )
        );

        // Create a fanout exchange so we can publish to the delay queue with any routing key
        $waiting_exchange = self::NAME_PREFIX . '.begin' . $delivery_suffix;
        $channel->exchange_declare(
            $waiting_exchange,
            'fanout',
            false,
            true,
            // Set to auto-delete, which will delete the exchange once the queue expires
            true
        );
        // Bind the queue we set the TTL on to the exchange we're going to publish via
        $channel->queue_bind($waiting_queue, $waiting_exchange);

        // The exchange is the only thing we need to remember the name of
        $this->waiting_exchange = $waiting_exchange;
    }

    /**
     * Create a delay loop based on an open connection, and a queue name.
     * This creates an additional temporary exchange, which joins the queue onto the end of the delay loop.
     * This is useful for cycling messages to the back of the same queue you read them from, while retaining
     *   their original routing key.
     *
     * @param AMQPChannel $channel
     * @param string $queue_name
     * @param int $delay_seconds
     * @return static
     */
    public static function createForNamedQueue(AMQPChannel $channel, $queue_name, $delay_seconds)
    {
        $delivery_suffix = '.queue.' . $queue_name;

        // Create an exchange whose purpose is to push directly to the specified queue
        $done_exchange = self::NAME_PREFIX . '.done' . $delivery_suffix;
        $channel->exchange_declare(
            $done_exchange,
            'fanout',
            false,
            true,
            // Set to auto-delete, which will delete this exchange once the target queue is deleted
            true // Auto-delete
        );
        $channel->queue_bind($queue_name, $done_exchange);

        return new static($channel, $done_exchange, $delivery_suffix, $delay_seconds);
    }

    /**
     * Create a delay loop based on an open connection, and an exchange name
     * This pushes messages directly into the exchange after the set delay, just as though you had
     *   published them there directly.
     *
     * @param AMQPChannel $channel
     * @param string $exchange_name
     * @param int $delay_seconds
     * @return static
     */
    public static function createForNamedExchange(AMQPChannel $channel, $exchange_name, $delay_seconds)
    {
        $delivery_suffix = '.exchange.' . $exchange_name;

        return new static($channel, $exchange_name, $delivery_suffix, $delay_seconds);
    }

    /**
     * Send a message via the delay mechanism
     *
     * @param AMQPMessage $message
     * @param string $routing_key The routing key which the message should maintain throughout its journey
	 */
	public function publishMessage(AMQPMessage $message, $routing_key)
    {
        // Publish the original message to the delay exchange, routing key in tact
        $this->channel->basic_publish(
            $message,
            $this->waiting_exchange,
            $routing_key
        );
    }
}