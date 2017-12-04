<?php

namespace ThreadConductor\Tests\Messenger;

use PHPUnit\Framework\TestCase;
use ThreadConductor\Messenger\Apc as ApcMessenger;


class ApcTest extends TestCase
{
    /**
     * @var ApcMessenger|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $mockMessenger;

    public function setUp()
    {
        $this->mockMessenger = $this->getMockBuilder(ApcMessenger::class)
            ->setMethods(['store', 'fetch', 'delete', 'validateEnvironment']) //apc extension function wrappers and skip apc check
            ->getMock();
    }

    public function testSendStoresMessageUnderGeneratedKey()
    {
        $uniqueKey = '123Foo';
        $expectedValue = 'bar456';
        $expectedKey = $this->mockMessenger->messengerPrefix . $uniqueKey;
        $this->mockMessenger->expects($this->once())->method('store')->with($expectedKey, $expectedValue);
        $this->mockMessenger->send($uniqueKey, $expectedValue);
    }

    public function testSendStoresWithProvidedTimeToLive()
    {
        $uniqueKey = '123Foo';
        $expectedValue = 'bar456';
        $expectedTimeToLive = 1;
        /** @var ApcMessenger|\PHPUnit_Framework_MockObject_MockObject $mockMessenger */
        $mockMessenger = $this->getMockBuilder(ApcMessenger::class)
            ->setMethods(['store', 'fetch', 'delete', 'validateEnvironment'])
            ->setConstructorArgs(['apcMessengerTest', $expectedTimeToLive])
            ->getMock();
        $expectedKey = $mockMessenger->messengerPrefix . $uniqueKey;

        $mockMessenger->expects($this->once())
            ->method('store')
            ->with($expectedKey, $expectedValue, $expectedTimeToLive);
        $mockMessenger->send($uniqueKey, $expectedValue);
    }

    public function testReceiveFetchesMessageUnderGeneratedKey()
    {
        $uniqueKey = '123Foo';
        $expectedValue = 'bar456';
        $expectedKey = $this->mockMessenger->messengerPrefix . $uniqueKey;
        $this->mockMessenger->expects($this->once())
            ->method('fetch')
            ->with($expectedKey)
            ->willReturn($expectedValue);
        $this->assertEquals($expectedValue, $this->mockMessenger->receive($uniqueKey));
    }

    public function testFlushMessageFetchesMessageAndClearsValueUnderGeneratedKey()
    {
        $uniqueKey = '123Foo';
        $expectedValue = 'bar456';
        $expectedKey = $this->mockMessenger->messengerPrefix . $uniqueKey;
        $this->mockMessenger->expects($this->once())
            ->method('fetch')
            ->with($expectedKey)
            ->willReturn($expectedValue);
        $this->mockMessenger->expects($this->once())
            ->method('delete')
            ->with($expectedKey);
        $this->assertEquals($expectedValue, $this->mockMessenger->flushMessage($uniqueKey));
    }
}
