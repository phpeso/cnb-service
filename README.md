# Czech National Bank Service for Peso

[![Packagist]][Packagist Link]
[![PHP]][Packagist Link]
[![License]][License Link]
[![GitHub Actions]][GitHub Actions Link]
[![Codecov]][Codecov Link]

[Packagist]: https://img.shields.io/packagist/v/peso/cnb-service.svg?style=flat-square
[PHP]: https://img.shields.io/packagist/php-v/peso/cnb-service.svg?style=flat-square
[License]: https://img.shields.io/packagist/l/peso/cnb-service.svg?style=flat-square
[GitHub Actions]: https://img.shields.io/github/actions/workflow/status/phpeso/cnb-service/ci.yml?style=flat-square
[Codecov]: https://img.shields.io/codecov/c/gh/phpeso/cnb-service?style=flat-square

[Packagist Link]: https://packagist.org/packages/peso/cnb-service
[GitHub Actions Link]: https://github.com/phpeso/cnb-service/actions
[Codecov Link]: https://codecov.io/gh/phpeso/cnb-service
[License Link]: LICENSE.md

This is an exchange data provider for Peso that retrieves data from
[the Czech National Bank](https://www.cnb.cz/en/).

## Installation

```bash
composer require peso/cnb-service
```

Install the service with all recommended dependencies:

```bash
composer install peso/cnb-service php-http/discovery guzzlehttp/guzzle symfony/cache
```

## Example

```php
<?php

use Peso\Peso\CurrencyConverter;
use Peso\Services\CzechNationalBank\CentralBankFixingService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

require __DIR__ . '/vendor/autoload.php';

$cache = new Psr16Cache(new FilesystemAdapter(directory: __DIR__ . '/cache'));
$service = new CentralBankFixingService($cache);
$converter = new CurrencyConverter($service);
```

## Documentation

Read the full documentation here: <https://phpeso.org/v1.x/services/cnb.html>

## Support

Please file issues on our main repo at GitHub: <https://github.com/phpeso/cnb-service/issues>

## License

The library is available as open source under the terms of the [MIT License][License Link].
