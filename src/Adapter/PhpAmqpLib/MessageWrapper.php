<?php

namespace IMSoP\RabbitRetry\Adapter\PhpAmqpLib;

use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPAbstractCollection;
use PhpAmqpLib\Wire\AMQPTable;
use IMSoP\RabbitRetry\Adapter\MessageWrapperInterface;

class MessageWrapper implements MessageWrapperInterface
{
    /**
     * @var AMQPMessage
     */
    private $wrappedMessage;

    /**
     * MessageWrapper constructor.
     * @param AMQPMessage $message
     */
    public function __construct($message)
    {
        $this->wrappedMessage = $message;
    }

    /**
     * Utility function to get a header from a message, since AMQPLib doesn't wrap this
     *
     * @param AMQPMessage $msg
     * @param string $header_name
     * @return mixed|null Null if the header is not set, otherwise its value (normally a string)
     */
    public function getHeader($header_name)
    {
        if ( $this->wrappedMessage->has('application_headers') )
        {
            $headers = $this->wrappedMessage->get('application_headers');
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
     *
     * @param AMQPMessage $msg
     * @param string $header_name
     * @param mixed $value The scalar value to be encoded into the header
     */
    protected function setMessageHeader($header_name, $value)
    {
        if ( $this->wrappedMessage->has('application_headers') )
        {
            $headers = $this->wrappedMessage->get('application_headers');
        }
        else
        {
            $headers = new AMQPTable;
        }

        $headers->set($header_name, $value);

        $this->wrappedMessage->set('application_headers', $headers);
    }
}