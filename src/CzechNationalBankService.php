<?php

/**
 * @copyright 2025 Anton Smirnov
 * @license MIT https://spdx.org/licenses/MIT.html
 */

declare(strict_types=1);

namespace Peso\Services;

use Peso\Services\CzechNationalBank\CentralBankFixingService;

class_alias(CentralBankFixingService::class, CzechNationalBankService::class);

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
if (false) {
    /**
     * @deprecated PesoServiceInterface
     */
    final readonly class CzechNationalBankService extends CentralBankFixingService
    {
    }
}
