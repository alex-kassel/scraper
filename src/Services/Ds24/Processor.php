<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Services\Ds24;

use AlexKassel\Scraper\Support\Fetcher;
use AlexKassel\Scraper\Support\ScraperInterface;
use AlexKassel\Scraper\Services\Ds24\BaseProcessor;
use AlexKassel\Scraper\Services\Ds24\MarketplaceDto;
use AlexKassel\Scraper\Services\Ds24\CheckoutDto;
use AlexKassel\Scraper\Services\Ds24\Ds24Trait;

class Processor implements ScraperInterface
{
    use Ds24Trait;

    public function getApiData(): array
    {
        return [
            'marketplace' => [
                'sample' => env('APP_URL') . '/ds24/marketplace/1/rank/de',
                'pattern' => '/ds24/marketplace/{page}/{sort}/{lang}/{keyword?}',
                'parameters' => [
                    'page' => '[1-9][0-9]*',
                    'sort' => 'rank|stars',
                    'lang' => 'de|en|fr|es|nl|it|pt|pl|sl',
                    'keyword' => '[0-9a-zA-Z_]*',
                ],
            ],

            'checkout' => [
                'sample' => env('APP_URL') . '/ds24/checkout/38219',
                'pattern' => '/ds24/checkout/{product_id}/{affiliate_id?}',
                'parameters' => [
                    'product_id' => '[1-9][0-9]*',
                    'affiliate_id' => '[0-9a-zA-Z_]*',
                ],
            ],

            'socialproof' => [
                'sample' => env('APP_URL') . '/ds24/call/socialproof/2081/MZ2oi8bVU4LKexc6Ne5wCXylDlxXRM',
                'pattern' => '/ds24/call/socialproof/{widget}/{token}',
                'parameters' => [
                    'widget' => '[0-9]+',
                    'token' => '[0-9a-zA-Z]{30}',
                ],
            ],
        ];
    }

    public function getCallableMethods(): array
	{
		return [
			'socialproof' => 'fetchSocialProof',
		];
	}

    public function fetchMarketplaceData($page = 1, $sort = 'rank', $lang = 'de', $keyword = ''): MarketplaceDto
    {
        $this->validateParameters(
            $this->getApiData()['marketplace']['parameters'],
            compact('page', 'sort', 'lang', 'keyword'),
        );

		$url = sprintf('%s/%s/home/marketplace/auto?page=%s&search_sort_by=%s&search_product=%s',
            $this->base_url, $lang, $page, $sort, str_replace('_', '+', $keyword)
        );

        $fetcher = new Fetcher($url);

		$output = [
            'base_url' => $this->base_url,
			'page' => 1,
			'pages' => 1,
			'itemsTotal' => (int) $fetcher->filter('.resultCount .count')->text(''),
        ];

		if($fetcher->filter('.pagination li')->count() > 0) {
			$output['page'] = (int) array_reverse(explode(' ', $fetcher->filter('.pagination li.active a')->attr('title')))[0];
			$output['pages'] = (int) array_reverse(explode(' ', $fetcher->filter('.pagination li:last-child a')->attr('title')))[0];
		}

		$order = ($output['page'] - 1) * 5;

		$output['items'] = $fetcher->filter('.product')->each(function($node) use($fetcher, &$order) {
			$item = [];
			$item['sort_order'] = ++$order;
			$item['product_id'] = explode('_', $node->filter('.promo_button')->attr('id'))[1];
			$item['name'] = explode('<', $node->filter('h3')->html())[0];
			$item['description'] = trim(preg_replace('/\s+/', ' ',  $node->filter('.productText')->html()));
			$item['image_url'] = $this->formatUrl($node->filter('.product_thumb')->attr('src'));
			$item['rating'] = trim(explode(':', $node->filter('.performance .staricon_container')->attr('title'))[1]);

			$rank = [];
			if($node->filter('span.shadow .with_tooltip_html_hover')->count() > 0) {
				$filter = sprintf('#%s p', $node->filter('span.shadow .with_tooltip_html_hover')->attr('data-html-id'));
				$rank = $fetcher->filter($filter)->each(function($node) {
					return $node->html();
				});
			}
			$item['rank'] = array_filter($rank);

			$data = [];

			$node->filter('.tags .shadow')->each(function($node) use(&$data) {
				list($key, $value) = explode(':', $node->text(), 2);
				$data[$this->translate($key)] = trim(str_replace('help', '', $value));
			});

            $item['data'] = $data;

			$links = [];
			$node->filter('.col-lg-3 .underline')->each(function($node) use(&$links) {
				$links[] = $node->attr('href');
			});

			$item['links'] = [
				'sales_page' => $links[0],
				'affiliate_support_page' => $links[1] ?? null,
			];

			return $item;
		});

		return $output;
    }

