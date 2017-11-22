<?php

namespace ThreadConductor\Style;

use ThreadConductor\Style as StyleInterface;

/**
 * Intended as a dummy thread style, will execute immediately (be aware, will not trigger timeouts in Thread Conductor)
 */
class Serial implements StyleInterface
{
    /**
     * Results indexed by the thread identifier
     * @var mixed[]
     */
    static protected $results = [];

    /**
     * @var int $threadCount Current Number of threads using this style
     */
    static protected $threadCount = 0;

    /**
     * @param callable $action
     * @param array $arguments
     * @return string
     */
    public function spawn(Callable $action, array $arguments)
    {
        ++self::$threadCount;
        self::$results[self::$threadCount] = call_user_func_array($action, $arguments);
        return (string) self::$threadCount;
    }

    /**
     * Serial threads always execute immediately, so this is a no-op
     * @param string $threadIdentifier
     */
    public function halt($threadIdentifier)
    {
        return;
    }

    /**
     * Reset the thread identifier to initial values previous to any spawns
     * WARNING: Doing this could cause duplicate thread identifiers to be generated!
     */
    public static function resetThreadIdentifier()
    {
        self::$threadCount = 0;
        //we're resetting our sequence, so that invalidates any results we currently have stored as well.
        self::$results = [];
    }

    /**
     * Look for a completed thread (if any) and return the identifier
     * @return string
     */
    public function getLatestCompleted()
    {
        //All threads execute inline, so the last one generated is the last one to complete
        return (string) self::$threadCount;
    }

    /**
     * Serial threads always execute immediately so this is a no-op
     * @param string $threadIdentifier
     * @return bool
     */
    public function hasCompleted($threadIdentifier)
    {
        return true;
    }

    /**
     * Pull the current message for given thread identifier and clear it out.
     * @param string $threadIdentifier
     * @return mixed
     */
    public function flushResult($threadIdentifier)
    {
        $result = self::$results[$threadIdentifier];
        unset(self::$results[$threadIdentifier]);
        return $result;
    }
}