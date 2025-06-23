<?php

declare(strict_types=1);

namespace Peso\Services\Tests\Helpers;

use GuzzleHttp\Psr7\Response;
use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;
use Psr\Http\Message\RequestInterface;

final readonly class MockClient
{
    public static function get(): Client
    {
        $client = new Client();

        $client->on(
            // phpcs:disable Generic.Files.LineLength.TooLong
            new RequestMatcher('/en/financial-markets/foreign-exchange-market/central-bank-exchange-rate-fixing/central-bank-exchange-rate-fixing/daily.txt', 'www.cnb.cz', ['GET'], ['https']),
            // phpcs:enable Generic.Files.LineLength.TooLong
            function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case '':
                        return new Response(body: fopen(__DIR__ . '/../data/daily.txt', 'r'));

                    case 'date=13.06.2025':
                        return new Response(body: fopen(__DIR__ . '/../data/2025-06-13.txt', 'r'));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $query);
                }
            }
        );

        return $client;
    }
}
