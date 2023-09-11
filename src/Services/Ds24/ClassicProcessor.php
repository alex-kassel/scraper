<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Services\Ds24;

use Illuminate\Support\Arr;
use AlexKassel\Scraper\Services\Ds24\BaseProcessor;
use AlexKassel\Scraper\Support\JavaScriptVariablesExtractor;

class ClassicProcessor extends BaseProcessor
{
    use JavaScriptVariablesExtractor;

    protected function dataPreparationSucceeded(): bool
    {
        if (! $this->fillJsVars() || ! $this->fillPpTxt()) {
            $this->output['status']['message'] = 'No javascript detected.';
            return false;
        }

        return true;
    }

    protected function processOutput(): void
    {
        $this->setGlobals();
        $this->setProducts();
        $this->setPayments();
        $this->setShippingCosts();
        $this->setPaymentPlans();
        $this->setMessages();
    }

    protected function debug(): void
    {
        $this->data['output'] = $this->output;
        parent::debug();
    }

    private function setGlobals(): void
    {
        $_ =& $this->output['global'];

        $_['affiliate']['name'] = substr($this->fetcher->filter('#controll_code')->text(''), 1, -1)
            ?: Arr::get($this->data['js_vars'], 'DS24_AFFILIATE')
        ;
        $_['currency_code'] = Arr::get($this->data['js_vars'], 'currency_code');
        $_['as_upgrade_only'] = $this->fetcher->filter('[id$="upgrade_only"]')->count() > 0;
        $_['is_voucher_input_enabled'] = $this->fetcher->filter('#voucher_input')->count() > 0;
        $_['can_preselect_payplan'] = Arr::get($this->data['js_vars'], 'can_preselect_payplan');
        $_['refund_days'] = $this->fetcher->filter('.cb_refund_waiver')->count() > 0 ? 0 : null;
    }

    private function setProducts(): void
    {
        $order_bump = [];
        $this->fetcher->filter('.order_bump-body')->each(function($node, $i) use (&$order_bump) {
            $key = $node->filter('.order_bump-selection input')->attr('data-listen-product_id');
            $order_bump[$key] = $node->filter('.order_bump-details')->html('');
        });

        $this->fetcher->filter('#cart tbody td.details, .item_details')->each(function($node, $i) use ($order_bump) {
            $node = crawler($node->closest('tr'));
            $this->output['products'][] = [
                'product_id' => $product_id = Arr::get($this->data['js_vars'], "list_product_ids.$i"),
                'image' => $this->formatUrl($node->filter('img')->attr('src')),
                'headline' => $node->filter('.title, h3, .item_title')->text(''),
                'description' => $node->filter('.description, .item_description')->html(''),
                'order_bump' => $order_bump[$product_id] ?? null,
                'is_optional_if_addons_present' => ! Arr::get($this->data['js_vars'], "list_min_quantity.$i"),
                'min_quantity' => Arr::get($this->data['js_vars'], "list_min_quantity.$i"),
                'max_quantity' => Arr::get($this->data['js_vars'], "list_max_quantity.$i"),
                'has_discount' => false,
            ];
        });
    }

    private function setPayments(): void
    {
        $this->output['payments'] = [];

        if ($this->output['global']['variant'] == 'iframe') {
            $this->fetcher->filter('div.pay_selector')->each(function ($node, $i) {
                [$payment_provider_id, $pay_method] = explode('/', $node->filter('input')->attr('value'));
                if ($payment_provider_id > 0) {
                    $this->output['payments'][] = [
                        'payment_provider_id' => $payment_provider_id,
                        'pay_method' => $pay_method,
                        'headline' => $node->filter('img')->attr('alt'),
                    ];
                }
            });
        } else {
            $this->fetcher->filter('button[id^="pay_inputs"].submit')->each(function ($node, $i) {
                [, $payment_provider_id, $pay_method] = explode('/', $node->attr('value'));
                if ($payment_provider_id > 0) {
                    $this->output['payments'][] = [
                        'payment_provider_id' => $payment_provider_id,
                        'pay_method' => $pay_method,
                        'headline' => $node->text(''),
                    ];
                }
            });
        }
    }

