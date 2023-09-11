<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Contracts\MarketPlaceServiceInterface;

class CopecartService implements MarketPlaceServiceInterface
{
    public function getApiData() : array
	{
		return [
			"marketplace" => [
				"sample" => env("APP_URL") . "/copecart/marketplace/1/date/desc",
				"pattern" => "/copecart/marketplace/{page}/{sort}/{order}/{keyword?}",
				"parameters" => [
					"page" => "[1-9][0-9]*",
					"sort" => "date|income|conversion_rate|commission_percentage|commission_amount",
					"order" => "asc|desc",
					"keyword" => "[0-9a-z_]*",
				],
			],
			
			"checkout" => [
				//
			],
			
			"product_pricing" => [
				"sample" => env("APP_URL") . "/copecart/call/product_pricing/1bda0b24/335223",
				"pattern" => "/copecart/call/product_pricing/{product_slug}/{payment_plan_id}",
				"parameters" => [
					"product_slug" => "[0-9a-f]{8}",
					"payment_plan_id" => "[0-9]+",
				],
			],
		];
	}
	
	public function getCallableMethods() : array
	{
		return [
			'product_pricing' => 'fetchProductPricing',
		];
	}

    public function fetchMarketplaceData($page = 1, $sort = 'date', $order = 'desc', $keyword = '') : array
    {
        foreach($this->getApiData()['marketplace']['parameters'] as $parameter => $pattern) {
			if(!preg_match(sprintf('~^%s$~', $pattern), ${$parameter})) {
				abort(404);
			}
		}
		
		$sort_option = sprintf('%s_%s', $sort, $order);
		$keyword = str_replace('_', '+', $keyword);
		
		$url = sprintf('https://www.copecart.com/marketplace.json?page=%s&sort_option=%s&q=%s', $page, $sort_option, $keyword);
		$response = Http::get($url);
		
		return $response->json();
    }
	
	public function fetchCheckoutData($productId) : array
	{
		// https://www.copecart.com/products/1bda0b24/checkout
		return [];
	}
	
	public function fetchProductPricing(string $args) : array
	{
		$args = explode('/', $args);
		
		if (count($args) != 2) {
			abort(404);
		}
		
		$parameters = $this->getApiData()['product_pricing']['parameters'];
		
		if (
			!preg_match(sprintf('~^%s$~', $parameters['product_slug']), $args[0]) ||
			!preg_match(sprintf('~^%s$~', $parameters['payment_plan_id']), $args[1])
		) {
			abort(404);
		}

		$data = [
		#	'country_code' => 'DE',
		#	'is_private_person' => 'true',
		#	'vat_number' => '',
			'product_info' => json_encode([
		#		'promocode_id' => '',
				'product_slug' => $args[0],
				'payment_plan_id' => intval($args[1]),
		#		'quantity' => 1,
			]),
		#	'addons_info' => json_encode([]),
		#	'address' => json_encode((object) []),
		];
		
		$url = 'https://www.copecart.com/products/pricing?' . http_build_query($data);
		$response = Http::get($url);

		return $response->json() ?? [];
	}
}
