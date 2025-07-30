<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Arokettu\Date\Calendar;
use Arokettu\Date\Date;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Services\CzechNationalBankOtherCurrenciesService;
use Peso\Services\CzechNationalBankService;
use PHPUnit\Framework\TestCase;
use stdClass;

final class SupportTest extends TestCase
{
    public function testRequests(): void
    {
        $service = new CzechNationalBankService();

        self::assertTrue($service->supports(new CurrentExchangeRateRequest('EUR', 'CZK')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('EUR', 'CZK', Date::today())));
        self::assertFalse($service->supports(new CurrentExchangeRateRequest('USD', 'EUR')));
        self::assertFalse($service->supports(new HistoricalExchangeRateRequest('USD', 'EUR', Date::today())));
        self::assertFalse($service->supports(
            new HistoricalExchangeRateRequest('EUR', 'CZK', Calendar::parse('1990-01-01')),
        ));
        self::assertFalse($service->supports(new stdClass()));
    }

    public function testRequestsOther(): void
    {
        $service = new CzechNationalBankOtherCurrenciesService();

        self::assertTrue($service->supports(new CurrentExchangeRateRequest('EUR', 'CZK')));
        self::assertTrue($service->supports(new HistoricalExchangeRateRequest('EUR', 'CZK', Date::today())));
        self::assertFalse($service->supports(new CurrentExchangeRateRequest('USD', 'EUR')));
        self::assertFalse($service->supports(new HistoricalExchangeRateRequest('USD', 'EUR', Date::today())));
        self::assertFalse($service->supports(
            new HistoricalExchangeRateRequest('EUR', 'CZK', Calendar::parse('1990-01-01')),
        ));
        self::assertFalse($service->supports(new stdClass()));
    }
}
