<?php

declare(strict_types=1);

namespace AlexKassel\Scraper\Support;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\BrowserKit\Response;
use AlexKassel\Scraper\Support\CrawlerWrapper;

class Fetcher
{
    protected HttpBrowser $browser;
    protected Response $response;
    protected CrawlerWrapper $crawler;
    protected ?object $json;

    public function __construct(string $url = '', array|object $data = []) {
        $this->initBrowser();
        if ($url) $this->url($url, $data);
    }

    public function url(string $url, array|object $data = [])
    {
        if ($data) {
            $crawler = $this->browser->request('POST', $url, [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], json_encode($data));
        } else {
            $crawler = $this->browser->request('GET', $url);
        }


        $this->response = $this->browser->getResponse();
        $this->json = json_decode($this->response->getContent());
        $this->crawler = new CrawlerWrapper($this->json ? '' : $crawler);

        return $this;
    }

    public function initBrowser(): void
    {
        $this->browser = new HttpBrowser(HttpClient::create([
            'headers' => [
                'Accept-Language' => 'de,en-US;q=0.7,en;q=0.3',
            ],
        ]));
        $this->browser->followRedirects(false);
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getHeaders(): array
    {
        return $this->response->getHeaders();
    }

    public function getContent(): string
    {
        return $this->response->getContent();
    }

    public function getJson(): object
    {
        return $this->json ?? (object) [];
    }

    public function toArray(): array
    {
        return $this->json ? json_decode($this->response->getContent(), true) : [];
    }

    public function __call(string $name, array $arguments)
    {
        return $this->crawler->$name(...$arguments);
    }
}
