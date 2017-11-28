<?php

namespace ThreadConductor;

use ThreadConductor\Exception\ConcurrentLimitExceeded as ConcurrentLimitExceededException;
use ThreadConductor\Exception\Timeout as TimeoutException;

class Conductor implements \Iterator
{
    // All time is is microseconds
    const SLEEP_TIME = 50000; // .05s
    const DEFAULT_MAXIMUM_TOTAL_WAIT_TIME = 10000000; // 10s

    /**
     * @var Style
     */
    protected $style;

    /**
     * Maximum time to spend waiting, in microseconds
     * @var int
     */
    protected $waitTimeLimit;

    /**
     * @var Thread[]
     */
    protected $threads = [];

    /**
     * Map of a list of arguments to pass to the corresponding thread
     * @var array[]
     */
    protected $threadArguments = [];

    /**
     * @var Thread[]
     */
    protected $activeThreads = [];

    /**
     * @var int Number of times we've waited
     */
    protected $waits = 0;

    /**
     * @var mixed[] Results from threads that have completed
     */
    protected $results = [];

    /**
     * SoftLayer_Utility_Thread_Conductor constructor.
     * @param Style $style
     */
    public function __construct(Style $style, $waitTimeLimit = null)
    {
        $this->style = $style;
        $this->waitTimeLimit = (isset($waitTimeLimit)) ? $waitTimeLimit : self::DEFAULT_MAXIMUM_TOTAL_WAIT_TIME;
    }


    public function addAction($key, Callable $action, $arguments = [])
    {
        $this->threads[$key] = new Thread($action, $this->style);
        $this->threadArguments[$key] = $arguments;
    }

    /**
     * Activate as many threads as our style supports
     */
    public function start()
    {
        foreach($this->threads as $threadKey => $thread) {
            //@todo do something more elegant than continue here
            if ($thread->hasStarted()) {
                continue;
            }
            try {
                call_user_func_array($thread, $this->threadArguments[$threadKey]);
                $this->activeThreads[$threadKey] = $thread;
            } catch (ConcurrentLimitExceededException $concurrentLimitExceededException) {
                //We've started as many threads as we can, stop adding for now
                return;
            }
        }
    }

    public function stop()
    {
        $this->cleanUp();
    }

    /**
     * @throws TimeoutException
     */
    protected function populateResult()
    {
        try {
            list($threadKey, $latestCompletedThread) = $this->findCompletedThread();
            while (!isset($latestCompletedThread)) {
                $this->wait();
                list($threadKey, $latestCompletedThread) = $this->findCompletedThread();
            }
            $this->results[$threadKey] = $latestCompletedThread->getResult();
            unset($this->activeThreads[$threadKey]);
        } catch (TimeoutException $timeoutException) {
            $this->cleanUp();
            throw $timeoutException;
        } catch (\Exception $exception) {
            //ran out of active threads or something bad happened
            $this->cleanUp();
        }
    }

    /**
     * Returns a pair of the thread key and the thread object when found, or null if no threads have completed
     * @return null|array
     * @throws \Exception
     */
    protected function findCompletedThread()
    {
        $this->start(); //if our thread pool is reached, this will no-op
        if (empty($this->activeThreads)) { //if the threads are still empty after starting, we've processed everything
            //@todo a better control structure should be used here. This is kind of an awkward way of having three outcomes from the function
            throw new \Exception("All Threads Have Been Processed");
        }
        foreach($this->activeThreads as $threadKey => $thread) {
            if ($thread->checkCompletion()) {
                return [$threadKey, $thread];
            }
        }
        return null;
    }

    public function getActionCount()
    {
        return count($this->threads);
    }

    /**
     * @throws TimeoutException Will throw exception if Conductor has already exceeded max wait time
     */
    protected function wait()
    {
        $totalWaitTime = $this->getTotalWaitTime(); // in micro seconds
        if ($totalWaitTime >= $this->waitTimeLimit) {
            throw new TimeoutException('Exceeded Conductor Total Wait Time For Threads');
        }
        usleep(self::SLEEP_TIME);
        $this->waits++;
    }

    protected function getTotalWaitTime()
    {
        return self::SLEEP_TIME * $this->waits;
    }

    protected function cleanUp()
    {
        foreach($this->activeThreads as $activeThread) {
            $activeThread->halt();
        }
        $this->activeThreads = [];
        $this->results = [];
    }

    //Iterator interface methods
    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current()
    {
        return end($this->results);
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     * @throws TimeoutException
     */
    public function next()
    {
        $this->populateResult();
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key()
    {
        $resultKeys = array_keys($this->results);
        return end($resultKeys);
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid()
    {
        return !empty($this->results);
    }

    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     * @throws TimeoutException
     */
    public function rewind()
    {
        $this->cleanUp();
        $this->populateResult();
    }
}