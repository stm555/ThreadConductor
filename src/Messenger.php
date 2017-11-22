<?php

namespace ThreadConductor;

interface Messenger
{
    /**
     * Send a message from the identified thread
     * @param string $key
     * @param mixed $result
     * @throws
     */
    public function send($key, $result);

    /**
     * Receive a message from the identified thread
     * @param string $key
     * @return mixed
     */
    public function receive($key);

    /**
     * Flushes message for the given thread identifier and returns it
     * @param string $key
     * @return mixed
     */
    public function flushMessage($key);
}