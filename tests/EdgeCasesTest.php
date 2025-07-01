<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Clock\StaticClock;
use Arokettu\Date\Calendar;
use Error;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Services\CzechNationalBankService;
use PHPUnit\Framework\TestCase;
use stdClass;

final class EdgeCasesTest extends TestCase
{
    public function testInvalidRequest(): void
    {
        $service = new CzechNationalBankService();

        $response = $service->send(new stdClass());
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(RequestNotSupportedException::class, $response->exception);
        self::assertEquals('Unsupported request type: "stdClass"', $response->exception->getMessage());
    }

    public function testFutureDate(): void
    {
        $clock = StaticClock::fromDateString('2025-06-18'); // 'now'
        $future = Calendar::parse('2025-06-19');

        $service = new CzechNationalBankService(clock: $clock);

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'CZK', $future));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Date seems to be in future', $response->exception->getMessage());
    }

    public function testHttpFailure(): void
    {
        $http = new Client();
        $http->setDefaultResponse(new Response(500, body: 'Server error or something'));

        $service = new CzechNationalBankService(httpClient: $http);

        self::expectException(HttpFailureException::class);
        self::expectExceptionMessage('HTTP error 500. Response is "Server error or something"');
        $service->send(new CurrentExchangeRateRequest('EUR', 'CZK'));
    }

    public function testInvalidDateInResponse(): void
    {
        $http = new Client();
        $http->setDefaultResponse(new Response(body: 'Not a date'));

        $service = new CzechNationalBankService(httpClient: $http);

        self::expectException(Error::class);
        self::expectExceptionMessage('Invalid date. Format change?');
        $service->send(new CurrentExchangeRateRequest('EUR', 'CZK'));
    }

    public function testInvalidHeaderInResponse(): void
    {
        $http = new Client();
        $http->setDefaultResponse(new Response(body: <<<BODY
            5 Nov 2000 #0
            Some|New|Header
            Val1|1|1000
            BODY));

        $service = new CzechNationalBankService(httpClient: $http);

        self::expectException(Error::class);
        self::expectExceptionMessage('Invalid header. Format change?');
        $service->send(new CurrentExchangeRateRequest('EUR', 'CZK'));
    }
}
