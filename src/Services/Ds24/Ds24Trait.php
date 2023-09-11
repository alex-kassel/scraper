<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Services\Ds24;

trait Ds24Trait
{
    protected $base_url = 'https://www.checkout-ds24.com';

    protected function formatUrl(?string $url, ?string $base_url = null, mixed $mixed = null): ?string
    {
        return format_url(
            $url,
            $base_url ?? $this->base_url,
            [
                'regex' => 'merchant_',
                'replace' => [
                    'search' => [
                        'digistore24.com'
                    ],
                    'replace' => [
                        'checkout-ds24.com'
                    ],
                ]
            ]
        );
    }

    protected function translate($key): string
    {
        $translate = [
            "Price" => "price",
            "Verkaufspreis" => "price",
            "Commission" => "commission",
            "Provision" => "commission",
            "Earnings/Sale**" => "earnings_sale",
            "Verdienst/Verkauf**" => "earnings_sale",
            "Earnings/Cart visitor**" => "earnings_cart",
            "Verdienst/Cartbesucher**" => "earnings_cart",
            "Vendor" => "vendor",
            "Created" => "created",
            "Erstellt" => "created",
            "Billing types" => "billing_types",
            "Bezahlarten" => "billing_types",
            "Cart conversion**" => "cart_conversion",
            "Cart Conversion**" => "cart_conversion",
            "Cancellation rate**" => "cancellation_rate",
            "Storno­quote**" => "cancellation_rate",
        ];

        if (! isset($translate[$key])) {
            throw new \InvalidArgumentException("Can't translate '$key'");
        }

        return $translate[$key];
    }
}
