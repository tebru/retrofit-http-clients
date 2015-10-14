<?php
/*
 * Copyright (c) 2015 Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Retrofit\HttpClient\Adapter\Guzzle;

use Exception;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tebru;
use Tebru\Retrofit\Adapter\HttpClientAdapter;
use Tebru\Retrofit\Exception\RequestException;
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
     * @param RequestInterface $request
     * @param \Tebru\Retrofit\Http\Callback $callback
     * @return null
     */
    public function sendAsync(RequestInterface $request, Callback $callback)
    {
        $this->promises[] = $this->client
            ->sendAsync($request)
            ->then(
                function (ResponseInterface $response) use ($callback) {
                    $callback->onResponse($response);
                },
                function (Exception $exception) use ($callback) {
                    /** @var \GuzzleHttp\Exception\RequestException $exception */
                    $requestException = new RequestException(
                        $exception->getMessage(),
                        $exception->getCode(),
                        $exception->getPrevious(),
                        $exception->getRequest(),
                        $exception->getResponse(),
                        $exception->getHandlerContext()
                    );

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
}
