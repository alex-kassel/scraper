<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Support;

use AlexKassel\Scraper\Support\DataTransferObject;

interface ScraperInterface
{
	public function getApiData(): array;
	public function getCallableMethods(): array;
    public function fetchMarketplaceData($page, $sort, $order, $keyword): DataTransferObject;
	public function fetchCheckoutData($productId): DataTransferObject;
}
