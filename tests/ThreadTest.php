<?php

namespace ThreadConductor\Tests;

use PHPUnit\Framework\TestCase;
use ThreadConductor\Thread;
use ThreadConductor\Style;
use ThreadConductor\Style\Serial;

class ThreadTest extends TestCase
{
    /**
     * @var Style|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $stubThreadStyle;

    /**
     * @var Callable $stubAction
     */
    protected $stubAction;

    public function setUp()
    {
        $this->stubThreadStyle = $this->getMockBuilder(Serial::class)
        ->setMethods(['spawn'])
        ->getMock();
        $this->stubAction = function() {};
    }

    public function testThreadSpawnsChildWhenInvoked()
    {
        /** @var Serial|\PHPUnit_Framework_MockObject_MockObject $threadStyle */
        $threadStyle = $this->getMockBuilder(Serial::class)
            ->setMethods(['spawn'])
            ->getMock();
        $threadStyle->expects($this->once())->method('spawn')->willReturn(1);

        $thread = new Thread($this->stubAction, $threadStyle);
        $thread();
    }

    public function testThreadPassesArgumentsToThreadStyleSpawnWhenInvoked()
    {
        $argument1 = new \stdClass();
        $argument2 = 5;
        $argument3 = "mouse";
        /** @var Serial|\PHPUnit_Framework_MockObject_MockObject $mockThreadStyle */
        $mockThreadStyle = $this->getMockBuilder(Serial::class)
            ->setMethods(['spawn'])
            ->getMock();
        $mockThreadStyle->expects($this->once())
            ->method('spawn')
            ->with($this->stubAction, [$argument1, $argument2, $argument3]);

        $thread = new Thread($this->stubAction, $mockThreadStyle);
        $thread($argument1, $argument2, $argument3);
    }

    public function testThreadHaltHasStyleAttemptToHaltProcess()
    {
        $mockThreadStyle = $this->getMockBuilder(Serial::class)
            ->setMethods(['spawn','halt'])
            ->getMock();
        $expectedThreadIdentifier = '123456';
        $mockThreadStyle->expects($this->once())
            ->method('spawn')
            ->willReturn($expectedThreadIdentifier);
        $mockThreadStyle->expects($this->once())
            ->method('halt')
            ->with($expectedThreadIdentifier);
        $thread = new Thread(function() {}, $mockThreadStyle);
        $thread();
        $thread->halt();
    }

    public function testHasStartedIsTrueAfterThreadIsExecuted()
    {
        $thread = new Thread($this->stubAction, $this->stubThreadStyle);
        $thread();
        $this->assertTrue($thread->hasStarted());
    }

    public function testHasStartedIsFalseBeforeThreadExecutes()
    {
        $thread = new Thread($this->stubAction, $this->stubThreadStyle);
        $this->assertFalse($thread->hasStarted());
    }

    public function testHasHaltedIsTrueAfterThreadIsHalted()
    {
        $thread = new Thread($this->stubAction, $this->stubThreadStyle);
        $thread();
        $thread->halt();
        $this->assertTrue($thread->hasHalted());
    }

    public function testHasHaltedIsFalseWhenThreadHasNotBeenHalted()
    {
        $thread = new Thread($this->stubAction, $this->stubThreadStyle);
        $this->assertFalse($thread->hasHalted(), 'Thread has not yet been executed (or halted), but is reporting halted');
        $thread();
        $this->assertFalse($thread->hasHalted(), 'Thread has executed but not been halted, but is reporting halted');
    }

    public function testHasCompletedIsTrueAfterThreadIsCompleted()
    {
        $thread = new Thread($this->stubAction, $this->stubThreadStyle);
        $thread();
        $thread->checkCompletion();
        $this->assertTrue($thread->hasCompleted());
    }
}
