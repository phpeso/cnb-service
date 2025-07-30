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
use Peso\Services\CzechNationalBankOtherCurrenciesService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

final class OtherCurrenciesRequestTest extends TestCase
{
    public function testCurrentRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();

        $service = new CzechNationalBankOtherCurrenciesService(cache: $cache, httpClient: $http);

        $response = $service->send(new CurrentExchangeRateRequest('RSD', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.21155', $response->rate->value);
        self::assertEquals('2025-06-30', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('RUB', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.26924', $response->rate->value);
        self::assertEquals('2025-06-30', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('KZT', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.04066', $response->rate->value);
        self::assertEquals('2025-06-30', $response->date->toString());

        $response = $service->send(new CurrentExchangeRateRequest('MNT', 'CZK'));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.005900', $response->rate->value);
        self::assertEquals('2025-06-30', $response->date->toString());
    }

    public function testHistoricalRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-20');

        $service = new CzechNationalBankOtherCurrenciesService(cache: $cache, httpClient: $http, clock: $clock);

        $date = Calendar::parse('2025-06-13');

        $response = $service->send(new HistoricalExchangeRateRequest('RSD', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.21288', $response->rate->value);
        self::assertEquals('2025-05-30', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('RUB', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.27985', $response->rate->value);
        self::assertEquals('2025-05-30', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('KZT', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.04303', $response->rate->value);
        self::assertEquals('2025-05-30', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('MNT', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.006146', $response->rate->value);
        self::assertEquals('2025-05-30', $response->date->toString());
    }

    public function testHistoricalRollOverRate(): void
    {
        $cache = new Psr16Cache(new ArrayAdapter());
        $http = MockClient::get();
        $clock = StaticClock::fromDateString('2025-06-20');

        $service = new CzechNationalBankOtherCurrenciesService(cache: $cache, httpClient: $http, clock: $clock);

        $date = Calendar::parse('2015-01-12'); // this should request data for Dec 2014

        $response = $service->send(new HistoricalExchangeRateRequest('RSD', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.22782', $response->rate->value);
        self::assertEquals('2014-12-31', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('UAH', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('1.448', $response->rate->value);
        self::assertEquals('2014-12-31', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('KZT', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.12495', $response->rate->value);
        self::assertEquals('2014-12-31', $response->date->toString());

        $response = $service->send(new HistoricalExchangeRateRequest('MNT', 'CZK', $date));
        self::assertInstanceOf(ExchangeRateResponse::class, $response);
        self::assertEquals('0.01213', $response->rate->value);
        self::assertEquals('2014-12-31', $response->date->toString());
    }

    public function testCzkOnly(): void
    {
        $service = new CzechNationalBankOtherCurrenciesService();

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

    public function testAfter2004Only(): void
    {
        $service = new CzechNationalBankOtherCurrenciesService();

        $response = $service->send(new HistoricalExchangeRateRequest('PHP', 'CZK', Calendar::parse('1990-12-31')));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals(
            'No historical data for dates earlier than June 2004',
            $response->exception->getMessage(),
        );
    }

    public function testAfterJuneOnly(): void
    {
        $service = new CzechNationalBankOtherCurrenciesService();

        $response = $service->send(new HistoricalExchangeRateRequest('PHP', 'CZK', Calendar::parse('2004-05-31')));
        self::assertInstanceOf(ErrorResponse::class, $response);
        self::assertInstanceOf(ExchangeRateNotFoundException::class, $response->exception);
        self::assertEquals(
            'No historical data for dates earlier than June 2004',
            $response->exception->getMessage(),
        );
    }
}