    private function setShippingCosts(): void
    {
        $this->output['shipping_costs'] = [];

        foreach((array) Arr::get($this->data, 'js_vars.SHIPPING_COST') as $shipping_cost) {
            $vat_rate = Arr::get($this->data, 'js_vars.list_vat_rates.0');
            $devider = Arr::get($this->data, 'js_vars.price_mode')  == 'netto' ? 1 : (1 + $vat_rate / 100);

            $shipping = [];
            $shipping['id'] = Arr::get($shipping_cost, 'id');
            $shipping['fee_type'] = Arr::get($shipping_cost, 'fee_type');
            $shipping['billing_cycle'] = Arr::get($shipping_cost, 'billing_cycle');
            $shipping['label'] = Arr::get($shipping_cost, 'label');
            $shipping['for_product_ids'] = Arr::get($shipping_cost, 'for_product_ids');
            $shipping['first_amount'] = Arr::get($shipping_cost, 'scale_1_amount') / $devider;
            $shipping['other_amounts'] = Arr::get($shipping_cost, 'billing_cycle') == 'once' ? null : $shipping['first_amount'];
            $shipping['scale_level_count'] = Arr::get($shipping_cost, 'scale_level_count');

            for($i = 2; $i <= 5; $i++) {
                $shipping["scale_{$i}_from"] = Arr::get($shipping_cost, "scale_{$i}_from");
                $shipping["scale_{$i}_amount"] = Arr::get($shipping_cost, "scale_{$i}_amount");
            }

            $shipping['blocked_states'] = Arr::get($shipping_cost, 'blocked_states');
            $shipping['is_forbidden'] = Arr::get($shipping_cost, 'is_forbidden');

            $this->output['shipping_costs'][] = $shipping;
        }
    }

