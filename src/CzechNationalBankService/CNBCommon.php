<?php

declare(strict_types=1);

namespace Peso\Services\CzechNationalBankService;

use Arokettu\Clock\SystemClock;
use Arokettu\Date\Calendar;
use DateInterval;
use Error;
use Peso\Core\Helpers\Calculator;
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

trait CNBCommon
{
    public function __construct(
        private readonly CacheInterface $cache = new NullCache(),
        private readonly DateInterval $ttl = new DateInterval('PT1H'),
        private readonly ClientInterface $httpClient = new DiscoveredHttpClient(),
        private readonly RequestFactoryInterface $requestFactory = new DiscoveredRequestFactory(),
        private readonly ClockInterface $clock = new SystemClock(),
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

    private function getRateData(string $url): array
    {
        $cacheKey = 'peso|cnb|' . hash('sha1', $url);

        $data = $this->cache->get($cacheKey);

        if ($data !== null) {
            return $data;
        }

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
            if ((float)$line[4] === 0.0) { // Special ZWL handling
                continue;
            }
            $rate = new Decimal($line[4]);
            // a perfect decimal inversion that should not increase precision
            $per = $calculator->trimZeros($calculator->invert(new Decimal($line[2])));
            $rate = $calculator->multiply($rate, $per);
            $data['rates'][$code] = $rate->value;
        }

        $this->cache->set($cacheKey, $data, $this->ttl);

        return $data;
    }
}
