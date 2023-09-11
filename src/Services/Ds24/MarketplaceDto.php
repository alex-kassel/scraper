<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Services\Ds24;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use AlexKassel\Scraper\Support\DataTransferObject;

final readonly class MarketplaceDto extends DataTransferObject
{
    protected function processData(): array
    {
        return $this->input;
    }
}
