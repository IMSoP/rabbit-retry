<?php

namespace IMSoP\RabbitRetry;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

class RetryHandler
{
    /**
     * @var int $numRetries
     */
    private $numRetries;

    /**
     * @var DelayLoop $delayLoop
     */
    private $delayLoop;

    /**
     * @param AMQPChannel $channel
     * @param $queue_name
     * @param $delay
     * @param $num_retries
     */
    public function __construct(AMQPChannel $channel, $queue_name, $delay, $num_retries)
    {
        $this->numRetries = $num_retries;

        $this->delayLoop = DelayLoop::createForNamedQueue($channel, $queue_name, $delay);
    }

    /**
     * Publish a message back to its original queue after failure to be retried
     *  a limited number of times, after which it is discarded.
     *
     * @param AMQPMessage $message The message being handled
     *
     * @return int Number of retries remaining before message will be discarded
     *    0 means the message has already been discarded
     *    1 means the message has been requeued for the last time
     *    >1 means the message will be requeued (n-1) further times if requested
     */
    public function retryMessage(AMQPMessage $message)
    {
        // How many times have we re-tried already?
        $headers = $message->has('application_headers') ? $message->get('application_headers') : array();
        $previous_retry_count = isset($headers['rabbitretry-attempts']) ? $headers['rabbitretry-attempts'][1] : 0;

        // Remaining retries, capped off at 0 for sanity
        $remaining_retries = max(0, $this->numRetries - $previous_retry_count);

        if ($remaining_retries == 0)
        {
            // Send a "nack", which means "discard this message", possibly sending it to a "dead letter exchange"
            $message->delivery_info['channel']->basic_nack($message->delivery_info['delivery_tag']);
        }
        else
        {
            // Set the new header
            $headers['rabbitretry-attempts'] = array('I', $previous_retry_count + 1);
            $message->set('application_headers', $headers);

            // Acknowledge that we've processed this message from the current queue
            $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

            // Requeue the message to its original queue or exchange, with its original routing key
            $this->delayLoop->publishMessage($message, $message->delivery_info['routing_key']);
        }

        return $remaining_retries;
    }
}