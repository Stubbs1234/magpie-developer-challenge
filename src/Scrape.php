<?php

namespace App;

require 'vendor/autoload.php';

use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

class Scrape
{
    private array $products = [];

    private array $pages = [];

    /**
     * @return void
     * @throws GuzzleException
     */
    public function run(): void
    {
        $document = ScrapeHelper::fetchDocument(sprintf('%s/smartphones', ScrapeHelper::BASE_URL));
        $pages = $document->filter('#products > #pages > div > a')->count();

        for ($page = 1; $page <= $pages; ++$page) {
            $this->pages[] = (string)$page;
        }

        foreach ($this->pages as $page)  {
            $this->setData($page);
        }

        $this->removeDuplicates();

        file_put_contents('output.json', json_encode($this->products, JSON_PRETTY_PRINT));
    }

    /**
     * @param mixed $page
     * @return void
     * @throws GuzzleException
     */
    private function setData(mixed $page): void
    {
        $document = ScrapeHelper::fetchDocument(sprintf('%s/smartphones/?page=%d', ScrapeHelper::BASE_URL, $page));

        $document->filter('#products > .flex')->children()->each(function ($node) {
            $title = $node->filter('div > h3')->text();
            $price = $this->getPrice($node->filter('div.my-8.block.text-center.text-lg'));
            $url = UriResolver::resolve($node->filter('div > img')->attr('src'), ScrapeHelper::BASE_URL);
            $capacity = $this->getCapacity($node->filter('div > h3 > span.product-capacity')->text());
            $status = $this->getAvailabilityStatus($node);
            $stockStatus = $this->getStockStatus($node);
            $shippingTax = $this->getProductShippingText($node);
            $shippingDate = $this->getProductShippingDate($node);
            $colours = $this->getProductColours($node);

            foreach ($colours as $colour) {
                $products = new Product(
                    title: $title,
                    price: $price,
                    imageUrl: $url,
                    capacityMB: $capacity,
                    color: $colour,
                    availabilityText: $status,
                    isAvailable: $stockStatus,
                    shippingText: $shippingTax,
                    shippingDate: $shippingDate,
                );

                $this->products[] = $products;
            }
        });
    }


    /**
     * @param mixed $price
     * @return float
     */
    private function getPrice(mixed $price): float
    {
        $price = str_replace('£', '', $price->text());

        return (float)$price;
    }

    /**
     * @param mixed $node
     * @return bool
     */
    private function getStockStatus(mixed $node): bool
    {

        $filtered = $node->filter('div div')->reduce(function (Crawler $avail_node): bool {
            return str_contains($avail_node->text(), 'Availability:');
        });

        if (str_contains($filtered->last()->text(), 'In Stock')) {
            return true;
        }

        return false;
    }

    /**
     * @param mixed $node
     * @return string
     */
    private function getAvailabilityStatus(mixed $node): string
    {
        $filtered = $node->filter('div div')->reduce(function (Crawler $avail_node): bool {
            return str_contains($avail_node->text(), 'Availability:');
        });

        return str_replace('Availability:' . ' ', '', $filtered->last()->text());
    }

    /**
     * @param mixed $node
     * @return string
     */
    private function getProductShippingText(mixed $node): string
    {
        $text = $node->filter('div div')->last()->text();

        if (
            str_contains($text, 'delivery') ||
            str_contains($text, 'Free Shipping') ||
            preg_match("#[0-9]+#", $text)
        ) {
            return $text;
        }

        return '';
    }

    /**
     * @param mixed $node
     * @return string
     */
    private function getProductShippingDate(mixed $node): string
    {
        $date = strtolower($node->filter('div div')->last()->text());

        if (str_contains($date, 'tomorrow')) {
            return date('Y-m-d', strtotime('tomorrow'));
        }

        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $date, $matches)) {
            $day = $matches[3];
            $month = $matches[2];
            $year = $matches[1];
            return $year . '-' . $month . '-' . $day;
        }

        if (preg_match('/(\d{1,2})(st|nd|rd|th)?\s([A-Za-z]+)\s(\d{4})/', $date, $matches)) {
            $day = $matches[1];
            $month = $matches[3];
            $year = $matches[4];
            return date('Y-m-d', strtotime($day . ' ' . $month . ' ' . $year));
        }

        return '';
    }

    /**
     * @param string $capacity
     * @return int
     */
    private function getCapacity(string $capacity): int
    {
        $capacity = strtolower($capacity);
        foreach (['gb' => 1000, 'mb' => 1] as $match => $value) {
            if (str_contains($capacity, $match)) {
                return (int)$capacity * $value;
            }
        }

        return 0;
    }

    /**
     * @param mixed $node
     * @return array
     */
    private function getProductColours(mixed $node): array
    {

        return $node->filter('img + div > div > div')
                ->children('span')
                ->each(function (Crawler $color) {
                    return $color->attr('data-colour');
                });
    }

    /**
     * @return void
     */
    private function removeDuplicates(): void
    {
        $this->products = array_filter($this->products, function ($product) {
            foreach ($this->products as $other) {

                if (($product->title == $other->title &&
                    $product->price == $other->price &&
                    $product->capacityMB == $other->capacityMB &&
                    $product->color == $other->color) && $product != $other) {
                    return false;
                }
            }
            return true;
        });

        $this->products = array_values($this->products);
    }

}

$scrape = new Scrape();
$scrape->run();
