<?php

namespace ThreadConductor;

use ThreadConductor\Exception\ConcurrentLimitExceeded as ConcurrentLimitExceededException;
use ThreadConductor\Exception\Fail as FailException;

class Thread
{
    /**
     * Thread has not been started yet
     */
    const STATUS_NOT_STARTED = 'NOT_STARTED';
    /**
     * Thread was started and is currently executing
     */
    const STATUS_EXECUTING =  'EXECUTING';
    /**
     * Thread was started and then subsequently halted before finishing
     */
    const STATUS_HALTED = 'HALTED';
    /**
     * Thread was started and completed normally
     */
    const STATUS_FINISHED = 'FINISHED';

    /**
     * @var string Identifier for this thread
     */
    public $identifier;

    /**
     * Style of thread to create
     * @var Style
     */
    public $style;

    /**
     * Action to execute in thread
     * @var Callable
     */
    protected $action;

    /**
     * Current state of the thread
     * @var string
     */
    protected $status = self::STATUS_NOT_STARTED;

    /**
     * @param callable $action Action to be executed in this thread
     * @param Style $style The style that will run the thread
     */
    public function __construct(Callable $action, Style $style)
    {
        $this->action = $action;
        $this->style = $style;
    }

    /**
     * @return string An identifier for the thread
     * @throws ConcurrentLimitExceededException
     * @throws FailException
     */
    public function __invoke()
    {
        $this->updateStatus(self::STATUS_EXECUTING);
        try {
            $this->identifier = $this->style->spawn($this->action, func_get_args());
        } catch (ConcurrentLimitExceededException $concurrentLimitExceededException) {
            $this->updateStatus(self::STATUS_NOT_STARTED);
            throw $concurrentLimitExceededException;
        }
        return $this->identifier;
    }

    /**
     * Checks with the style adapter to see if the thread has completed yet
     * @return bool
     */
    public function checkCompletion()
    {
        //if we're not already complete locally but our style says we're complete
        if (!$this->hasCompleted() && $this->style->hasCompleted($this->identifier)) {
            $this->updateStatus(self::STATUS_FINISHED); //mark us complete locally
        }
        return $this->hasCompleted();
    }

    /**
     * Change the current status of the thread
     * @param $status
     */
    protected function updateStatus($status) {
        switch($status)
        {
            case self::STATUS_EXECUTING:
            case self::STATUS_NOT_STARTED:
            case self::STATUS_HALTED:
            case self::STATUS_FINISHED:
            $this->status = $status;
                break;
            default:
                //invalid status, ignore
        }
    }

    /**
     * Has the thread started yet?
     * @return bool
     */
    public function hasStarted()
    {
        return ($this->status !== self::STATUS_NOT_STARTED);
    }

    /**
     * Has the thread completed yet?
     * @return bool
     */
    public function hasCompleted()
    {
        return ($this->status === self::STATUS_FINISHED || $this->status === self::STATUS_HALTED);
    }

    /**
     * Has the thread been halted?
     * @return bool
     */
    public function hasHalted()
    {
        return ($this->status === self::STATUS_HALTED);
    }

    /**
     * Ask the style adapter to get the result for the thread and provide it
     * @return mixed
     */
    public function getResult()
    {
        return $this->style->flushResult($this->identifier);
    }

    /**
     * Halt the thread
     */
    public function halt()
    {
        $this->style->halt($this->identifier);
        $this->updateStatus(self::STATUS_HALTED);
    }
}