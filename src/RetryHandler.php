<?php

namespace IMSoP\RabbitRetry;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPAbstractCollection;
use PhpAmqpLib\Wire\AMQPTable;

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
        $previous_retry_count = $this->getHeader($message, 'rabbitretry-attempts') ?: 0;

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
            $this->setMessageHeader($message, 'rabbitretry-attempts',  $previous_retry_count + 1);

            // Acknowledge that we've processed this message from the current queue
            $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

            // Requeue the message to its original queue or exchange, with its original routing key
            $this->delayLoop->publishMessage($message, $message->delivery_info['routing_key']);
        }

        return $remaining_retries;
    }
    
    /**
     * Utility function to get a header from a message, since AMQPLib doesn't wrap this
     * @todo Move to an adapter class of some sort
     *
     * @param AMQPMessage $message
     * @param string $header_name
     * @return mixed|null Null if the header is not set, otherwise its value (normally a string)
     */
    private function getHeader(AMQPMessage $message, $header_name)
    {
        if ( $message->has('application_headers') )
        {
            $headers = $message->get('application_headers');
            if ( $headers instanceof AMQPAbstractCollection )
            {
                $headers = $headers->getNativeData();
            }
            if ( is_array($headers) && isset($headers[$header_name]) )
            {
                return $headers[$header_name];
            }
        }
        return null;
    }
    /**
     * Utility function to set a header on a message, since AMQPLib doesn't wrap this
     * @todo Move to an adapter class of some sort
     *
     * @param AMQPMessage $message
     * @param string $header_name
     * @param mixed $value The scalar value to be encoded into the header
     */
    private function setMessageHeader(AMQPMessage $message, $header_name, $value)
    {
        if ( $message->has('application_headers') )
        {
            $headers = $message->get('application_headers');
        }
        else
        {
            $headers = new AMQPTable;
        }
        $headers->set($header_name, $value);
        $message->set('application_headers', $headers);
    }
}
