<?php

namespace App;

class Product
{

    public function __construct(public string $title,
                                public float $price,
                                public string $imageUrl,
                                public int $capacityMB,
                                public string $color,
                                public string $availabilityText,
                                public bool $isAvailable,
                                public string $shippingText,
                                public string $shippingDate)
    {
    }

}
