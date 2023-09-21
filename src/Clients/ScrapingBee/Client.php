<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Clients\ScrapingBee;

use AlexKassel\Scraper\Clients\BaseClient;

class Client extends BaseClient
{
    protected string $endpoint = 'https://app.scrapingbee.com/api/v1/?api_key=%s&url=%s';

    protected function optionalParameters(): array
    {
        return [
            #'block_resources' => 'False',
            'premium_proxy' => 'True', # 25 credits
            #'stealth_proxy' => 'True', # 75 credits
            'extract_rules' => trim(json_encode([
                'title' => 'title',
                'items' => [
                    'selector' => 'a[data-listing-id]',
                    'type' => 'list',
                    'output' => [
                     #   'id' => '@data-listing-id',
                    #    'sponsored' => '.top-label',
                        'name' => '.h3',
                        'url' => '@href',
                    ],
                ],
            ])),
        ];
    }
}
