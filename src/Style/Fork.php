<?php

namespace ThreadConductor\Style;

use ThreadConductor\Style as StyleInterface;
use ThreadConductor\Messenger;
use ThreadConductor\Exception\Fail as FailException;
use ThreadConductor\Exception\ConcurrentLimitExceeded as ConcurrentLimitExceededException;

/**
 * @white
 */
class Fork implements StyleInterface
{
    const ACTIVE_PROCESSES_MESSENGER_KEY = 'ACTIVE_PROCESS_COUNT';
    const ACTIVE_PROCESSES_MAXIMUM = 2;
    const PCNTL_ANY_PROCESS_ID = -1;

    /**
     * @var Messenger $messenger
     */
    protected $messenger;

    const STATUS_CHILD_FORK_VALUE = 0;
    const STATUS_CHILD = 'CHILD';
    const STATUS_PARENT = 'PARENT';
    const STATUS_ERROR = 'ERROR';

    public function __construct(Messenger $messenger)
    {
        //@todo validate this messenger will work with forked processes - ie, in memory methodcache will *not* work
        $this->messenger = $messenger;
    }

    /**
     * @param callable $action
     * @param mixed[] $arguments
     * @return string
     * @throws FailException
     * @throws ConcurrentLimitExceededException
     */
    public function spawn(Callable $action, array $arguments)
    {
        $this->incrementActiveProcessCount();
        $processId = $this->fork();
        switch ($this->getForkStatus($processId)) {
            case self::STATUS_PARENT:
                return (string) $processId;
            case self::STATUS_CHILD:
                $pid = $this->getPid();
                $this->messenger->send($pid, call_user_func_array($action, $arguments));
                //finished doing the thing, kill this whole process with extreme prejudice
                $this->halt($pid); //halting oneself means the next throw should never be reached.
                throw new FailException('Failed to Kill Current Process Properly');
            default:
                throw new FailException("Error Attempting to Fork: {$processId}");
        }
    }

    /**
     * @todo put some protection around which pids can be hardExited here
     * @param string $threadIdentifier
     */
    public function halt($threadIdentifier)
    {
        $this->decrementActiveProcessCount();
        $this->hardExit($threadIdentifier);
    }

    /**
     * @return int|null|string
     * @throws FailException
     */
    public function getLatestCompleted()
    {
        $pid = $this->getCompletedChildProcess();
        if ($this->isCompletedWaitResponse($pid)) { //child finished
            return $pid;
        }
        return null;
    }

    /**
     * @param string $threadIdentifier
     * @return bool
     * @throws FailException
     */
    public function hasCompleted($threadIdentifier)
    {
        return $this->isCompletedWaitResponse($this->checkChildProcess($threadIdentifier));
    }

    /**
     * Pull the current message for given thread identifier and clear it out.
     * @param string $threadIdentifier
     * @return mixed
     */
    public function flushResult($threadIdentifier)
    {
        return $this->messenger->flushMessage($threadIdentifier);
    }

    /**
     * @param int $waitResponse
     * @return bool
     * @throws FailException
     */
    protected function isCompletedWaitResponse($waitResponse)
    {
        if ($waitResponse < 0) {
            //some error occurred
            throw new FailException("Error waiting for thread completion.");
        }
        return ($waitResponse > 0); //0 = not complete, anything positive child completed
    }

    /**
     * @param int $processId
     * @return string
     */
    protected function getForkStatus($processId)
    {
        if ($processId === self::STATUS_CHILD_FORK_VALUE) {
            return self::STATUS_CHILD;
        }
        if ($processId > self::STATUS_CHILD_FORK_VALUE) {
            return self::STATUS_PARENT;
        }
        //Anything less than the child fork value is an error
        return self::STATUS_ERROR;
    }

    /**
     * This is intended as a thing wrapper around the pcntl call
     * @codeCoverageIgnore
     *
     * @return int
     */
    protected function fork()
    {
        return pcntl_fork();
    }

    /**
     * This is intended as a thing wrapper around the pcntl call
     * @codeCoverageIgnore
     *
     * @return int
     */
    protected function getPid()
    {
        return posix_getpid();
    }

    /**
     * Interrupt the process and prevent any normal clean up behavior
     * This is intended as a thing wrapper around the pcntl call
     * @codeCoverageIgnore
     *
     *
     * @param int $pid
     */
    protected function hardExit($pid)
    {
        posix_kill($pid, SIGKILL);
    }

    public function getMessenger()
    {
        return $this->messenger;
    }

    /**
     * @throws ConcurrentLimitExceededException
     */
    protected function incrementActiveProcessCount()
    {
        $activeProcessCount = $this->getActiveProcessCount();
        if ($activeProcessCount >= self::ACTIVE_PROCESSES_MAXIMUM) {
            throw new ConcurrentLimitExceededException(
                "Can not start new process, {$activeProcessCount} processes already running. "
                . self::ACTIVE_PROCESSES_MAXIMUM . " processes allowed at a time");
        }
        $this->messenger->send(self::ACTIVE_PROCESSES_MESSENGER_KEY, ++$activeProcessCount);
    }

    protected function decrementActiveProcessCount()
    {
        $activeProcessCount = $this->getActiveProcessCount();
        if ($activeProcessCount > 0) {
            $this->messenger->send(self::ACTIVE_PROCESSES_MESSENGER_KEY, --$activeProcessCount);
        }
    }

    /**
     * @return int
     */
    protected function getActiveProcessCount()
    {
        $activeProcessCount = intval($this->messenger->receive(self::ACTIVE_PROCESSES_MESSENGER_KEY));
        return $activeProcessCount;
    }

    /**
     * Check for a child that has completed, but don't sit around
     * This is intended as a thin wrapper around the pcntl call
     * @codeCoverageIgnore
     *
     * @return int
     */
    protected function getCompletedChildProcess()
    {
        return pcntl_waitpid(self::PCNTL_ANY_PROCESS_ID, $status, WNOHANG);
    }

    /**
     * This is intended as a thing wrapper around the pcntl call
     * @codeCoverageIgnore
     *
     * @param $childProcessId
     * @return int
     */
    protected function checkChildProcess($childProcessId): int
    {
        return pcntl_waitpid($childProcessId, $status, WNOHANG);
    }
}