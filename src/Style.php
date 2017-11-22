<?php

namespace ThreadConductor;

use ThreadConductor\Exception\ConcurrentLimitExceeded;
use ThreadConductor\Exception\Fail as FailException;
use ThreadConductor\Exception\ConcurrentLimitExceeded as ConcurrentLimitExceededException;

interface Style
{
    /**
     * @param callable $action Action to spawn
     * @param mixed[] $arguments Arguments to the action
     * @return string An identifier for the thread that was spawned
     * @throws FailException Thrown when the thread fails to spawn
     * @throws ConcurrentLimitExceededException Thrown when the style has reached the limit of active threads available
     */
    public function spawn(Callable $action, array $arguments);

    /**
     * Halt the specified thread
     * @param string $threadIdentifier
     */
    public function halt($threadIdentifier);

    /**
     * Look for a completed thread (if any) and return the identifier
     * @return string|null
     */
    public function getLatestCompleted();

    /**
     * Check if a specified process has completed
     * @param string $threadIdentifier
     * @return bool
     */
    public function hasCompleted($threadIdentifier);

    /**
     * Pull the current message for given thread identifier and clear it out.
     * @param string $threadIdentifier
     * @return mixed
     */
    public function flushResult($threadIdentifier);
}