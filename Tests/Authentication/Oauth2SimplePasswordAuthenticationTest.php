<?php

namespace LaFourchette\HydraApiBundle\Tests\Authentication;

use CommerceGuys\Guzzle\Plugin\Oauth2\GrantType\PasswordCredentials;
use CommerceGuys\Guzzle\Plugin\Oauth2\Oauth2Plugin;
use Guzzle\Common\Event;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Request;
use LaFourchette\HydraApiBundle\Authentication\Oauth2SimplePasswordAuthentication;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

class Oauth2SimplePasswordAuthenticationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LoggerInterface|ObjectProphecy
     */
    private $loggerMock;

    /**
     * @var Oauth2Plugin|ObjectProphecy
     */
    private $oauth2pluginMock;

    /**
     * @var PasswordCredentials|ObjectProphecy
     */
    private $passwordCrendentialsMock;

    /**
     * @var oauth2SimplePasswordAuthentication
     */
    private $oauth2SimplePasswordAuthentication;

    public function setUp()
    {
        parent::setUp();

        $this->loggerMock = $this->getLoggerMock();
        $this->oauth2pluginMock = $this->getOAuth2PluginMock();
        $this->passwordCredentialsMock = $this->getPasswordCredentialsMock();
    }

    public function testOnRequestBeforeSend()
    {
        $this->loggerMock->info('oauth2_simple_password authenticate')->shouldBeCalledTimes(1);

        $expires = time() + 999999;
        $eventMock = $this->getEventMock();

        $this->passwordCredentialsMock->getTokenData()->willReturn(array(
            'access_token' => 'access_token_value',
            'refresh_token' => 'refresh_token_value',
            'expires_in' => $expires
        ))->shouldBeCalledTimes(1);

        $this->oauth2pluginMock->setAccessToken(Argument::that(function ($argument) {
            return isset($argument['access_token']) && $argument['access_token'] == 'access_token_value';
        }))->shouldBeCalledTimes(1);

        $this->oauth2pluginMock->onRequestBeforeSend($eventMock->reveal())->shouldBeCalledTimes(1);

        $this->oauth2SimplePasswordAuthentication = new Oauth2SimplePasswordAuthentication(
            $this->loggerMock->reveal(),
            $this->oauth2pluginMock->reveal(),
            $this->passwordCredentialsMock->reveal(),
            array('base_url' => 'http://erb.api/oauth/')
        );

        $this->oauth2SimplePasswordAuthentication->onRequestBeforeSend($eventMock->reveal());

        $this->assertEquals($this->oauth2SimplePasswordAuthentication->getOauthAccessToken(), 'access_token_value');
        $this->assertEquals($this->oauth2SimplePasswordAuthentication->getOauthRefreshToken(), 'refresh_token_value');
        $this->assertEquals($this->oauth2SimplePasswordAuthentication->getOauthExpiresIn(), $expires);
    }

    public function testGetName()
    {
        $this->oauth2SimplePasswordAuthentication = new Oauth2SimplePasswordAuthentication(
            $this->loggerMock->reveal(),
            $this->oauth2pluginMock->reveal(),
            $this->passwordCredentialsMock->reveal(),
            array('base_url' => 'http://erb.api/oauth/')
        );

        $this->assertEquals('oauth2_simple_password', $this->oauth2SimplePasswordAuthentication->getName());
    }

    /**
     * @return ObjectProphecy|Logger
     */
    private function getLoggerMock()
    {
        return $this->prophesize('Symfony\Bridge\Monolog\Logger');
    }

    /**
     * @return ObjectProphecy|Client
     */
    private function getClientMock()
    {
        return $this->prophesize('Guzzle\Http\Client');
    }

    /**
     * @return ObjectProphecy|Oauth2Plugin
     */
    private function getOAuth2PluginMock()
    {
        return $this->prophesize('CommerceGuys\Guzzle\Plugin\Oauth2\Oauth2Plugin');
    }

    /**
     * @return ObjectProphecy|PasswordCredentials
     */
    private function getPasswordCredentialsMock()
    {
        return $this->prophesize('CommerceGuys\Guzzle\Plugin\Oauth2\GrantType\PasswordCredentials');
    }

    /**
     * @return ObjectProphecy|Response
     */
    private function getResponseMock()
    {
        return $this->prophesize('Guzzle\Http\Message\Response');
    }

    /**
     * @return ObjectProphecy|Request
     */
    private function getRequestMock()
    {
        return $this->prophesize('Guzzle\Http\Message\Request');
    }

    /**
     * @return Event|ObjectProphecy
     */
    private function getEventMock()
    {
        return $this->prophesize('Guzzle\Common\Event');
    }
}
