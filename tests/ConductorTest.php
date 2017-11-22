<?php

namespace ThreadConductor\Tests;

use PHPUnit\Framework\TestCase;
use ThreadConductor\Conductor;
use ThreadConductor\Style;
use ThreadConductor\Style\Serial;
use ThreadConductor\Style\Fork;
use ThreadConductor\Exception\Timeout as TimeoutException;
use ThreadConductor\Exception\ConcurrentLimitExceeded as ConcurrentLimitExceededException;

class ConductorTest extends TestCase
{
    /** @var  Style|\PHPUnit_Framework_MockObject_MockObject */
    protected $mockStyle;
    /** @var string */
    protected $mockActionMethodName;
    /** @var  \stdClass|\PHPUnit_Framework_MockObject_MockObject */
    protected $mockAction;

    public function setUp()
    {
        $this->mockStyle = $this->getMockBuilder(Serial::class)
            ->setMethods(['spawn', 'halt', 'getLatestCompleted', 'hasCompleted'])
            ->getMock();
        $this->mockActionMethodName = '__invoke';
        $this->mockAction = $this->getMockBuilder(\stdClass::class)
            ->setMethods([$this->mockActionMethodName])
            ->getMock();
    }

    public function tearDown()
    {
        Serial::resetThreadIdentifier();
    }

    public function testConductorGathersResultsOnIteration()
    {
        //mock up our action
        $expectedResult = 'foobar';
        $this->mockAction->expects($this->exactly(2))->method($this->mockActionMethodName)->willReturn($expectedResult);

        $conductor = new Conductor(new Serial());
        $conductor->addAction(1, [$this->mockAction, $this->mockActionMethodName]);
        $conductor->addAction(2,[$this->mockAction, $this->mockActionMethodName]);
        foreach($conductor as $key => $result) {
            //verify the results are what our action returned
            $this->assertEquals($expectedResult, $result);
        }
        $this->assertEquals(2, $key); //should have two threads, two results, final key should be the second action we requested
    }

    public function testConductorThrowsErrorOnExecutionTimeout()
    {
        $this->expectException(TimeoutException::class);
        /** @var Conductor|\PHPUnit_Framework_MockObject_MockObject $mockConductor */
        $mockConductor = $this->getMockBuilder(Conductor::class)
            ->setMethods(['getTotalWaitTime', 'findCompletedThread'])
            ->setConstructorArgs([$this->mockStyle])
            ->getMock();
        //force no completed thread detection
        $mockConductor->expects($this->once())->method('findCompletedThread')->willReturn(null);
        //force total wait time more than the default max wait time
        $mockConductor->expects($this->once())->method('getTotalWaitTime')->willReturn(Conductor::DEFAULT_MAXIMUM_TOTAL_WAIT_TIME + 10);

        $mockConductor->addAction('foo', [$this->mockAction, $this->mockActionMethodName]);
        foreach($mockConductor as $result) {
            $this->fail('Conductor should have timed out before any results were generated');
        }
    }

    public function testConductorReturnsKeyedResults()
    {
        $expectedResults = []; //indexed by action insertion order to correspond to serial style thread identifier generation
        $expectedResults['foo'] = 1;
        $expectedResults['bar'] = 2;
        $expectedResults['baz'] = 3;
        $this->mockAction->expects($this->exactly(count($expectedResults)))
            ->method($this->mockActionMethodName)
            ->willReturnOnConsecutiveCalls($expectedResults['foo'], $expectedResults['bar'], $expectedResults['baz']);

        $conductor = new Conductor(new Serial());
        foreach($expectedResults as $expectedResultKey => $expectedResultValue) {
            $conductor->addAction($expectedResultKey, [$this->mockAction, $this->mockActionMethodName], []);
        }

        $results = iterator_to_array($conductor, true);
        $this->assertEquals($expectedResults, $results);
    }

    public function testConductorStartsOnlyNumberOfThreadsAvailableToStyle()
    {
        /** @var Fork|\PHPUnit_Framework_MockObject_MockObject $mockStyle */
        $mockStyle = $this->getMockBuilder(Fork::class)
            ->setMethods(['spawn', 'fork', 'hardExit', 'hasCompleted', 'getActiveProcessCount', 'flushResult'])
            ->disableOriginalConstructor()
            ->getMock();
        //first spawn goes through fine and should complete fine
        $firstThreadIdentifier = '12345';
        $mockStyle->expects($this->at(0))->method('spawn')->willReturn($firstThreadIdentifier);
        //second spawn hits limit
        $mockStyle->expects($this->at(1))->method('spawn')->willThrowException(new ConcurrentLimitExceededException());
        //Complete the first thread and record result
        $mockStyle->expects($this->at(2))->method('hasCompleted')->with($firstThreadIdentifier)->willReturn(true);
        $mockStyle->expects($this->at(3))->method('flushResult')->with($firstThreadIdentifier)->willReturn('some_result');
        //third spawn (for the previous thread) goes through
        $secondThreadIdentifier = '123456';
        $mockStyle->expects($this->at(4))->method('spawn')->willReturn($secondThreadIdentifier);
        //Complete the second thread and record result
        $mockStyle->expects($this->at(5))->method('hasCompleted')->with($secondThreadIdentifier)->willReturn(true);
        $mockStyle->expects($this->at(6))->method('flushResult')->with($secondThreadIdentifier)->willReturn('some_other_result');

        $conductor = new Conductor($mockStyle);
        $conductor->addAction('first', function(){}, []);
        $conductor->addAction('second',function(){}, []);

        //The story here is that the conductor attempts to start the threads, hits the limit, but retries the threads
        //until they are, eventually, executed
        $results = iterator_to_array($conductor);
        $this->assertArrayHasKey('first', $results);
        $this->assertArrayHasKey('second', $results);
    }
}
