<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Services\Ds24;

use AlexKassel\Scraper\Services\Ds24\BaseProcessor;

class PgbProcessor extends BaseProcessor
{
    protected function dataPreparationSucceeded(): bool
    {
        if (! preg_match('~var order_data[= ]+(.*?);<\/script>~', $this->fetcher->getContent(), $matches)) {
            $this->output['status']['message'] = 'No order_data detected.';
            return false;
        }

        $this->data['order_data'][0] = json_decode($matches[1]);
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

    protected function setGlobals(): void
    {
        $this->output['global'] = [
            'lang' => $this->data['order_data'][0]->settings->global->language,
            'affiliate' => [
                'id' => $this->output['global']['affiliate']['id'] ?? 0,
                'name' => $this->data['order_data'][0]->settings->affiliate->name,
            ],
            'currency_code' => $this->data['order_data'][0]->settings->global->currency_code,
            'as_upgrade_only' => (function() {
                foreach($this->data['order_data'][0]->messages->errors as $error_index => $error_message) {
                    if(strpos(strtolower($error_message), 'upgrade')) {
                        unset($this->data['order_data'][0]->messages->errors[$error_index]);
                        return true;
                    }
                }

                return false;
            })(),
            'is_voucher_input_enabled' => $this->data['order_data'][0]->settings->global->is_voucher_input_enabled,
            'can_preselect_payplan' => $this->data['order_data'][0]->settings->config->can_preselect_payplan,
            'refund_days' => $this->data['order_data'][0]->settings->product->settings->refund_days,
        ] + $this->output['global'] ?? [];
    }

    protected function setProducts(): void
    {
        $this->output['products'] = [];

        foreach($this->data['order_data'][0]->settings->product->items as $item_index => $item_value) {
            $this->output['products'][] = [
                'product_id' => $item_value->product_id,
                'image' => $this->formatUrl($item_value->image_url),
                'headline' => $item_value->headline,
                'description' => $item_value->description,
                'is_optional_if_addons_present' => $item_value->is_optional_if_addons_present == 'Y',
                'min_quantity' => $item_value->quantity->min_value,
                'max_quantity' => $item_value->quantity->max_value,
                'has_discount' => $item_value->quantity->has_discount,
            ];
        }
    }

    protected function setPayments(): void
    {
        $this->output['payments'] = [];

        foreach($this->data['order_data'][0]->payment as $payment_index => $payment_value) {
            if($payment_provider_id = (int) $payment_value->payment_provider_id) {
                $this->output['payments'][] = [
                    'payment_provider_id' => $payment_provider_id,
                    'pay_method' => $payment_value->pay_method,
                    'headline' => $payment_value->icon_data->alt,
                ];
            }
        }
    }

    protected function setShippingCosts(): void
    {
        $this->output['shipping_costs'] = [];

        foreach($this->data['order_data'][0]->settings->product->items as $item_index => $item_value) {
            if(! $item_value->shipping_amounts->first->net) continue;

            $this->output['shipping_costs'][] = [
                'label' => $this->data['order_data'][0]->order_summary->shipping_cost_label,
                'for_product_ids' => $item_value->product_id,
                'first_amount' => $item_value->shipping_amounts->first->net,
                'other_amounts' => $item_value->shipping_amounts->oth->net,
                'is_forbidden' => $this->data['order_data'][0]->validation->errors_by_postname->country, // 293876
            ];
        }
    }

    protected function setPaymentPlans(): void
    {
        $this->output['payment_plans'] = collect($this->data['order_data'][0]->payment_plans)
            ->map(function ($payplan_value, $payplan_index) {
                $order_data = $this->getOrderData($payplan_index, $payplan_value->payment_plan_id);

                return [
                    'payplan_id' => $payplan_value->payment_plan_id,
                    'number_of_installments' => $payplan_value->number_of_installments,
                    'billing_type' => $payplan_value->billing_type,
                    'first_billing_interval' => $order_data->order_summary->payment_plan->first_billing_interval,
                    'other_billing_intervals' => $order_data->order_summary->payment_plan->other_billing_intervals,
                    'test_phase' => $order_data->order_summary->payment_plan->test_interval,
                    'products' => collect($order_data->settings->product->items)
                        ->map(function ($item_value, $item_index) use ($payplan_value){
                            return [
                                'is_voucher' => $item_value->have_value_voucher,
                                'vat_rate' => vat_rate(
                                    $item_value->unit_amounts->first->net,
                                    $item_value->unit_amounts->first->gross,
                                ),
                                'first_amount' => $item_value->unit_amounts->first->net,
                                'other_amounts' => $item_value->unit_amounts->oth->net,
                                'discounts' => $this->extractDiscounts(
                                    $payplan_value->quantity_discounts,
                                    $item_index
                                ),
                            ];
                        })->all(),
                ];
            })->all();
    }

    protected function setMessages(): void
    {
        foreach ($this->data['order_data'][0]->messages as $messages) {
            foreach ($messages as $message) {
                $this->output['status']['message'] = isset($this->output['status']['message'])
                    ? implode("\n", [$this->output['status']['message'], $message])
                    : $message
                ;
            }
        }
    }

    protected function getOrderData(int $payplan_index, string $payment_plan_id): object
    {
        if($payplan_index == 0) {
            $order_data = $this->data['order_data'][0];
        } else {
            $url = sprintf('%s/product/%s',
                $this->output['base_url'],
                $this->data['order_data'][0]->settings->product->items[0]->product_id
            );

            $data = [
                'execute' => [
                    'action' => 'select',
                ],
                'payment_plan_id' => $payment_plan_id,
            ];

            [$response, ] = fetch_url($url, $data);

            $order_data = json_decode($response->getContent());
            $this->data['order_data'][$payplan_index] = $order_data;
        }

        return $order_data;
    }

    protected function extractDiscounts(array $quantity_discounts, int $item_index): array
    {
        if (! isset($quantity_discounts[$item_index])) return [];

        $subject = strip_tags($quantity_discounts[$item_index]->html);

        preg_match_all('/(\d+) \w+: ([\d,\.]+)/', $subject, $matches);

        for($i = 0; $i < count($matches[0]); $i++) {
            $discounts[] = [
                'from_quantity' => $matches[1][$i],
                'unit_price_1st' => str_replace(['.', ','], ['', '.'], $matches[2][$i]),
                'unit_price_oth' => 0,
            ];
        }

        return $discounts ?? [];
    }
}
