<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Retrofit\HttpClient\Adapter\Guzzle;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tebru;
use Tebru\Retrofit\Adapter\HttpClientAdapter;
use Tebru\Retrofit\Http\Callback;

/**
 * Class GuzzleV6ClientAdapter
 *
 * Wrapper around version 6 of guzzlehttp/guzzle
 *
 * @author Nate Brunette <n@tebru.net>
 */
class GuzzleV6ClientAdapter implements HttpClientAdapter
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
     * @param string $method
     * @param string $uri
     * @param array $headers
     * @param string $body
     * @return Response
     */
    public function send($method, $uri, array $headers = [], $body = null)
    {
        return $this->client->send(new Request($method, $uri, $headers, $body));
    }

    /**
     * Send asynchronous guzzle request
     *
     * @param Request $request
     * @param \Tebru\Retrofit\Http\Callback $callback
     * @return null
     */
    public function sendAsync(Request $request, Callback $callback)
    {
        $this->promises[] = $this->client
            ->sendAsync($request)
            ->then($callback->success(), $callback->failure())
        ;
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
}
