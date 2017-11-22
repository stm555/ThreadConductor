<?php

namespace ThreadConductor;

use ThreadConductor\Exception\ConcurrentLimitExceeded as ConcurrentLimitExceededException;
use ThreadConductor\Exception\Fail as FailException;

class Thread
{
    const STATUS_NOT_STARTED = 'NOT_STARTED';
    const STATUS_EXECUTING =  'EXECUTING';
    const STATUS_HALTED = 'HALTED';
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

    protected $status = self::STATUS_NOT_STARTED;

    /**
     * @param callable $action
     * @param Style $style
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

    public function checkCompletion()
    {
        //if we're not already complete locally but our style says we're complete
        if (!$this->hasCompleted() && $this->style->hasCompleted($this->identifier)) {
            $this->updateStatus(self::STATUS_FINISHED); //mark us complete locally
        }
        return $this->hasCompleted();
    }

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

    public function hasStarted()
    {
        return ($this->status !== self::STATUS_NOT_STARTED);
    }

    public function hasCompleted()
    {
        return ($this->status === self::STATUS_FINISHED || $this->status === self::STATUS_HALTED);
    }

    public function hasHalted()
    {
        return ($this->status === self::STATUS_HALTED);
    }

    public function getResult()
    {
        return $this->style->flushResult($this->identifier);
    }

    public function halt()
    {
        $this->style->halt($this->identifier);
        $this->updateStatus(self::STATUS_HALTED);
    }
}