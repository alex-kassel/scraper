<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Contracts\MarketPlaceServiceInterface;

class ClickbankService implements MarketPlaceServiceInterface
{
    public function getApiData() : array
	{
		return [
			"marketplace" => [
				"sample" => env("APP_URL") . "/clickbank/marketplace/1/popularity/desc",
				"pattern" => "/clickbank/marketplace/{page}/{sort}/{order}/{keyword?}",
				"parameters" => [
					"page" => "[1-9][0-9]*",
					"sort" => "popularity|gravity",
					"order" => "asc|desc",
					"keyword" => "[0-9a-z_]*",
				],
			],

			"checkout" => [
				//
			],

			"search_suggestions" => [
				"endpoint" => env("APP_URL") . "/clickbank/call/search_suggestions",
			],
		];
	}
	
	public function getCallableMethods() : array
	{
		return [
			'search_suggestions' => 'fetchSearchSuggestions',
		];
	}

    public function fetchMarketplaceData($page = 1, $sort = 'popularity', $order = 'desc', $keyword = '') : array
    {
        foreach($this->getApiData()['marketplace']['parameters'] as $parameter => $pattern) {
			if(!preg_match(sprintf('~^%s$~', $pattern), ${$parameter})) {
				abort(404);
			}
		}

		$offset = ($page - 1) * 10;
		
		$url = 'https://new-api.clickbank.net/graphql';

        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:90.0) Gecko/20100101 Firefox/90.0',
            'Content-Type' => 'application/json',
        ])->post($url, [
            'query' => 'query ($parameters: MarketplaceSearchParameters!) {
                            marketplaceSearch(parameters: $parameters) {
                                totalHits
                                offset
                                hits {
                                    site
                                    title
                                    description
                                    favorite
                                    url
                                    marketplaceStats {
                                        activateDate
                                        category
                                        subCategory
                                        initialDollarsPerSale
                                        averageDollarsPerSale
                                        gravity
                                        totalRebill
                                        de
                                        en
                                        es
                                        fr
                                        it
                                        pt
                                        standard
                                        physical
                                        rebill
                                        upsell
                                        standardUrlPresent
                                        mobileEnabled
                                        whitelistVendor
                                        cpaVisible
                                        dollarTrial
                                        hasAdditionalSiteHoplinks
                                    }
                                    affiliateToolsUrl
                                    affiliateSupportEmail
                                    skypeName
                                }
                                facets {
                                    field
                                    buckets {
                                        value
                                        count
                                    }
                                }
                            }
                        }',
            'variables' => [
                'parameters' => [
					'includeKeywords' => str_replace('_', ' ', $keyword),
                    'offset' => $offset,
                    'sortDescending' => $order == 'desc',
                    'sortField' => $sort,
                ],
            ],
        ]);

        return $response->json();
    }
	
	public function fetchCheckoutData($productId) : array
	{
		return [];
	}
	
	public function fetchSearchSuggestions() : array
	{
		return Http::get('https://d27hxqjlrn9b4q.cloudfront.net/marketplace-search-suggestions.json')->json();
	}
}
