<?php

declare(strict_types=1);

namespace Peso\Services\Tests;

use Peso\Core\Helpers\Calculator;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\SuccessResponse;
use Peso\Services\CzechNationalBankService;
use Peso\Services\Tests\Helpers\MockClient;
use PHPUnit\Framework\TestCase;

class WrappedServicesTest extends TestCase
{
    public function testReversible(): void
    {
        $http = MockClient::get();

        $baseService = new CzechNationalBankService(httpClient: $http);
        $service = CzechNationalBankService::reversible(httpClient: $http);

        $request = new CurrentExchangeRateRequest('CZK', 'USD');

        // base service doesn't support
        self::assertInstanceOf(ErrorResponse::class, $baseService->send($request));

        $response = $service->send($request);
        self::assertInstanceOf(SuccessResponse::class, $response);
        // ignore calculator changes
        self::assertEquals('0.0463929', Calculator::instance()->round($response->rate, 7)->value);
    }
}
