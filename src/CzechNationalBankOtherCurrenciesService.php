<?php

declare(strict_types=1);

namespace Peso\Services;

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
use Peso\Services\CzechNationalBankService\CNBCommon;

final readonly class CzechNationalBankOtherCurrenciesService implements PesoServiceInterface
{
    use CNBCommon;

    // phpcs:disable Generic.Files.LineLength.TooLong
    private const ENDPOINT = 'https://www.cnb.cz/en/financial-markets/foreign-exchange-market/fx-rates-of-other-currencies/fx-rates-of-other-currencies/fx_rates.txt';
    // phpcs:enable Generic.Files.LineLength.TooLong

    public function send(object $request): ExchangeRateResponse|ErrorResponse
    {
        if ($request instanceof CurrentExchangeRateRequest) {
            if ($request->quoteCurrency !== 'CZK') {
                return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
            }

            $baseCurrency = $request->baseCurrency;
            $query = '';
        } elseif ($request instanceof HistoricalExchangeRateRequest) {
            if ($request->quoteCurrency !== 'CZK') {
                return new ErrorResponse(ExchangeRateNotFoundException::fromRequest($request));
            }
            if (
                $request->date->getYear() < 2004 ||
                $request->date->getYear() === 2004 && $request->date->getMonthNumber() < 6
            ) {
                return new ErrorResponse(new ExchangeRateNotFoundException(
                    'No historical data for dates earlier than June 2004',
                ));
            }
            $today = Calendar::fromDateTime($this->clock->now());
            if ($today->sub($request->date) < 0) {
                return new ErrorResponse(new ExchangeRateNotFoundException('Date seems to be in future'));
            }

            $baseCurrency = $request->baseCurrency;
            $year = $request->date->getYear();
            $month = $request->date->getMonthNumber() - 1; // previous month
            if ($month === 0) {
                $month = 12;
                $year -= 1;
            }

            $query = '?' . http_build_query([
                'year' => $year,
                'month' => $month,
            ], encoding_type: PHP_QUERY_RFC3986);
        } else {
            return new ErrorResponse(RequestNotSupportedException::fromRequest($request));
        }

        $url = self::ENDPOINT . $query;
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
            $request->quoteCurrency === 'CZK' && (
                $request->date->getYear() >= 2005 ||
                // 31 May 2004 is the earliest day making June 2004 the earliest usable date
                $request->date->getYear() === 2004 && $request->date->getMonthNumber() >= 6
            )
        ) {
            return true;
        }
        return false;
    }
}
