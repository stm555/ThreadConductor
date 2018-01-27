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
const JUDGE_KEY = 'largestSlackTime';
require_once "../vendor/autoload.php";

//Some handy constants for use in formatting durations
const INTERVAL_SPEC_PREFIX_PERIOD = 'P';
const INTERVAL_SPEC_PREFIX_TIME = 'T';

try { $messenger = new ApcMessenger(); }
catch (Exception $e) {
    exit("Could not use APC Messenger for Demo - " . $e->getMessage());
}
$parallelStyle = new ForkStyle($messenger, 5);
$serialStyle = new ThreadConductor\Style\Serial();

//Do some simple stuff in parallel
$slacker = function ($slackTime) {
    echo "\nSlacking for " . describeDuration($slackTime);
    sleep($slackTime);
    return $slackTime;
};

//Do some shared value comparison .. running in parallel this is almost assured to have race conditions
$slackerJudge = function ($slackTime) use ($messenger) {
    //introducing artificial computation time
    //sleep(1);

    $largestSlackTime = intval($messenger->receive('' . JUDGE_KEY . ''));
    echo "\nComparing {$slackTime} to {$largestSlackTime}";
    if ($slackTime > $largestSlackTime) {
        $messenger->send(JUDGE_KEY, $slackTime);
        return true;
    }
    return false;
};
$sleepWindows = [ 'small' => 2, 'medium' => 4, 'large' => 5, 'extra large' => 10];

Benchmark::start();
$sleepResults = arrayMap($sleepWindows, $slacker, $serialStyle);
$arrayMapTiming = Benchmark::stop();
describeTransformationResult($sleepWindows, $sleepResults);
echo "\nTook " . describeDuration($arrayMapTiming['time']) . " to process array map serially";
//run as many of these mappings in parallel as the adapter allows
Benchmark::start();
$sleepResults = arrayMap($sleepWindows, $slacker, $parallelStyle);
$arrayMapTiming = Benchmark::stop();
describeTransformationResult($sleepWindows, $sleepResults);
echo "\nTook " . describeDuration($arrayMapTiming['time']) . " to process array map in parallel";
echo "\n";

//randomize the array order to make the results more interesting
$shuffledSleepWindows = [];
foreach (array_rand($sleepWindows, count($sleepWindows)) as $key) {
    $shuffledSleepWindows[$key] = $sleepWindows[$key];
}
//Serially processed, takes longer but no race conditions
Benchmark::start();
$filteredResults = arrayFilter($shuffledSleepWindows, $slackerJudge, $serialStyle);
$filterSerialTiming = Benchmark::stop();
echo "\nTook " . describeDuration($arrayMapTiming['time']) . " to process filter serially";
//reset the comparison for another run
$messenger->flushMessage(JUDGE_KEY);
//judge as many of these properties in parallel as the adapter allows, faster but hits race conditions producing non-deterministic results
Benchmark::start();
$filteredResults = arrayFilter($shuffledSleepWindows, $slackerJudge, $parallelStyle);
$filterTiming = Benchmark::stop();
echo "\nTook " . describeDuration($arrayMapTiming['time']) . " to process filter in parallel";


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
            $message = "\tSleep Window \"{$key}\" ({$array[$key]}) ";
            if ($result) {
                $filteredArray[$key] = $array[$key];
                $message .= "Survived";
            } else {
                $message .= "Removed";
            }
            echo "\n" . $message;
        }
    } catch (TimeoutException $timeoutException) {
        echo "\nDid not judge all values, exceeded execution time - " . $timeoutException->getMessage();
    }
    return $filteredArray;
}

/**
 * Compares original array to filtered array and describes differences
 * @param $originalArray
 * @param $filteredArray
 */
function describeTransformationResult($originalArray, $filteredArray)
{
    foreach ($originalArray as $description => $size) {
        $message = "\tSleep Window \"{$description}\" ({$size}) : Actual Sleep: ";
        $message .= (isset($filteredArray[$description])) ? $filteredArray[$description] : "[Unknown]";
        echo "\n" . $message;
    }
}

