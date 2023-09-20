<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\MarketPlaces\MobileDe;

use AlexKassel\Fetcher\Fetcher;
use AlexKassel\Parser\Parser;

class ProductListScraper
{
    public static function scrape()
    {
        $url1 = 'http://api.scraperapi.com?api_key=1b2d29b104c19d6350cfb475eea07be0&url=';
        $url2 = 'https://suchen.mobile.de/fahrzeuge/search.html?dam=0&isSearchRequest=true&ref=quickSearch&sb=rel&vc=Car';
        $url = $url1.urlencode($url2);

        $md5 = md5($url);
        if (! $content = cache($md5)) {
            $content = $content = Fetcher::fetch($url)->getContent();
            cache([$md5 => $content], 48*60*60);
            $content = cache($md5);
        }
        #return $content;
        return Parser::parse($content, ['url' => $url2]);
    }
}
