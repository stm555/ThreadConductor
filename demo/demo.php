<?php
/**
 * Example Usage of a Forking Thread Conductor
 */

use ThreadConductor\Conductor;
use ThreadConductor\Style;
use ThreadConductor\Style\Fork as ForkStyle;
use ThreadConductor\Messenger\Apc as ApcMessenger;
use ThreadConductor\Exception\Timeout as TimeoutException;
use Kanel\Benchmark\Benchmark;

//just using Composer's autoloader, so make sure composer has run
require_once "../vendor/autoload.php";

//Some handy constants for use in formatting durations
const INTERVAL_SPEC_PREFIX_PERIOD = 'P';
const INTERVAL_SPEC_PREFIX_TIME = 'T';

$messenger = new ApcMessenger();
$style = new ForkStyle($messenger, 5);
$serialStyle = new ThreadConductor\Style\Serial();

//Do some simple stuff in parallel
$slacker = function ($slackTime) {
    $napTime = rand(1, $slackTime);
    echo "\nSlacking for " . describeDuration($napTime);
    sleep($napTime);
    return $napTime;
};

//Do some shared value comparison .. running in parallel this is almost assured to have race conditions
$slackerJudge = function ($slackTime) use ($messenger) {
    //introducing artificial computation time
    sleep(1);

    $largestSlackTime = intval($messenger->receive('largestSlackTime'));
    echo "\nComparing {$slackTime} to {$largestSlackTime}";
    if ($slackTime > $largestSlackTime) {
        $messenger->send('largestSlackTime', $slackTime);
        return true;
    }
    return false;
};

demonstrationExecution($slacker, $style);

$sleepWindows = [ 'small' => 2, 'medium' => 5, 'large' => 10, 'extra large' => 20];

//run as many of these mappings in parallel as the adapter allows
$benchmark = new Benchmark();
$benchmark->start();
$sleepResults = arrayMap($sleepWindows, $slacker, $style);
$arrayMapTiming = $benchmark->stop();
foreach ($sleepWindows as $description => $size) {
    $message = "\tSleep Window \"{$description}\" ({$size}) : Actual Sleep: ";
    $message .= (isset($sleepResults[$description])) ? $sleepResults[$description] : "[Unknown]";
    echo "\n" . $message;
}
echo "\nTook " . describeDuration($arrayMapTiming['time']) . " to process array map in parallel";

$benchmark->start();
$sleepResults = arrayMap($sleepWindows, $slacker, $serialStyle);
$arrayMapTiming = $benchmark->stop();
foreach ($sleepWindows as $description => $size) {
    $message = "\tSleep Window \"{$description}\" ({$size}) : Actual Sleep: ";
    $message .= (isset($sleepResults[$description])) ? $sleepResults[$description] : "[Unknown]";
    echo "\n" . $message;
}
echo "\nTook " . describeDuration($arrayMapTiming['time']) . " to process array map serially";

//reversed the array to produce more interesting results
//judge as many of these properties in parallel as the adapter allows
$benchmark->start();
$filteredResults = arrayFilter(array_reverse($sleepWindows, true), $slackerJudge, $style);
$filterTiming = $benchmark->stop();
foreach ($sleepWindows as $description => $size) {
    $message = "\tSleep Window \"{$description}\" ({$size}) ";
    $message .= (isset($filteredResults[$description])) ? "Survived" : "Removed";
    echo "\n" . $message;
}
echo "\nTook " . describeDuration($arrayMapTiming['time']) . " to process filter in parallel";

//Serially processed, takes longer but no race conditions
$benchmark->start();
$filteredResults = arrayFilter(array_reverse($sleepWindows, true), $slackerJudge, $serialStyle);
$filterSerialTiming = $benchmark->stop();
foreach ($sleepWindows as $description => $size) {
    $message = "\tSleep Window \"{$description}\" ({$size}) ";
    $message .= (isset($filteredResults[$description])) ? "Survived" : "Removed";
    echo "\n" . $message;
}
echo "\nTook " . describeDuration($arrayMapTiming['time']) . " to process filter serially";


//---- Utility Functions
function describeDuration($seconds)
{
    //S signifies the seconds unit of Time
    $duration = new DateInterval(INTERVAL_SPEC_PREFIX_PERIOD . INTERVAL_SPEC_PREFIX_TIME . (int) $seconds . 'S');
    $hours = $duration->format('%h');
    $minutes = $duration->format('%i');
    if ($hours > 0) {
        return $duration->format('%H:%I:%S');
    }
    if ($minutes > 0) {
        return $duration->format('%i minutes, %s seconds');
    }
    return $duration->format('%s seconds');
}

function arrayMap(array $array, callable $function, Style $style)
{
    $threadConductor = new Conductor($style);
    foreach($array as $key => $value) {
        $threadConductor->addAction($key, $function, [$value]);
    }
    $map = [];
    try {
        foreach($threadConductor as $key => $result) {
            $map[$key] = $result;
        }
    } catch (TimeoutException $timeoutException) {
        echo "\nDid not map all values, exceeded execution time - " . $timeoutException->getMessage();
    }
    return $map;
}

function arrayFilter(array $array, callable $judge, ThreadConductor\Style $style)
{
    $threadConductor = new Conductor($style);
    foreach($array as $key => $value) {
        $threadConductor->addAction($key, $judge, [$value]);
    }
    $filteredArray = [];
    try {
        foreach($threadConductor as $key => $result) {
            if ($result) {
                $filteredArray[$key] = $array[$key];
            }
        }
    } catch (TimeoutException $timeoutException) {
        echo "\nDid not judge all values, exceeded execution time - " . $timeoutException->getMessage();
    }
    return $filteredArray;
}

/**
 * @param callable $function
 * @param Style $style
 */
function demonstrationExecution(Callable $function, Style $style)
{
    $benchmark = new Benchmark();
    $benchmark->start();
    $threadConductor = new Conductor($style);
    $threadConductor->addAction('first', $function, [5]);
    $threadConductor->addAction('second', $function, [5]);
    $threadConductor->addAction('third', $function, [5]);
    $threadConductor->addAction('fourth', $function, [5]);
    $threadConductor->addAction('fifth', $function, [5]);
    $threadConductor->addAction('sixth', $function, [5]);

    $threadingDescription = "Attempting " . $threadConductor->getActionCount() . " threads using "
        . get_class($style) . " for threading";
    if ($style instanceOf ForkStyle) {
        $threadingDescription .= " and " . get_class($style->getMessenger()) . " for communication";
    }
    echo "\n" . $threadingDescription;

    //wait for completion..
    $results = [];
    try {
        //$results = iterator_to_array($threadConductor);
        foreach ($threadConductor as $actionKey => $actionResult) {
            $results[$actionKey] = $actionResult;
        }
    } catch (Exception $exception) {
        echo "\nFailed to finish all threads - " . $exception->getMessage();
    }
    echo "\nSlacked for the following times: ";
    foreach ($results as $actionKey => $actionResult) {
        echo "\n\t$actionKey slacked for " . describeDuration($actionResult);
    }
    $timing = $benchmark->stop();
    echo "\nDemo Execution: " . describeDuration($timing['time']) . " elapsed, yet " . describeDuration(array_sum($results)) . " Slacked." ;
}
