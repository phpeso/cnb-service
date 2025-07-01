<?php

declare(strict_types=1);

namespace Peso\Services;

use Arokettu\Clock\SystemClock;
use Arokettu\Date\Calendar;
use Arokettu\Date\Date;
use DateInterval;
use Error;
use Peso\Core\Exceptions\ExchangeRateNotFoundException;
use Peso\Core\Exceptions\RequestNotSupportedException;
use Peso\Core\Helpers\Calculator;
use Peso\Core\Requests\CurrentExchangeRateRequest;
use Peso\Core\Requests\HistoricalExchangeRateRequest;
use Peso\Core\Responses\ErrorResponse;
use Peso\Core\Responses\ExchangeRateResponse;
use Peso\Core\Services\PesoServiceInterface;
use Peso\Core\Services\ReversibleService;
use Peso\Core\Services\SDK\Cache\NullCache;
use Peso\Core\Services\SDK\Exceptions\HttpFailureException;
use Peso\Core\Services\SDK\HTTP\DiscoveredHttpClient;
use Peso\Core\Services\SDK\HTTP\DiscoveredRequestFactory;
use Peso\Core\Services\SDK\HTTP\UserAgentHelper;
use Peso\Core\Types\Decimal;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\SimpleCache\CacheInterface;

final readonly class CzechNationalBankService implements PesoServiceInterface
{
    // phpcs:disable Generic.Files.LineLength.TooLong
    private const ENDPOINT = 'https://www.cnb.cz/en/financial-markets/foreign-exchange-market/central-bank-exchange-rate-fixing/central-bank-exchange-rate-fixing/daily.txt';
    // phpcs:enable Generic.Files.LineLength.TooLong

    public function __construct(
        private CacheInterface $cache = new NullCache(),
        private DateInterval $ttl = new DateInterval('PT1H'),
        private ClientInterface $httpClient = new DiscoveredHttpClient(),
        private RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    public static function reversible(
        CacheInterface $cache = new NullCache(),
        DateInterval $ttl = new DateInterval('PT1H'),
        ClientInterface $httpClient = new DiscoveredHttpClient(),
        RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        ClockInterface $clock = new SystemClock(),
    ): PesoServiceInterface {
        return new ReversibleService(new self($cache, $ttl, $httpClient, $requestFactory, $clock));
    }

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
        $cacheKey = hash('sha1', __CLASS__ . '|' . $url);

        $data = $this->cache->get($cacheKey);

        if ($data === null) {
            $request = $this->requestFactory->createRequest('GET', $url);
            $request = $request->withHeader('User-Agent', UserAgentHelper::buildUserAgentString(
                'CNB-Client',
                'peso/cnb-service',
                $request->hasHeader('User-Agent') ? $request->getHeaderLine('User-Agent') : null,
            ));
            $response = $this->httpClient->sendRequest($request);

            if ($response->getStatusCode() !== 200) {
                throw HttpFailureException::fromResponse($request, $response);
            }

            $responseData = (string)$response->getBody();

            $lines = explode("\n", $responseData);

            // line 0 is a date

            $dateLine = $lines[0] ?? '0';

            if (!preg_match('/^(\d+ \w+ \d{4}) #\d+$/', $dateLine, $matches)) {
                throw new Error('Invalid date. Format change?');
            }

            $date = Calendar::parseDateTimeString($matches[1]);

            // line 1 is a header
            if (($lines[1] ?? null) !== 'Country|Currency|Amount|Code|Rate') {
                throw new Error('Invalid header. Format change?');
            }

            $data = [
                'date' => $date->julianDay,
                'rates' => [],
            ];
            $calculator = Calculator::instance();
            for ($i = 2; $i < \count($lines); ++$i) {
                if ($lines[$i] === '') {
                    break;
                }
                $line = explode('|', $lines[$i]);
                $code = $line[3];
                $rate = new Decimal($line[4]);
                // a perfect decimal inversion that should not increase precision
                $per = $calculator->trimZeros($calculator->invert(new Decimal($line[2])));
                $rate = $calculator->multiply($rate, $per);
                $data['rates'][$code] = $rate->value;
            }

            $this->cache->set($cacheKey, $data, $this->ttl);
        }

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
