<?php

namespace ThreadConductor\Messenger;

use ThreadConductor\Messenger as MessengerInterface;

/**
 * Class Apc
 * This uses the key/value shared memory store portion of APC
 *  - not to be confused with the op-code caching feature of APC which is redundant in modern versions of PHP
 * @see https://github.com/krakjoe/apcu
 *
 * @package ThreadConductor\Messenger
 */
class Apc implements MessengerInterface
{
    const APC_VARIANT_APC = 'APC';
    const APC_VARIANT_APCU = 'APCu';

    static protected $apcVariant = 'APC';

    /**
     * @var string Prefix for keys in apc used with this messenger
     */
    public $messengerPrefix = 'thread';

    /**
     * @var int Maximum time to keep the value around in the APC cache, in seconds
     */
    public $timeToLive = 60;

    /**
     * @param string $messengerPrefix
     * @param int $timeToLive
     * @throws \Exception
     */
    public function __construct($messengerPrefix = 'thread', $timeToLive = 60)
    {
        $this->validateEnvironment();
        $this->messengerPrefix = $messengerPrefix;
        $this->timeToLive = $timeToLive;
    }

    /**
     * @codeCoverageIgnore
     *
     * @throws \Exception When the current environment is incompatible with this adapter
     */
    protected function validateEnvironment()
    {
        //verify apc shared-memory cache is available
        if (function_exists('apc_store')) {
            self::$apcVariant = self::APC_VARIANT_APC;
            return;
        }
        if (function_exists('apcu_store')) {
            self::$apcVariant = self::APC_VARIANT_APCU;
            return;
        }
        throw new \Exception('APC/APCu Unavailable for inter-process communication');
    }

    /**
     * Generates a key for this messenger
     * @param $key
     * @return string
     */
    protected function generateMessageIdentifier($key)
    {
        return $this->messengerPrefix . $key;
    }

    /**
     * Send a message from the identified thread
     * @param string $key
     * @param mixed $result
     */
    public function send($key, $result)
    {
        $this->store($this->generateMessageIdentifier($key), $result, $this->timeToLive);
    }

    /**
     * Receive a message from the identified thread
     * @param string $key
     * @return mixed
     */
    public function receive($key)
    {
        return $this->fetch($this->generateMessageIdentifier($key));
    }

    /**
     * Receive the current message for this key then clear the value
     * @param string $key
     * @return mixed
     */
    public function flushMessage($key)
    {
        $message = $this->receive($key);
        $this->delete($this->generateMessageIdentifier($key));
        return $message;
    }

    /**
     * Wrapper function for the native apc function
     * This is intended as a thing wrapper around the apc call
     * @codeCoverageIgnore
     *
     * @param string $key
     * @param mixed $value
     * @param int $timeToLive in seconds
     */
    protected function store($key, $value, $timeToLive)
    {
        //@todo add failure handling?
        switch(self::$apcVariant)
        {
            case self::APC_VARIANT_APC:
                apc_store($key, $value, $timeToLive);
                return;
            case self::APC_VARIANT_APCU:
                apcu_store($key, $value, $timeToLive);
                return;
            default:
                return; //if neither are available don't do anything
        }
    }

    /**
     * Wrapper function for the native apc function
     * This is intended as a thing wrapper around the apc call
     * @codeCoverageIgnore
     *
     * @param string $key
     * @return mixed
     */
    protected function fetch($key)
    {
        //@todo add failure handling?
        switch(self::$apcVariant)
        {
            case self::APC_VARIANT_APC:
                return apc_fetch($key, $successFlag);
            case self::APC_VARIANT_APCU:
                return apcu_fetch($key, $successFlag);
            default:
                return; //if neither are available don't do anything
        }
    }

    /**
     * Wrapper function for the native apc function
     *
     * This is intended as a thing wrapper around the apc call
     * @codeCoverageIgnore
     *
     * @param string $key
     */
    protected function delete($key)
    {
        //@todo add failure handling?
        switch(self::$apcVariant)
        {
            case self::APC_VARIANT_APC:
                apc_delete($key);
                return;
            case self::APC_VARIANT_APCU:
                apcu_delete($key);
                return;
            default:
                return; //if neither are available don't do anything
        }

    }
}