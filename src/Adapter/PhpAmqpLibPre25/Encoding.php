<?php
/**
 * Created by PhpStorm.
 * User: Rowan
 * Date: 23/04/2017
 * Time: 22:53
 */

namespace IMSoP\RabbitRetry\Adapter\PhpAmqpLibPre25;


class Encoding
{
    const T_INT_SHORTSHORT = 'b';
    const T_INT_SHORT = 's';
    const T_INT_LONG = 'I';
    const T_INT_LONGLONG = 'l';
    const T_DECIMAL = 'D';
    const T_TIMESTAMP = 'T';
    const T_VOID = 'V';
    const T_BOOL = 't';
    const T_STRING_LONG = 'S';
    const T_ARRAY = 'A';
    const T_TABLE = 'F';

    /**
     * @param mixed $val
     * @return array
     * @throws \OutOfBoundsException
     */
    public function encodeValue($val)
    {
        if (is_string($val)) {
            return array(self::T_STRING_LONG, $val);
        } elseif (is_float($val)) {
            return array(self::T_STRING_LONG, $val);
        } elseif (is_int($val)) {
            if (($val >= -2147483648) && ($val <= 2147483647)) {
                return array(self::T_INT_LONG, $val);
            } else {
                return array(self::T_STRING_LONG, $val);
            }
        } elseif (is_bool($val)) {
            return array(self::T_BOOL, $val);
        } elseif (is_null($val)) {
            return array(self::T_VOID, null);
        } elseif ($val instanceof \DateTime) {
            return array(self::T_TIMESTAMP, $val->getTimestamp());
        } elseif (is_array($val)) {
            return self::encodeArray($val);
        } else {
            throw new \OutOfBoundsException(sprintf('Encountered value of unsupported type: %s', gettype($val)));
        }
    }

    public static function encodeArray($php_array)
    {
        $encoded_values = array();
        foreach ( $php_array as $key => $php_value ) {
            $encoded_values[ $key ] = self::encodeValue($php_value);
        }

        return array(self::T_ARRAY, $encoded_values);
    }
}