<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Factory;

use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;
use SapphireTest;

class ProviderFactoryTest extends SapphireTest
{
    public function testSetAndGetProviders()
    {
        $factory = new ProviderFactory();

        $this->assertEmpty($factory->getProviders());

        $providers = ['name' => 'value'];
        $this->assertSame($factory, $factory->setProviders($providers));
        $this->assertEquals($providers, $factory->getProviders());
    }

    public function testGetProvider()
    {
        $mock = $this->getMockBuilder('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->setMethods(['getProviders'])
            ->getMock();
        $mock->expects($this->once())
            ->method('getProviders')
            ->will($this->returnValue(['ProviderName' => 'Result']));

        $this->assertEquals('Result', $mock->getProvider('ProviderName'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetProviderNotSet()
    {
        $mock = $this->getMockBuilder('Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory')
            ->setMethods(['getProviders'])
            ->getMock();
        $mock->expects($this->once())
            ->method('getProviders')
            ->will($this->returnValue(['ProviderName' => 'Result']));

        $this->assertNull($mock->getProvider('ProviderNameNotSet'));
    }
}
