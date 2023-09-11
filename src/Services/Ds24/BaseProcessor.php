<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Services\Ds24;

use AlexKassel\Scraper\Support\Fetcher;
use AlexKassel\Scraper\Services\Ds24\Ds24Trait;

abstract class BaseProcessor
{
    use Ds24Trait;

    protected array $output;
    protected array $data;

    public function __construct(
        protected Fetcher $fetcher
    ) {}

    public function expandOutput(array $output): array
    {
        $this->output = $output;

        if (! $this->dataPreparationSucceeded()) {
            return $this->output;
        }

        $this->processOutput();

        if (config('app.debug')) {
            $this->debug();
        }

        return $this->output;
    }

    abstract protected function dataPreparationSucceeded(): bool;

    abstract protected function processOutput(): void;

    protected function debug(): void
    {
        $this->output['debug'] = $this->data;
    }
}
