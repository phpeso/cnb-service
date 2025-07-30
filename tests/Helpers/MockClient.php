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
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case '':
                        return new Response(body: fopen(__DIR__ . '/../data/cbfix/daily.txt', 'r'));

                    case 'date=13.06.2025':
                        return new Response(body: fopen(__DIR__ . '/../data/cbfix/2025-06-13.txt', 'r'));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $query);
                }
            },
        );
        $client->on(
            // phpcs:disable Generic.Files.LineLength.TooLong
            new RequestMatcher('/en/financial-markets/foreign-exchange-market/fx-rates-of-other-currencies/fx-rates-of-other-currencies/fx_rates.txt', 'www.cnb.cz', ['GET'], ['https']),
            // phpcs:enable Generic.Files.LineLength.TooLong
            static function (RequestInterface $request) {
                $query = $request->getUri()->getQuery();
                switch ($query) {
                    case '':
                        return new Response(body: fopen(__DIR__ . '/../data/other/fx_rates.txt', 'r'));

                    case 'year=2025&month=5':
                        return new Response(body: fopen(__DIR__ . '/../data/other/2025-05.txt', 'r'));

                    case 'year=2014&month=12':
                        return new Response(body: fopen(__DIR__ . '/../data/other/2014-12.txt', 'r'));

                    default:
                        throw new \LogicException('Non-mocked query: ' . $query);
                }
            },
        );

        return $client;
    }
}
