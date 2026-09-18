<?php

namespace App\Sources;

use Carbon\CarbonInterface;

final readonly class ItemData
{
    /**
     * @param  array<string, list<string>>|null  $entities
     */
    public function __construct(
        public string $url,
        public string $title,
        public string $kind = 'news',
        public ?string $excerpt = null,
        public ?string $publisher = null,
        public ?CarbonInterface $publishedAt = null,
        public ?string $language = null,
        public ?string $countryCode = null,
        public ?string $category = null,
        public ?int $severity = null,
        public bool $isCambodia = false,
        public ?array $entities = null,
        public ?string $author = null,
    ) {}
}
