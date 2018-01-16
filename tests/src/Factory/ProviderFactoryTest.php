<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Factory;

use Bigfork\SilverStripeOAuth\Client\Factory\ProviderFactory;
use Bigfork\SilverStripeOAuth\Client\Test\TestCase;

class ProviderFactoryTest extends TestCase
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
        $mock = $this->getMockBuilder(ProviderFactory::class)
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
        $mock = $this->getMockBuilder(ProviderFactory::class)
            ->setMethods(['getProviders'])
            ->getMock();
        $mock->expects($this->once())
            ->method('getProviders')
            ->will($this->returnValue(['ProviderName' => 'Result']));

        $this->assertNull($mock->getProvider('ProviderNameNotSet'));
    }
}
