<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Support;

abstract readonly class DataTransferObject
{
    public array $output;

    public function __construct(
        public array $input
    ) {
        $this->output = array_filter_recursive(
            $this->processData(),
            function($value, $key) {
                return ! is_null($value);
            },
            false
        );
    }

    abstract protected function processData(): array;
}
