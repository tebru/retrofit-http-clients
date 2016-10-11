<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Retrofit\HttpClient\Adapter\Guzzle;

use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\Request;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\Ring\Future\FutureInterface;
use GuzzleHttp\Stream\Stream;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tebru;
use Tebru\Retrofit\Adapter\HttpClientAdapter;
use Tebru\Retrofit\Exception\RequestException;
use Tebru\Retrofit\Event\AfterSendEvent;
use Tebru\Retrofit\Event\ApiExceptionEvent;
use Tebru\Retrofit\Event\EventDispatcherAware;
use Tebru\Retrofit\Http\Callback;

/**
 * Class GuzzleV5ClientAdapter
 *
 * Wrapper around version 5 of guzzlehttp/guzzle
 *
 * @author Nate Brunette <n@tebru.net>
 */
class GuzzleV5ClientAdapter implements HttpClientAdapter, EventDispatcherAware
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var FutureInterface[]
     */
    private $responses = [];

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * Constructor
     *
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $version = (int)ClientInterface::VERSION;
        Tebru\assertSame(5, $version, 'Guzzle client must be at version 5, version %d found', $version);

        $this->client = $client;
    }

    /**
     * Make a request
     *
     * @param RequestInterface $request
     * @return Psr7Response
     */
    public function send(RequestInterface $request)
    {
        $request = $this->createRequest($request);
        $response = $this->client->send($request);

        return new Psr7Response(
            $response->getStatusCode(),
            $response->getHeaders(),
            $response->getBody(),
            $response->getProtocolVersion(),
            $response->getReasonPhrase()
        );
    }

    /**
     * Send asynchronous guzzle request
     *
     * @param RequestInterface $psrRequest
     * @param \Tebru\Retrofit\Http\Callback $callback
     * @return null
     */
    public function sendAsync(RequestInterface $psrRequest, Callback $callback)
    {
        $request = $this->createRequest($psrRequest, true);

        /** @var FutureInterface $response */
        $response = $this->client->send($request);

        $response
            ->then(
                function (ResponseInterface $response) {
                    return new Psr7Response(
                        $response->getStatusCode(),
                        $response->getHeaders(),
                        $response->getBody(),
                        $response->getProtocolVersion(),
                        $response->getReasonPhrase()
                    );
                },
                function (Exception $exception) use ($callback, $psrRequest) {
                    $request = $psrRequest;
                    $response = null;
                    if ($exception instanceof \GuzzleHttp\Exception\RequestException) {
                        $request = $exception->getRequest();
                        $response = $exception->getResponse();
                    }

                    $requestException = new RequestException(
                        $exception->getMessage(),
                        $exception->getCode(),
                        $exception->getPrevious(),
                        $request,
                        $response
                    );

                    if (null !== $this->eventDispatcher) {
                        $this->eventDispatcher->dispatch(ApiExceptionEvent::NAME, new ApiExceptionEvent($requestException, $request));
                    }

                    $callback->onFailure($requestException);
                }
            )
            ->then(
                function (Psr7Response $response) use ($callback, $psrRequest) {
                    if (null !== $this->eventDispatcher) {
                        $this->eventDispatcher->dispatch(AfterSendEvent::NAME, new AfterSendEvent($psrRequest, $response));
                    }

                    $callback->onResponse($response);
                }
            );

        $this->responses[] = $response;
    }

    /**
     * Resolve all promises
     *
     * @return null
     */
    public function wait()
    {
        foreach ($this->responses as $response) {
            $response->wait();
        }
    }

    /**
     * Set the event dispatcher
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Create a Guzzle5 request object
     *
     * @param RequestInterface $request
     * @param bool $async
     * @return Request
     */
    private function createRequest(RequestInterface $request, $async = false)
    {
        $body = Stream::factory($request->getBody());
        $uri = (string)$request->getUri();
        $headers = $request->getHeaders();

        // fixes issue with host getting applied twice
        if (isset($headers['Host'])) {
            unset($headers['Host']);
        }

        $options = ($async) ? ['future' => true] : [];

        $request = $this->client->createRequest($request->getMethod(), $uri, $options);
        $request->setBody($body);
        $request->setHeaders(array_merge($request->getHeaders(), $headers));

        return $request;
    }
}