    public function fetchCheckoutData($product_id, $affiliate_id = ''): CheckoutDto
    {
        $this->validateParameters(
            $this->getApiData()['checkout']['parameters'],
            compact('product_id', 'affiliate_id'),
        );

        $url = sprintf('%s/product/%s?aff=%s', $this->base_url, $product_id, $affiliate_id);

        $fetcher = new Fetcher($url);

        $output = [
            'base_url' => $this->base_url,
            'status' => [
                'code' => $fetcher->getStatusCode(),
            ]
        ];

        switch ($output['status']['code']) {
            case 200:
                if(substr($fetcher->getContent(), 0, 3) == '<h1') {
                    $output['status']['message'] = strip_tags($fetcher->getContent());
                    break; // very rare
                }

                if(strpos($fetcher->getContent(), 'redirect_to_target_page()')) {
                    $output['status']['message'] = 'Sorry, the product is not available in your country.';
                    break; // 396593, 483329
                }

                $output += [
                    'global' => [
                        'lang' => $fetcher->filter('html')->attr('lang')
                            ?? (
                                preg_match('~language/([\w]+)/common~', $fetcher->getContent(), $matches)
                                    ? $matches[1]
                                    : null
                            ),
                        'title' => $fetcher->filter('title')->text(''),
                        'variant' => preg_match('~orderform type: (\w+)~', $fetcher->getContent(), $matches)
                            ? $matches[1]
                            : ''
                        ,
                        'merchant' => preg_match('/merchant_([0-9]+)/', $fetcher->getContent(), $matches)
                            ? $matches[1]
                            : ''
                        ,
                    ],
                    'opengraph' => $fetcher->filter('meta[property^="og:"]')->count() > 3
                        ? [
                            'image' => $this->formatUrl($fetcher->filter('meta[property="og:image"]')->attr('content')),
                            'title' => $fetcher->filter('meta[property="og:title"]')->attr('content'),
                            'description' => $fetcher->filter('meta[property="og:description"]')->attr('content'),
                        ]
                        : [],
                ];

                if ($error = $fetcher->filter('.error li')->text('')) {
                    $output['status']['message'] = $error;
                    break;
                }

                if (strpos($fetcher->getContent(), 'PGB_PUBLIC_PATH')) {
                    $output['global']['variant'] = 'pgb';
                }

                if (preg_match('~track/orderform/(\d+).+/initial/(\d+)~', $fetcher->getContent(), $matches)) {
                    $output['global']['merchant'] = $matches[1];
                    $output['global']['affiliate']['id'] = $matches[2];
                }

                if (preg_match('~[^"\']+24.com/socialproof/[^"\']+~', $fetcher->getContent(), $matches)) {
                    $output['assets']['socialproof'] = $matches[0];
                }

                if (preg_match_all('~[^"\']+/merchant_[^"\']+~', $fetcher->getContent(), $matches)) {
                    $images = str_replace('\\', '', $matches[0]);
                    $output['assets']['images'] = collect($images)->map(function($image) {
                        return $this->formatUrl($image);
                    })->all();
                }

                $processor = $this->getProcessor($output['global']['variant'], $fetcher);
                $output = $processor->expandOutput($output);

                $output['global']['affiliate']['default'] = ! in_array($affiliate_id, $output['global']['affiliate']);

                break;
            case 301:
            case 302:
                $output['status']['location'] = $fetcher->getHeaders()['location'][0];
                break;
            default:
                $output['status']['message'] = $fetcher->filter('#container')->text('');
        }

        $output['redir_info'] = $this->redirInfo($product_id);

        return new CheckoutDto($output);
	}

    public function fetchSocialProof(string $args): array
	{
        if (count($args = explode('/', $args)) !== 2) {
			abort(404);
        }

        [$widget, $token] = $args;

        $this->validateParameters(
            $this->getApiData()['socialproof']['parameters'],
            compact('widget', 'token'),
        );

		$url = sprintf('%s/socialproof/%s/%s/70/330/de.js', $this->base_url, $args[0], $args[1]);

        [$response, $crawler] = fetch_url($url);

        $body = $response->getContent();

		if(false === strpos($body, 'DS24_BUYER_LIST')) {
			return [
                'error' => trim($body, '/*. ')
            ];
		}

		$json = substr($body, $start = strpos($body, '['), strpos($body, ']') - $start + 1);
		$json = preg_replace('~(headline|message|footline|image_url): ~', '"$1": ', $json);
		$json = strip_tags($json);

        $json_decoded = json_decode($json);

        return [
            'DS24_BUYER_COUNT' => count($json_decoded),
            'DS24_BUYER_LIST' => $json_decoded,
        ];
	}

    private function redirInfo($product_id): array
    {
        $url = sprintf('%s/redir/%s', $this->base_url, $product_id);

        [$response, $crawler] = fetch_url($url);

        $output = [
            'status' => [
                'code' => $response->getStatusCode(),
            ],
        ];

        switch ($output['status']['code']) {
            case 301:
            case 302:
                $output['status']['location'] = $response->getHeaders()['location'][0];
                break;
            default:
                $output['status']['message'] = $crawler->filter('#container')->text('') ?? null;
        }

        return $output;
    }

    protected function validateParameters(array $parameter_rules, array $parameters): void
    {
        foreach($parameter_rules as $parameter => $pattern) {
            if (! preg_match(sprintf('~^%s$~', $pattern), $parameters[$parameter])) {
                if (config('app.debug')) {
                    throw new \InvalidArgumentException(
                        sprintf("Parameter '%s' => '%s' don't match pattern '%s'",
                            $parameter, $parameters[$parameter], $pattern
                        )
                    );
                }

                abort(404);
            }
        }
    }

    protected function getProcessor(string $variant, Fetcher $fetcher): BaseProcessor
    {
        switch ($variant) {
            case 'pgb': return new PgbProcessor($fetcher);
            case 'responsive':
            case 'iframe':
            case 'classic': return new ClassicProcessor($fetcher);
            default: return new DefaultProcessor($fetcher);
        }
    }
}
