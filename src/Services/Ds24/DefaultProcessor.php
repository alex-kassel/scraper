<?php

declare(strict_types=1);

namespace App\Services\Ds24;

use App\Services\Ds24\Processor;

class DefaultProcessor extends Processor
{
    public function expandOutput(array $output): array
    {
        $output['status']['message'] = 'Page variant not identified.';
        return $output;
    }
}
