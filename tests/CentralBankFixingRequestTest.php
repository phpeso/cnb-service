<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Clock\StaticClock;
use Arokettu\Date\Calendar;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Services\CzechNationalBank\CentralBankFixingService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class CentralBankFixingRequestTest extends TestCase
{
    public function testCurrentRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CentralBankFixingService(cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('EUR', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.825', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('CHF', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('26.369', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('PHP', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.37681', $response->rate->value);
        self::assertEquals('2025-06-20', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('THB', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.65740', $response->rate->value); // same precision after recalculation
        self::assertEquals('2025-06-20', $response->date->toString());
    }

    public function testHistoricalRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-20');

        $service = new CentralBankFixingService(cache: $cache, httpClient: $http, clock: $clock);

        $date = Calendar::parse('2025-06-13');

        $response = $service->send(new HistoricalExchangeRateRequest('EUR', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('24.840', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('CHF', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('26.540', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('PHP', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.38377', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('THB', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.66487', $response->rate->value);
        self::assertEquals('2025-06-13', $response->date->toString());
    }

    public function testCzkOnly(): void
    {
        $service = new CentralBankFixingService();

        $response = $service->send(new CurrentExchangeRateRequest('PHP', 'USD'));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals('Unable to find exchange rate for PHP/USD', $response->exception->getMessage());

        $response = $service->send(new HistoricalExchangeRateRequest('PHP', 'USD', Calendar::parse('2025-05-06')));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals(
            'Unable to find exchange rate for PHP/USD on 2025-05-06',
            $response->exception->getMessage(),
        );
    }

    public function testAfter1991Only(): void
    {
        $service = new CentralBankFixingService();

        $response = $service->send(new HistoricalExchangeRateRequest('PHP', 'CZK', Calendar::parse('1990-12-31')));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals(
            'No historical data for dates earlier than 1991',
            $response->exception->getMessage(),
        );
    }
}
