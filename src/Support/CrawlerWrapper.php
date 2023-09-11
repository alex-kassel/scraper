<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Support;

use Symfony\Component\DomCrawler\Crawler;

class CrawlerWrapper
{
    private Crawler $crawler;

    public function __construct(mixed $mixed)
    {
        if ($mixed instanceof Crawler) {
            $this->crawler = $mixed;
        } else {
            if ($mixed instanceof $this) {
                $mixed = $mixed->html('');
            }

            $this->crawler = new Crawler($mixed);
        }
    }

    public function attr(string $attr, ?string $default = null): ?string
    {
        return $this->crawler->count() ? $this->crawler->attr($attr) : $default;
    }

    public function __call(string $name, array $args)
    {
        $result = $this->crawler->$name(...$args);

        if ($result instanceof Crawler) {
            return new static($result);
        }

        return $result;
    }
}
