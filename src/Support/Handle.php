<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Support;

class Handle
{
    public static function InvalidArgument(string $message): void
    {
        if (config('app.debug')) {
            throw new \InvalidArgumentException($message);
        }

        abort(404);
    }
}
