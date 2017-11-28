<?php

namespace ThreadConductor\Tests\Style;

use PHPUnit\Framework\TestCase;
use ThreadConductor\Style\Fork;
use ThreadConductor\Messenger;
use ThreadConductor\Exception\Fail as FailException;
use ThreadConductor\Exception\ConcurrentLimitExceeded as ConcurrentLimitExceededException;
use ThreadConductor\Thread;

class ForkTest extends TestCase
{

    /**
     * @var Callable $stubAction
     */
    protected $stubAction;

    /**
     * @var Messenger|\PHPUnit_Framework_MockObject_MockObject $stubMessenger
     */
    protected $stubMessenger;

    /**
     * A Fork Style with all the system call / dangerous methods stubbed out to not do anything
     * @var Fork|\PHPUnit_Framework_MockObject_MockObject $neuteredForkStyle
     */
    protected $neuteredForkStyle;

    public function setUp()
    {
        $this->stubMessenger = $this->getMockBuilder(Messenger::class)
            ->setMethods(['send', 'receive', 'flushMessage'])
            ->getMock();
        $this->neuteredForkStyle = $this->getMockBuilder(Fork::class)
            ->setMethods(['fork', 'getPid', 'hardExit', 'getCompletedChildProcess', 'checkChildProcess'])
            ->setConstructorArgs([$this->stubMessenger])
            ->getMock();
        $this->stubAction = function() {};
    }

    public function testSpawnReturnsProcessIdToParentProcess()
    {
        $expectedChildProcessId = 12345;
        $this->neuteredForkStyle->expects($this->once())
            ->method('fork')
            ->willReturn($expectedChildProcessId);
        $this->assertEquals($expectedChildProcessId, $this->neuteredForkStyle->spawn(function(){}, []));
    }

    public function testSpawnExecutesActionSendsResultAndHardExitsProcessForChildProcess()
    {
        $argument1 = 'parameter';
        $argument2 = 2;
        $argument3 = ['something'];
        $expectedResult = 42;
        $expectedProcessId = 12345;

        $mockAction = $this->getMockBuilder(\stdClass::class)->setMethods(['act'])->getMock();
        $mockAction->expects($this->once())
            ->method('act')
            ->with($argument1, $argument2, $argument3)
            ->willReturn($expectedResult);

        //send is called for tracking the process count first, then for storing the result
        $this->stubMessenger->expects($this->at(2))->method('send')->with($expectedProcessId, $expectedResult);

        $this->neuteredForkStyle->expects($this->once())
            ->method('fork')
            ->will($this->returnValue(Fork::STATUS_CHILD_FORK_VALUE));
        $this->neuteredForkStyle->expects($this->once())->method('getPid')->willReturn($expectedProcessId);
        $this->neuteredForkStyle->expects($this->once())
            ->method('hardExit');

        try { $this->neuteredForkStyle->spawn([$mockAction, 'act'], [$argument1, $argument2, $argument3]); }
        catch (FailException $failException) {
            //since we mocked the hard exit, this exception will be reached, but we're expecting that so swallow it. Anything else bubble the exception
            if ($failException->getMessage() != 'Failed to Kill Current Process Properly') {
                throw $failException;
            }
        }
    }

    public function testSpawnThrowsFailureExceptionOnErrorForking()
    {
        $this->expectException(FailException::class);
        $this->neuteredForkStyle->expects($this->once())->method('fork')->willReturn('-1');

        $this->neuteredForkStyle->spawn($this->stubAction, []);
    }

    //This is really hard to test without actually forking, (ensuring process count is decremented for forked process)
    //but here we go
    public function testSpawnTracksSpawnedProcessCountInParent()
    {
        //This is what happens in the parent of the fork
        /** @var Messenger|\PHPUnit_Framework_MockObject_MockObject $parentMessengerMock */
        $parentMessengerMock = $this->getMockBuilder(Messenger::class)
        ->setMethods(['send', 'receive', 'flushMessage'])
        ->getMock();
        /** @var Fork|\PHPUnit_Framework_MockObject_MockObject $parentProcessMock */
        $parentProcessMock = $this->getMockBuilder(Fork::class)
            ->setMethods(['fork', 'getPid', 'hardExit', 'getForkStatus'])
            ->setConstructorArgs([$parentMessengerMock])
            ->getMock();
        $parentProcessMock->expects($this->once())
            ->method('getForkStatus')
            ->willReturn(Fork::STATUS_PARENT);
        //Adds a new process but doesn't remove one because that happens in the child
        $parentMessengerMock->expects($this->once())
            ->method('send')
            ->with(Fork::ACTIVE_PROCESSES_MESSENGER_KEY, 1);

        $parentProcessMock->spawn(function () {}, ['SomeKey']);
    }

