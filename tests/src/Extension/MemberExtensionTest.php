<?php

namespace Bigfork\SilverStripeOAuth\Client\Test\Extension;

use Bigfork\SilverStripeOAuth\Client\Extension\MemberExtension;
use SapphireTest;

class MemberExtensionTest extends SapphireTest
{
    public function testOnBeforeDelete()
    {
        $mockDataList = $this->getMockBuilder('stdClass')
            ->setMethods(['removeAll'])
            ->getMock();
        $mockDataList->expects($this->once())
            ->method('removeAll');

        $mockMember = $this->getMockBuilder('stdClass')
            ->setMethods(['AccessTokens'])
            ->getMock();
        $mockMember->expects($this->once())
            ->method('AccessTokens')
            ->will($this->returnValue($mockDataList));

        $extension = new MemberExtension;
        $extension->setOwner($mockMember, 'Member');
        $extension->onBeforeDelete();
    }

    public function testClearTokensFromProvider()
    {
        $mockDataList = $this->getMockBuilder('stdClass')
            ->setMethods(['filter', 'count', 'removeAll'])
            ->getMock();
        $mockDataList->expects($this->at(0))
            ->method('filter')
            ->with(['Provider' => 'ProviderName'])
            ->will($this->returnValue($mockDataList));
        $mockDataList->expects($this->at(1))
            ->method('count')
            ->will($this->returnValue(1));
        $mockDataList->expects($this->at(2))
            ->method('removeAll');

        $mockMember = $this->getMockBuilder('stdClass')
            ->setMethods(['AccessTokens'])
            ->getMock();
        $mockMember->expects($this->once())
            ->method('AccessTokens')
            ->will($this->returnValue($mockDataList));

        $extension = new MemberExtension;
        $extension->setOwner($mockMember, 'Member');
        $extension->clearTokensFromProvider('ProviderName');
    }
}
