<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Retrofit\HttpClient\Adapter\Guzzle;

use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Tebru;
use Tebru\Retrofit\Adapter\HttpClientAdapter;
use Tebru\Retrofit\Exception\RequestException;
use Tebru\Retrofit\Event\AfterSendEvent;
use Tebru\Retrofit\Event\ApiExceptionEvent;
use Tebru\Retrofit\Event\EventDispatcherAware;
use Tebru\Retrofit\Http\Callback;

/**
 * Class GuzzleV6ClientAdapter
 *
 * Wrapper around version 6 of guzzlehttp/guzzle
 *
 * @author Nate Brunette <n@tebru.net>
 */
class GuzzleV6ClientAdapter implements HttpClientAdapter, EventDispatcherAware
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var PromiseInterface[]
     */
    private $promises = [];

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
        Tebru\assertSame(6, $version, 'Guzzle client must be at version 6, version %d found', $version);

        $this->client = $client;
    }

    /**
     * Make a request
     *
     * @param RequestInterface $request
     * @return Response
     */
    public function send(RequestInterface $request)
    {
        return $this->client->send($request);
    }

    /**
     * Send asynchronous guzzle request
     *
     * @param RequestInterface $request
     * @param \Tebru\Retrofit\Http\Callback $callback
     * @return null
     */
    public function sendAsync(RequestInterface $request, Callback $callback)
    {
        $this->promises[] = $this->client
            ->sendAsync($request)
            ->then(
                function (ResponseInterface $response) use ($callback, $request) {
                    if (null !== $this->eventDispatcher) {
                        $this->eventDispatcher->dispatch(AfterSendEvent::NAME, new AfterSendEvent($request, $response));
                    }

                    $callback->onResponse($response);
                },
                function (Exception $exception) use ($callback, $request) {
                    /** @var \GuzzleHttp\Exception\RequestException $exception */
                    $requestException = new RequestException(
                        $exception->getMessage(),
                        $exception->getCode(),
                        $exception->getPrevious(),
                        $exception->getRequest(),
                        $exception->getResponse(),
                        $exception->getHandlerContext()
                    );

                    if (null !== $this->eventDispatcher) {
                        $this->eventDispatcher->dispatch(ApiExceptionEvent::NAME, new ApiExceptionEvent($requestException, $request));
                    }

                    $callback->onFailure($requestException);
                }
            );
    }

    /**
     * Resolve all promises
     *
     * @return null
     */
    public function wait()
    {
        foreach ($this->promises as $promise) {
            $promise->wait();
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
}
