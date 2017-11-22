<?php

namespace ThreadConductor\Messenger;

use ThreadConductor\Messenger as MessengerInterface;

class Apc implements MessengerInterface
{
    public $messengerPrefix = 'thread';

    public function __construct($messengerPrefix = 'thread')
    {
        $this->messengerPrefix = $messengerPrefix;
    }

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
        //@todo add failure handling?
        apc_store($this->generateMessageIdentifier($key), $result);
    }

    /**
     * Receive a message from the identified thread
     * @param string $key
     * @return mixed
     */
    public function receive($key)
    {
        //@todo add failure handling?
        return apc_fetch($this->generateMessageIdentifier($key), $successFlag);
    }

    public function flushMessage($key)
    {
        $message = $this->receive($key);
        apc_delete($this->generateMessageIdentifier($key));
        return $message;
    }
}