    private function setPaymentPlans(): void
    {
        $this->output['payment_plans'] = [];

        foreach((array) Arr::get($this->data, 'js_vars.payplan_ids') as $payplan_nr => $payplan_id) {
            $products = [];

            foreach((array) Arr::get($this->data, 'js_vars.list_product_ids') as $product_nr => $product_id) {
                $addon = "js_vars.list_payment_plan_addons.$product_nr.$payplan_id";

                if ($payplan_id > 0) {
                    data_fill($this->data, $addon, Arr::get($this->data, "js_vars.list_payment_plan_addons.$product_nr.0"));
                }

                $voucher_value = crawler($this->fetcher->filter("#value_voucher_$product_id"))->attr('value')
                    ?: crawler($this->fetcher->filter("#value_voucher_$product_id option:selected"))->attr('value');

                if (! $voucher_value = crawler($this->fetcher->filter("#value_voucher_$product_id"))->attr('value')) {
                    $voucher_value = crawler($this->fetcher->filter("#value_voucher_$product_id option:selected"))->attr('value');
                }

                if ($voucher_value) {
                    if ($product_nr > 0) {
                        Arr::set($this->data, "$addon.first_amount", $voucher_value);
                    } else {
                        Arr::set($this->data, "js_vars.payplan_first_amounts.$payplan_nr", $voucher_value);
                    }
                }

                $vat_index = Arr::get($this->data, "js_vars.list_vat_indexes.$payplan_nr.$product_nr");

                $product = [
                    'is_voucher' => (bool) $voucher_value,
                    'vat_rate' => Arr::get($this->data, "js_vars.list_vat_rates.$vat_index"),
                ];

                $devider = Arr::get($this->data, "js_vars.price_mode") == 'netto' ? 1 : (1 + $product['vat_rate'] / 100);

                if ($product_nr > 0) {
                    data_fill($this->data, "$addon.first_amount",
                        Arr::get($this->data, "$addon.single_amount", 0)
                    );

                    data_fill($this->data, "$addon.other_amounts", 0);
                    data_fill($this->data, "$addon.discount_unit_prices", []);

                    Arr::set($product, "first_amount",
                        Arr::get($this->data, "$addon.first_amount") / $devider
                    );

                    Arr::set($product, "other_amounts",
                        Arr::get($this->data, "$addon.other_amounts") / $devider
                    );

                    Arr::set($product, "discounts",
                        Arr::get($this->data, "$addon.discount_unit_prices", [])
                    );
                } else {
                    Arr::set($product, "first_amount",
                        Arr::get($this->data, "js_vars.payplan_first_amounts.$payplan_nr") / $devider
                    );

                    Arr::set($product, "other_amounts",
                        Arr::get($this->data, "js_vars.payplan_other_amounts.$payplan_nr") / $devider
                    );

                    Arr::set($product, "discounts",
                        Arr::get($this->data, "js_vars.payplan_discount_unit_prices.$payplan_id", [])
                    );
                }

                Arr::set($product, "discounts",
                    collect(Arr::get($product, "discounts", []))->map(function($item, $i) use ($devider) {
                        $item['unit_price_1st'] = $item['unit_price_1st'] / $devider;
                        $item['unit_price_oth'] = $item['unit_price_oth'] / $devider;
                        return $item;
                    })->all()
                );

                Arr::set($product, "has_discount",
                    count(Arr::get($product, "discounts")) > 0
                );

                $products[] = $product;
            }

            $this->output['payment_plans'][] = [
                'payplan_id' => $payplan_id,
                'number_of_installments' => Arr::get($this->data, "js_vars.payplan_number_of_installments.$payplan_nr"),
                'billing_type' => Arr::get($this->data, "js_vars.payplan_billing_types.$payplan_nr"),
                'first_billing_interval' => Arr::get($this->data, "pp_txt.$payplan_id.first_billing_interval"),
                'other_billing_intervals' => Arr::get($this->data, "pp_txt.$payplan_id.other_billing_intervals"),
                'test_phase' => Arr::get($this->data, "js_vars.payplan_has_test_phase.$payplan_nr"),
                'products' => $products,
            ];
        }
    }

    private function setMessages(): void
    {

    }

    private function fillJsVars(): int
    {
        $js_txt = crawler_results($this->fetcher, 'script', true);

        $desired_vars = [
            'buyer_type' => [],
            'buy_ordeform_layout' => [],
            'can_preselect_payplan' => [],
            'currency_code' => [],
            'DS24_AFFILIATE' => [],
            'have_payplan_choice' => [],
            'language' => [],
            'list_max_quantity_as_main_product' => [],
            'list_max_quantity' => [],
            'list_min_quantity' => [],
            'list_payment_plan_addons' => [],
            'list_product_ids' => [],
            'list_vat_indexes' => [],
            'list_vat_labels' => [],
            'list_vat_rates' => [],
            'main_product_id' => [],
            'main_product_index' => [],
            'max_amount' => [],
            'payplan_billing_types' => [],
            'payplan_discount_unit_prices' => [],
            'payplan_first_amounts' => [],
            'payplan_has_test_phase' => [],
            'payplan_ids' => [],
            'payplan_number_of_installments' => [],
            'payplan_other_amounts' => [],
            'price_mode' => [],
            'SHIPPING_COST' => [
                'Versand:' => 'Versand',
            ],
            'zero_payment_modes' => [],
        ];

        $this->data['js_vars'] = $this->extractJsVars($js_txt, $desired_vars);

        return count($this->data['js_vars']);
    }

    private function fillPpTxt(): int
    {
        $this->data['pptxt'] = [];

        $this->fetcher->filter('div[id^="pptxt"]')->each(function($node, $i) {
            [, $payment_plan, $var_name] = explode('_', $node->attr('id'), 3);
            $this->data['pptxt'][$payment_plan][$var_name] = $node->text('');
        });

        return count($this->data['pptxt']);
    }
}