    public function testSpawnTracksSpawnedProcessCountInChild()
    {
        //This is what happens in the child of the fork
        /** @var Messenger|\PHPUnit_Framework_MockObject_MockObject $childMessengerMock */
        $childMessengerMock = $this->getMockBuilder(Messenger::class)
            ->setMethods(['send', 'receive', 'flushMessage'])
            ->getMock();
        /** @var Fork|\PHPUnit_Framework_MockObject_MockObject $childProcessMock */
        $childProcessMock = $this->getMockBuilder(Fork::class)
            ->setMethods(['fork', 'getPid', 'hardExit', 'getForkStatus'])
            ->setConstructorArgs([$childMessengerMock])
            ->getMock();
        $childProcessMock->expects($this->once())
            ->method('getForkStatus')
            ->willReturn(Fork::STATUS_CHILD);
        //Adds a new process
        $childMessengerMock->expects($this->at(0))
            ->method('receive')
            ->with(Fork::ACTIVE_PROCESSES_MESSENGER_KEY)
            ->willReturn(0); //return a 0 because we haven't started any processes yet.
        $childMessengerMock->expects($this->at(1)) //should call this first
            ->method('send')
            ->with(Fork::ACTIVE_PROCESSES_MESSENGER_KEY, 1); //first process started
        $childMessengerMock->expects($this->at(2)) //should return result second, which we don't care about
            ->method('send');
        //Removes process after it finishes
        $childMessengerMock->expects($this->at(3))
            ->method('receive')
            ->with(Fork::ACTIVE_PROCESSES_MESSENGER_KEY)
            ->willReturn(1); //return the one that we set earlier
        $childMessengerMock->expects($this->at(4)) //should call this last
            ->method('send')
            ->with(Fork::ACTIVE_PROCESSES_MESSENGER_KEY, 0); //first process finished
        //Child should hard exit
        $childProcessMock->expects($this->once())
            ->method('hardExit');

        try { $childProcessMock->spawn(function () {}, ['SomeKey']); }
        catch (FailException $failException) {
            //since we mocked the hard exit, this exception will be reached, but we're expecting that so swallow it. Anything else bubble the exception
            if ($failException->getMessage() != 'Failed to Kill Current Process Properly') {
                throw $failException;
            }
        }
    }

    public function testSpawnPreventsSpawningExcessiveProcesses()
    {
        $excessiveProcessCount = Fork::ACTIVE_PROCESSES_MAXIMUM + 1;
        $this->stubMessenger
            ->expects($this->once())
            ->method('receive')
            ->with(Fork::ACTIVE_PROCESSES_MESSENGER_KEY)
            ->willReturn($excessiveProcessCount);
        $this->expectException(ConcurrentLimitExceededException::class);
        $this->expectExceptionMessage(
            "Can not start new process, {$excessiveProcessCount} processes already running. "
            . Fork::ACTIVE_PROCESSES_MAXIMUM . " processes allowed at a time"
        );

        $this->neuteredForkStyle->spawn(function() {}, []);
    }

    public function testGetLatestCompletedChecksForACompletedChildProcessAndReturnsIt()
    {
        $expectedChildProcessId = 12345;
        $this->neuteredForkStyle->expects($this->once())
            ->method('getCompletedChildProcess')
            ->willReturn($expectedChildProcessId);
        $this->assertEquals($expectedChildProcessId, $this->neuteredForkStyle->getLatestCompleted());
    }

    public function testGetLatestCompletedThrowsExceptionWhenErrorOccursFindingCompletedChildProcess()
    {
        $this->neuteredForkStyle->expects($this->once())
            ->method('getCompletedChildProcess')
            ->willReturn(-1); //signifies error occurred interrogating system
        $this->expectException(\ThreadConductor\Exception\Fail::class);

        $this->neuteredForkStyle->getLatestCompleted();
        $this->fail('Failure Exception not thrown when error encountered finding completed child process');
    }

    public function testGetLatestCompletedReturnsNullWhenNoChildProcessesHaveCompleted()
    {
        $this->neuteredForkStyle->expects($this->once())
            ->method('getCompletedChildProcess')
            ->willReturn(0); //Returns 0 when no completed child processes are found
        $this->assertNull($this->neuteredForkStyle->getLatestCompleted());
    }

    public function testHasCompletedIsTrueForChildProcessThatHasCompleted()
    {
        $threadIdentifier = 123456;
        $this->neuteredForkStyle->expects($this->once())
            ->method('checkChildProcess')
            ->with($threadIdentifier)
            ->willReturn($threadIdentifier);
        $this->assertTrue($this->neuteredForkStyle->hasCompleted($threadIdentifier));
    }

    public function testHasCompletedIsFalseForChildProcessThatHasNotCompleted()
    {
        $threadIdentifier = 123456;
        $this->neuteredForkStyle->expects($this->once())
            ->method('checkChildProcess')
            ->with($threadIdentifier)
            ->willReturn(0); //will return 0 if the queried process has not finished
        $this->assertFalse($this->neuteredForkStyle->hasCompleted($threadIdentifier));
    }

    public function testHasCompletedThrowsExceptionWhenErrorOccursFindingChildProcess()
    {
        $threadIdentifier = 123456;
        $this->neuteredForkStyle->expects($this->once())
            ->method('checkChildProcess')
            ->with($threadIdentifier)
            ->willReturn(-1); //will return -1 if an error occurs querying the process
        $this->expectException(\ThreadConductor\Exception\Fail::class);
        $this->neuteredForkStyle->hasCompleted($threadIdentifier);
        $this->fail('Failure Exception not thrown when error encountered checking child process');
    }

    public function testFlushResultFlushesMessageFromMessenger()
    {
        $threadIdentifier = 123456;
        $this->stubMessenger->expects($this->once())->method('flushMessage')->with($threadIdentifier);
        $this->neuteredForkStyle->flushResult($threadIdentifier);
    }

    public function testGetMessengerProvidesMessengerUsedForStyle()
    {
        $this->assertEquals($this->stubMessenger, $this->neuteredForkStyle->getMessenger());
    }
}
