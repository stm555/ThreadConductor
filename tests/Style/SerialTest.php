<?php

namespace ThreadConductor\Tests\Style;

use PHPUnit\Framework\TestCase;
use ThreadConductor\Style\Serial;

class SerialTest extends TestCase
{
    /**
     * @var Callable $stubAction
     */
    protected $stubAction;

    public function setUp()
    {
        //make sure our thread identifier isn't poisoned
        Serial::resetThreadIdentifier();
        $this->stubAction = function() {};
    }

    public function tearDown()
    {
        //remove any poison we may have introduced to our thread identifier
        Serial::resetThreadIdentifier();
    }

    public function testResetThreadIdentifierStartsSequentialIdentifiersOver()
    {
        $expectedFirstThreadIdentifier = 1;
        //spawn a few times and verify we're not getting the first thread identifier
        $serialThreadStyle = new Serial();
        $serialThreadStyle->spawn($this->stubAction, []); //1
        $serialThreadStyle->spawn($this->stubAction, []); //2
        $this->assertNotEquals(
            $expectedFirstThreadIdentifier,
            $serialThreadStyle->spawn($this->stubAction, [])
        ); //3

        //then reset the identifier and verify we're back at the first thread identifier again
        Serial::resetThreadIdentifier(); //0
        $this->assertEquals(
            $expectedFirstThreadIdentifier,
            $serialThreadStyle->spawn($this->stubAction, [])
        ); //1
    }

    public function testSpawnIndexesThreadsSequentially()
    {
        $serialThreadStyle = new Serial();
        //each spawn identifies as 1-10 on a clean run
        $this->assertEquals(1, $serialThreadStyle->spawn($this->stubAction, []));
        $this->assertEquals(2, $serialThreadStyle->spawn($this->stubAction, []));
        $this->assertEquals(3, $serialThreadStyle->spawn($this->stubAction, []));
        $this->assertEquals(4, $serialThreadStyle->spawn($this->stubAction, []));
        $this->assertEquals(5, $serialThreadStyle->spawn($this->stubAction, []));
        $this->assertEquals(6, $serialThreadStyle->spawn($this->stubAction, []));
        $this->assertEquals(7, $serialThreadStyle->spawn($this->stubAction, []));
        $this->assertEquals(8, $serialThreadStyle->spawn($this->stubAction, []));
        $this->assertEquals(9, $serialThreadStyle->spawn($this->stubAction, []));
        $this->assertEquals(10, $serialThreadStyle->spawn($this->stubAction, []));
    }

    public function testSpawnExecutesActionAndStoresResult()
    {
        /**
         * serial sequentially generates these
         * @see testSpawnIndexesThreadsSequentially()
         */
        $expectedIdentifier = 1;
        $expectedResult = 42;
        $mockAction = $this->getMockBuilder(\stdClass::class)->setMethods(['act'])->getMock();
        $mockAction->expects($this->once())->method('act')->willReturn($expectedResult);

        $serialThreadStyle = new Serial();
        $threadId = $serialThreadStyle->spawn([$mockAction, 'act'], []);

        $this->assertEquals($expectedResult, $serialThreadStyle->flushResult($threadId));
    }

    public function testGetLatestCompletedReturnsLastSpawnedThread()
    {
        $serialThreadStyle = new Serial();
        //Spawn a thread
        $threadId = $serialThreadStyle->spawn(function(){}, []);
        //Latest completed is the thread we just spawned
        $this->assertEquals($threadId, $serialThreadStyle->getLatestCompleted());
        //Spawn another thread
        $threadId2 = $serialThreadStyle->spawn(function(){}, []);
        //Latest completed is the new thread we just spawned
        $this->assertEquals($threadId2, $serialThreadStyle->getLatestCompleted());
    }
}
