<?php

declare(strict_types=1);

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\BrowserKit\Response;
use Symfony\Component\DomCrawler\Crawler;
use AlexKassel\Scraper\Support\CrawlerWrapper;
use AlexKassel\Scraper\Support\Fetcher;

function vat_rate(float $net, float $gross, int $precision = 1): float
{
    return $net ? round(100 * $gross / $net - 100, $precision) : 0;
}

function fetch_url(string $url, array $data = []): array {
    $client = HttpClient::create([
        'headers' => [
            'Accept-Language' => 'de,en-US;q=0.7,en;q=0.3',
        ],
    ]);

    $browser = new HttpBrowser($client);
    $browser->followRedirects(false);

   if ($data) {
       $crawler = $browser->request('POST', $url, [], [], [
           'CONTENT_TYPE' => 'application/json',
       ], json_encode($data));
   } else {
       $crawler = $browser->request('GET', $url);
   }

    $response = $browser->getResponse();

    return [$response, $crawler];
}
/*
function webhook(): Response
{
    // Erstelle einen HttpClient
    $httpClient = HttpClient::create();

    // Definiere die URL des Webhooks
    $webhookUrl = 'https://example.com/webhook';

    // Definiere die Daten, die du an den Webhook senden möchtest
    $data = [
        'key' => 'value',
        'another_key' => 'another_value',
    ];

    // Sende die HTTP POST-Anfrage an den Webhook
    $response = $httpClient->request('POST', $webhookUrl, [
        'json' => $data, // Hier kannst du auch 'body' verwenden, um den JSON-String manuell zu erstellen
    ]);

    // Überprüfe die Antwort des Webhooks
    if ($response->getStatusCode() === 200) {
        return $this->json(['message' => 'Webhook erfolgreich gesendet']);
    } else {
        return $this->json(['message' => 'Fehler beim Senden des Webhooks'], 500);
    }
}
*/
function crawler_results(
    Crawler|Fetcher $crawler,
    string $selector,
    bool $as_string = false,
    string $separator = "\n\n"
): array|string {
    if (preg_match('~:attr\((\w+)\)$~', $selector, $matches)) {
        $attr = $matches[1];
        $selector = substr($selector, 0, strpos($selector, ':attr'));
    } else {
        $attr = null;
    }

    $array = array_filter(
        $crawler->filter($selector)
            ->each(function ($node, $i) use ($attr): string|null {
                return $attr ? $node->attr($attr) : $node->text('');
            })
    );

    return $as_string ? implode($separator, $array) : $array;
}

function exec_time(float $start_time_float = 0, int $precision = 3): float {
    return round(
        microtime(true) - ($start_time_float ?: $_SERVER['REQUEST_TIME_FLOAT']),
        $precision
    );
}

/**
 * Exactly the same as array_filter except this function
 * filters within multidimensional arrays
 * https://gist.github.com/benjamw/1690140
 *
 * @param array $array
 * @param callable|null $callback optional filter callback function
 * @param bool $remove_empty_arrays optional flag removal of empty arrays after filtering
 *
 * @return array filtered array
 */
function array_filter_recursive(array $array, callable $callback = null, bool $remove_empty_arrays = false): array {
    foreach ($array as $key => & $value) { // mind the reference
        if (is_array($value)) {
            $value = call_user_func_array(__FUNCTION__, array($value, $callback, $remove_empty_arrays));
            if ($remove_empty_arrays && ! $value) {
                unset($array[$key]);
            }
        } elseif (is_null($callback)) {
            if (in_array($value, ['', null], true)) {
                unset($array[$key]);
            }
        } elseif (! $callback($value, $key)) {
            unset($array[$key]);
        }
    }
    unset($value); // kill the reference

    return $array;
}

function or_null(string $type, mixed $mixed): mixed {
    if (is_null($mixed)) return null;

    [$type, $precision] = explode(':', $type . ':');

    return match ($type) {
        'int' => (int) $mixed,
        'float' => round((float) $mixed, (int) $precision),
        'string' => (string) $mixed,
        'array' => (array) $mixed,
        default => throw new \InvalidArgumentException("Type '$type' is not supported."),
    };
}

function format_url(?string $url, string $base_url = '', mixed $mixed = null): ?string
{
    if (is_null($url)) {
        return null;
    }

    if (isset($mixed['regex']) && is_string($mixed['regex'])) {
        $regex = $mixed['regex'];
    } elseif (is_string($mixed)) {
        $regex = $mixed;
    } else {
        $regex = '';
    }

    if ($regex === '' || ! preg_match(sprintf('~%s~i', $regex), $url)) {
        return null;
    }

    if (substr($url, 0, 4) !== 'http') {
        $url = sprintf('%s/%s', rtrim($base_url, '/'), ltrim($url, '/'));
    }

    if (isset($mixed['replace']['search']) && isset($mixed['replace']['replace'])) {
        $url = str_replace($mixed['replace']['search'], $mixed['replace']['replace'], $url);
    }

    return $url;
}

function crawler(mixed $crawler): CrawlerWrapper
{
    return new CrawlerWrapper($crawler);
}

function time_from_string(string $string): int
{
    $string = strtolower($string);

    if($output = strtotime($string)) {
        return $output;
    }

    $replace = [
        [
            'vor ', 'einem', 'einer', 'zwei', 'drei', 'vier', 'fünf', 'sechs', 'sieben', 'acht', 'neun', 'zehn', 'elf', 'zwölf',
            'jahren', 'jahr', 'monaten', 'monat', 'wochen', 'woche', 'tagen', 'tag',
            'stunden', 'stunde', 'minuten', 'minute', 'sekunden', 'sekunde',

            'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve'
        ],[
            '-', '1', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12',
            'years', 'year', 'months', 'month', 'weeks', 'week', 'days', 'day',
            'hours', 'hour', 'minutes', 'minute', 'seconds', 'second',

            '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'
        ]
    ];

    $replace = [
        'vor' => '-',
        'einem' => '1',
        'einer' => '1',
        'zwei' => '2',
        'drei' => '3',
        'vier' => '4',
        'fünf' => '5',
        'sechs' => '6',
        'sieben' => '7',
        'acht' => '8',
        'neun' => '9',
        'zehn' => '10',
        'elf' => '11',
        'zwölf' => '12',
        'jahren' => 'years',
        'jahr' => 'year',
        'monaten' => 'months',
        'monat' => 'month',
        'wochen' => 'weeks',
        'woche' => 'week',
        'tagen' => 'days',
        'tag' => 'day',
        'stunden' => 'hours',
        'stunde' => 'hour',
        'minuten' => 'minutes',
        'minute' => 'minute',
        'sekunden' => 'seconds',
        'sekunde' => 'second',
        'one' => '1',
        'two' => '2',
        'three' => '3',
        'four' => '4',
        'five' => '5',
        'six' => '6',
        'seven' => '7',
        'eight' => '8',
        'nine' => '9',
        'ten' => '10',
        'eleven' => '11',
        'twelve' => '12',
    ];

    #return strtotime(str_replace($replace[0], $replace[1], $string));
    return strtotime(str_replace(array_keys($replace), array_values($replace), $string));
}

# dd($t = time_from_string('vor 15 Minuten'), date('Y.m.d H:i:s', $t));
