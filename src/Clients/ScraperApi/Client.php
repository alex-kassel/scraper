<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Clients\ScraperApi;

use AlexKassel\Scraper\Clients\BaseClient;

class Client extends BaseClient
{
    protected string $endpoint = 'http://api.scraperapi.com?api_key=%s&url=%s';
}
