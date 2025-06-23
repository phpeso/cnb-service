# Czech National Bank Service for Peso

[![Packagist]][Packagist Link]
[![PHP]][Packagist Link]
[![License]][License Link]

[Packagist]: https://img.shields.io/packagist/v/peso/cnb-service.svg?style=flat-square
[PHP]: https://img.shields.io/packagist/php-v/peso/cnb-service.svg?style=flat-square
[License]: https://img.shields.io/packagist/l/peso/cnb-service.svg?style=flat-square

[Packagist Link]: https://packagist.org/packages/peso/cnb-service
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
use Peso\Services\CzechNationalBankService;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;

require __DIR__ . '/vendor/autoload.php';

$cache = new Psr16Cache(new FilesystemAdapter(directory: __DIR__ . '/cache'));
$service = new CzechNationalBankService($cache);
$converter = new CurrencyConverter($service);
```

## Documentation

Read the full documentation here: <https://phpeso.org/v0.x/services/cnb.html>

## Support

Please file issues on our main repo at GitHub: <https://github.com/phpeso/cnb-service/issues>

## License

The library is available as open source under the terms of the [MIT License][License Link].
