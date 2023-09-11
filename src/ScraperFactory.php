<?php

declare(strict_types=1);

namespace AlexKassel\Scraper;

use AlexKassel\Scraper\Support\ScraperInterface;
use AlexKassel\Scraper\Support\Handle;

class ScraperFactory {
    private array $services = [
        'clickbank' => \AlexKassel\Scraper\Services\Clickbank\Processor::class,
        'copecart' => \AlexKassel\Scraper\Services\Copecart\Processor::class,
        'ds24' => \AlexKassel\Scraper\Services\Ds24\Processor::class,
    ];

    public function create(string $service): ScraperInterface
    {
        if (isset($this->services[$service])) {
            $serviceClass = $this->services[$service];
            return new $serviceClass();
        }

        Handle::InvalidArgument("Unknown service: '$service'");
    }
}
