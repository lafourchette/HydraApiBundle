<?php

namespace LaFourchette\HydraApiBundle\Tests\Client;

use Guzzle\Common\Event;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use LaFourchette\HydraApiBundle\Authentication\AuthenticationInterface;
use LaFourchette\HydraApiBundle\Client\ApiClient;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

class ApiClientTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AuthenticationInterface|ObjectProphecy
     */
    private $authenticationMock;

    /**
     * @var LoggerInterface|ObjectProphecy
     */
    private $loggerMock;

    /**
     * @var ApiClient
     */
    private $client;

    public function setUp()
    {
        parent::setUp();

        $this->authenticationMock = $this->getAuthenticationMock();
        $this->loggerMock = $this->getLoggerMock();

        $this->client = new ApiClient(
            new EventDispatcher(),
            $this->loggerMock->reveal(),
            array(
                'base_url' => 'http://erb.api/',
                'name' => 'erb_api',
            )
        );
    }

    public function testLogRequest()
    {
        $this->authenticationMock
            ->getName()
            ->willReturn('auth_name')
            ->shouldBeCalledTimes(1);

        $this->loggerMock
            ->info('[erb_api] request call', array(
                'method' => 'GET',
                'url' => 'http://erb.api/profile',
                'authentication' => 'auth_name',
            ))
            ->shouldBeCalledTimes(1);

        $requestMock = $this->getRequestMock();
        $requestMock
            ->getMethod()
            ->willReturn('GET')
            ->shouldBeCalledTimes(1);

        $requestMock->getUrl()->willReturn('http://erb.api/profile')->shouldBeCalledTimes(1);

        $eventMock = $this->getEventMock();

        $eventMock
            ->offsetGet('request')
            ->willReturn($requestMock)
            ->shouldBeCalledTimes(1);

        $eventMock
            ->isPropagationStopped()
            ->shouldBeCalled();

        $eventMock
            ->setDispatcher($this->client->getEventDispatcher())
            ->shouldBeCalled();

        $eventMock
            ->setName('client.create_request')
            ->shouldBeCalled();

        $this->client->setAuthentication($this->authenticationMock->reveal());
        $this->client->getEventDispatcher()->dispatch('client.create_request', $eventMock->reveal());
    }

    public function testOnRequestBeforeSend()
    {
        $eventMock = $this->getEventMock();

        $this->authenticationMock->onRequestBeforeSend($eventMock)->shouldBeCalledTimes(1);

        $this->client->setAuthentication($this->authenticationMock->reveal());
        $this->client->onRequestBeforeSend($eventMock->reveal());
    }

    public function testOnRequestBeforeSendWithoutAuthentication()
    {
        $eventMock = $this->getEventMock();
        $this->client->onRequestBeforeSend($eventMock->reveal());
    }

    public function testOnRequestError()
    {
        $eventMock = $this->getEventMock();
        $requestMock = $this->getRequestMock();
        $responseMock = $this->getResponseMock();

        $requestMock
            ->getMethod()
            ->willReturn('method_value')
            ->shouldBeCalledTimes(1);

        $requestMock
            ->getUrl()
            ->willReturn('url_value')
            ->shouldBeCalledTimes(1);

        $responseMock
            ->getBody(true)
            ->willReturn('response_body')
            ->shouldBeCalledTimes(1);

        $eventMock
            ->offsetGet('request')
            ->willReturn($requestMock)
            ->shouldBeCalledTimes(1);

        $eventMock
            ->offsetGet('response')
            ->willReturn($responseMock)
            ->shouldBeCalledTimes(1);

        $this
            ->authenticationMock
            ->getName()
            ->willReturn('auth_name')
            ->shouldBeCalledTimes(1);

        $this->authenticationMock->onRequestError($eventMock)->shouldBeCalledTimes(1);

        $this
            ->loggerMock
            ->err(
                '[erb_api] request error',
                array(
                    'method' => 'method_value',
                    'url' => 'url_value',
                    'authentification' => 'auth_name',
                    'response' => 'response_body',
                )
            );

        $this->client->setAuthentication($this->authenticationMock->reveal());
        $this->client->onRequestError($eventMock->reveal());
    }

    public function testOnRequestErrorWithoutAuthentication()
    {
        $eventMock = $this->getEventMock();
        $requestMock = $this->getRequestMock();
        $responseMock = $this->getResponseMock();

        $requestMock
            ->getMethod()
            ->willReturn('method_value')
            ->shouldBeCalledTimes(1);

        $requestMock
            ->getUrl()
            ->willReturn('url_value')
            ->shouldBeCalledTimes(1);

        $responseMock
            ->getBody(true)
            ->willReturn('response_body')
            ->shouldBeCalledTimes(1);

        $eventMock
            ->offsetGet('request')
            ->willReturn($requestMock)
            ->shouldBeCalledTimes(1);

        $eventMock
            ->offsetGet('response')
            ->willReturn($responseMock)
            ->shouldBeCalledTimes(1);

        $this
            ->loggerMock
            ->err(
                '[erb_api] request error',
                array(
                    'method' => 'method_value',
                    'url' => 'url_value',
                    'authentification' => null,
                    'response' => 'response_body',
                )
            );

        $this->client->onRequestError($eventMock->reveal());
    }

    /**
     * @return ObjectProphecy|Logger
     */
    private function getLoggerMock()
    {
        return $this->prophesize('Symfony\Bridge\Monolog\Logger');
    }

    /**
     * @return ObjectProphecy|Request
     */
    private function getRequestMock()
    {
        return $this->prophesize('Guzzle\Http\Message\Request');
    }

    /**
     * @return ObjectProphecy|Response
     */
    private function getResponseMock()
    {
        return $this->prophesize('Guzzle\Http\Message\Response');
    }

    /**
     * @return ObjectProphecy|AuthenticationInterface
     */
    private function getAuthenticationMock()
    {
        return $this->prophesize('LaFourchette\HydraApiBundle\Authentication\AuthenticationInterface');
    }

    /**
     * @return Event|ObjectProphecy
     */
    private function getEventMock()
    {
        return $this->prophesize('Guzzle\Common\Event');
    }
}
