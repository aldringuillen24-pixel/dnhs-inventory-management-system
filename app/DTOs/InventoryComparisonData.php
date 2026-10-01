<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

final readonly class InventoryComparisonData
{
    public function __construct(
        public string $metric,
        public string $scope,
        public ?int $itemId,
        public ?string $itemName,
        public ?string $unit,
        public CarbonImmutable $monthOne,
        public CarbonImmutable $monthTwo,
    ) {}
}