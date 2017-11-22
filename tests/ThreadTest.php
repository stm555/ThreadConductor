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

    //@todo set the threads in different states and verify the status check methods
//    public function testHasStarted()
//    {
//        $thread = new SoftLayer_Utility_Thread($this->stubAction, $this->stubThreadStyle);
//    }
}
