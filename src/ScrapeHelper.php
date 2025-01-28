<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;

class ScrapeHelper
{
    const string BASE_URL = 'https://www.magpiehq.com/developer-challenge/';

    /**
     * @param string $url
     * @return Crawler
     * @throws GuzzleException
     */
    public static function fetchDocument(string $url): Crawler
    {
        $client = new Client();

        $response = $client->get($url);

        return new Crawler($response->getBody()->getContents(), $url);
    }
}
