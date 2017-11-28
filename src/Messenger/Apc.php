<?php

namespace ThreadConductor\Messenger;

use ThreadConductor\Messenger as MessengerInterface;

class Apc implements MessengerInterface
{
    /**
     * @var string Prefix for keys in apc used with this messenger
     */
    public $messengerPrefix = 'thread';

    /**
     * @var int Maximum time to keep the value around in the APC cache, in seconds
     */
    public $timeToLive = 60;

    public function __construct($messengerPrefix = 'thread', $timeToLive = 60)
    {
        $this->messengerPrefix = $messengerPrefix;
        $this->timeToLive = $timeToLive;
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
        apc_store($key, $value, $timeToLive);
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
        return apc_fetch($key, $successFlag);
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
        apc_delete($key);
    }
}