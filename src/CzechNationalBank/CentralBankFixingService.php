<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services\CzechNationalBank;

use Arokettu\Date\Calendar;
use Arokettu\Date\Date;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Core\Services\PesoServiceInterface;
use Peso\Core\Types\Decimal;

/**
 * Official CNB daily rates
 */
final readonly class CentralBankFixingService implements PesoServiceInterface
{
    use Common;

    // phpcs:disable Generic.Files.LineLength.TooLong
    private const ENDPOINT = 'https://www.cnb.cz/en/financial-markets/foreign-exchange-market/central-bank-exchange-rate-fixing/central-bank-exchange-rate-fixing/daily.txt';
    // phpcs:enable Generic.Files.LineLength.TooLong

    public function send(object $request): ExchangeRateResponse|ErrorResponse
    {
        if ($request instanceof CurrentExchangeRateRequest) {
            if ($request->quoteCurrency !== 'CZK') {
                return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
            }

            $baseCurrency = $request->baseCurrency;
            $date = '';
        } elseif ($request instanceof HistoricalExchangeRateRequest) {
            if ($request->quoteCurrency !== 'CZK') {
                return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
            }
            if ($request->date->getYear() < 1991) {
                return new ErrorResponse(new ExchangeRateNotFoundException(
                    'No historical data for dates earlier than 1991',
                ));
            }
            $today = Calendar::fromDateTime($this->clock->now());
            if ($today->sub($request->date) < 0) {
                return new ErrorResponse(new ExchangeRateNotFoundException('Date seems to be in future'));
            }

            $baseCurrency = $request->baseCurrency;
            $date = \sprintf(
                '?date=%02d.%02d.%d',
                $request->date->getDay(),
                $request->date->getMonthNumber(),
                $request->date->getYear(),
            );
        } else {
            return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
        }

        $url = self::ENDPOINT . $date;
        $data = $this->getRateData($url);

        return isset($data['rates'][$baseCurrency]) ?
            new ExchangeRateResponse(new Decimal($data['rates'][$baseCurrency]), new Date($data['date'])) :
            new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
    }

    public function supports(object $request): bool
    {
        if ($request instanceof CurrentExchangeRateRequest && $request->quoteCurrency === 'CZK') {
            return true;
        }
        if (
            $request instanceof HistoricalExchangeRateRequest &&
            $request->quoteCurrency === 'CZK' &&
            $request->date->getYear() >= 1991
        ) {
            return true;
        }
        return false;
    }
}
