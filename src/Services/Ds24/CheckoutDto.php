<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Services\Ds24;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use AlexKassel\Scraper\Support\DataTransferObject;

final readonly class CheckoutDto extends DataTransferObject
{
    protected function processData(): array
    {
        return [
            'status' => $this->status(),
            'global' => $this->global(),
            'opengraph' => $this->opengraph(),
            'assets' => $this->assets(),
            'products' => $this->products(),
            'payments' => $this->payments(),
            'shipping_costs' => $this->shippingCosts(),
            'payment_plans' => $this->paymentPlans(),
            'redir_info' => $this->redirInfo(),
            'debug' => $this->debug(),
        ];
    }

    private function status(): array {
        return [
            'code' => (int) Arr::get($this->input, 'status.code'),
            'location' => or_null('string', Arr::get($this->input, 'status.location')),
            'message' => or_null('string', Arr::get($this->input, 'status.message')),
        ];
    }

    private function global(): array {
        return [
            'lang' => (string) Arr::get($this->input, 'global.lang'),
            'title' => Str::squish(Arr::get($this->input, 'global.title')),
            'variant' => (string) Arr::get($this->input, 'global.variant'),
            'merchant' => (int) Arr::get($this->input, 'global.merchant'),
            'affiliate' => [
                'id' => (int) Arr::get($this->input, 'global.affiliate.id'),
                'name' => (string) Arr::get($this->input, 'global.affiliate.name'),
                'default' => (bool) Arr::get($this->input, 'global.affiliate.default'),
            ],
            'currency_code' => (string) Arr::get($this->input, 'global.currency_code'),
            'as_upgrade_only' => (bool) Arr::get($this->input, 'global.as_upgrade_only'),
            'is_voucher_input_enabled' => (bool) Arr::get($this->input, 'global.is_voucher_input_enabled'),
            'can_preselect_payplan' => (bool) Arr::get($this->input, 'global.can_preselect_payplan'),
            'refund_days' => or_null('int', Arr::get($this->input, 'global.refund_days')),
        ];
    }

    private function opengraph(): array {
        return [
            'image' => (string) Arr::get($this->input, 'opengraph.image'),
            'title' => Str::squish(Arr::get($this->input, 'opengraph.title')),
            'description' => Str::squish(Arr::get($this->input, 'opengraph.description')),
        ];
    }

    private function assets(): array {
        return [
            'socialproof' => (string) Arr::get($this->input, 'assets.socialproof'),
            'images' => collect(Arr::get($this->input, 'assets.images'))
                ->map(fn($image) => (string) $image)
                ->unique()
                ->values()
                ->all(),
        ];
    }

    private function products(): array {
        return collect(Arr::get($this->input, 'products'))->map(function ($product) {
            return [
                'product_id' => (int) Arr::get($product, 'product_id'),
                'image' => (string) Arr::get($product, 'image'),
                'headline' => Str::squish(Arr::get($product, 'headline')),
                'description' => Str::squish(Arr::get($product, 'description')),
                'order_bump' => Str::squish(Arr::get($product, 'order_bump')),
                'is_optional_if_addons_present' => (bool) Arr::get($product, 'is_optional_if_addons_present'),
                'min_quantity' => (int) Arr::get($product, 'min_quantity'),
                'max_quantity' => (int) Arr::get($product, 'max_quantity'),
                'has_discount' => (bool) Arr::get($product, 'has_discount'),
            ];
        })->all();
    }

    private function payments(): array {
        return collect(Arr::get($this->input, 'payments'))->map(function ($payment) {
            return [
                'payment_provider_id' => (int) Arr::get($payment, 'payment_provider_id'),
                'pay_method' => (string) Arr::get($payment, 'pay_method'),
                'headline' => (string) Arr::get($payment, 'headline'),
            ];
        })->all();
    }

    private function shippingCosts(): array {
        return collect(Arr::get($this->input, 'shipping_costs'))->map(function ($shipping_cost) {
            return [
                'id' => (int) Arr::get($shipping_cost, 'id'),
                'fee_type' => (string) Arr::get($shipping_cost, 'fee_type'),
                'billing_cycle' => (string) Arr::get($shipping_cost, 'billing_cycle'),
                'label' => (string) Arr::get($shipping_cost, 'label'),
                'for_product_ids' => collect(Arr::get($shipping_cost, 'for_product_ids'))
                    ->map(fn($product_id) => (int) $product_id)
                    ->all(),
                'first_amount' => round((float) Arr::get($shipping_cost, 'first_amount'), 2),
                'other_amounts' => round((float) Arr::get($shipping_cost, 'other_amounts'), 2),
                'scale_level_count' => (int) Arr::get($shipping_cost, 'scale_level_count'),
                'scale_2_from' => or_null('int', Arr::get($shipping_cost, 'scale_2_from')),
                'scale_2_amount' => or_null('float:2', Arr::get($shipping_cost, 'scale_2_from')),
                'scale_3_from' => or_null('int', Arr::get($shipping_cost, 'scale_3_from')),
                'scale_3_amount' => or_null('float:2', Arr::get($shipping_cost, 'scale_3_from')),
                'scale_4_from' => or_null('int', Arr::get($shipping_cost, 'scale_4_from')),
                'scale_4_amount' => or_null('float:2', Arr::get($shipping_cost, 'scale_4_from')),
                'scale_5_from' => or_null('int', Arr::get($shipping_cost, 'scale_5_from')),
                'scale_5_amount' => or_null('float:2', Arr::get($shipping_cost, 'scale_5_from')),
                'is_forbidden' => (bool) Arr::get($shipping_cost, 'is_forbidden'),
            ];
        })->all();
    }

    private function paymentPlans(): array {
        return collect(Arr::get($this->input, 'payment_plans'))->map(function ($payment_plan) {
            return [
                'payplan_id' => (int) Arr::get($payment_plan, 'payplan_id'),
                'number_of_installments' => (int) Arr::get($payment_plan, 'number_of_installments'),
                'billing_type' => (string) Arr::get($payment_plan, 'billing_type'),
                'first_billing_interval' => (string) Arr::get($payment_plan, 'first_billing_interval'),
                'other_billing_intervals' => (string) Arr::get($payment_plan, 'other_billing_intervals'),
                'test_phase' => (bool) Arr::get($payment_plan, 'test_phase'),
                'products' => collect(Arr::get($payment_plan, 'products'))->map(function ($product) {
                    return [
                        'is_voucher' => (bool) Arr::get($product, 'is_voucher'),
                        'vat_rate' => (int) Arr::get($product, 'vat_rate'),
                        'first_amount' => round((float) Arr::get($product, 'first_amount'), 2),
                        'other_amounts' => round((float) Arr::get($product, 'other_amounts'), 2),
                        'discounts' => collect(Arr::get($product, 'discounts'))->map(function ($discount) {
                            return [
                                'from_quantity' => (int) Arr::get($discount, 'from_quantity'),
                                'unit_price_1st' => round((float) Arr::get($discount, 'unit_price_1st'), 2),
                                'unit_price_oth' => round((float) Arr::get($discount, 'unit_price_oth'), 2),
                            ];
                        })->all(),
                    ];
                })->all(),
            ];
        })->all();
    }

    private function redirInfo(): array {
        return [
            'status' => [
                'code' => (int) Arr::get($this->input, 'redir_info.status.code'),
                'location' => or_null('string', Arr::get($this->input, 'redir_info.status.location')),
                'message' => or_null('string', Arr::get($this->input, 'redir_info.status.message')),
            ],
        ];
    }

    private function debug(): array
    {
        return config('app.debug') ? (array) Arr::get($this->input, 'debug') : [];
    }
}
