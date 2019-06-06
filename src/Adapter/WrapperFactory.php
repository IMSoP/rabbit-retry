<?php

namespace IMSoP\RabbitRetry\Adapter;

use PhpAmqpLib\Message\AMQPMessage;

class WrapperFactory
{
    /**
     * Prior to version 2.5, php-amqplib used complex arrays to represent complex AMQP types;
     * later versions use dedicated value objects.
     * There are therefore two different adapters to handle all versions.
     *
     * @return boolean true if the value classes exist, implying php-ampqlib >= 2.5.0
     */
    private static function haveAMQPValueObjects()
    {
        return class_exists('\PhpAmqpLib\Wire\AMQPAbstractCollection');
    }

    /**
     * @param miexed $message
     * @return MessageWrapperInterface
     * @throws WrappingException
     */
    public static function wrapMessage($message)
    {
        if ( $message instanceof AMQPMessage ) {
            if ( self::haveAMQPValueObjects() ) {
                return new PhpAmqpLib\MessageAdapter($message);
            }
            else {
                return new PhpAmqpLibPre25\MessageAdapter($message);
            }
        }
        elseif ( $message instanceof \AMQPEnvelope ) {
            return new PeclAmqp\MessageAdapter($message);
        }
        else {
            throw new WrappingException('Could not identify type of MessageAdapter to instantiate.');
        }
    }
}