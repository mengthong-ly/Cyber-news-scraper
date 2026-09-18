<?php

namespace App\Sources;

use App\Models\Source;

interface SourceAdapter
{
    /**
     * Fetch new items from the source. Throw on failure so the source is marked unhealthy.
     *
     * @return iterable<ItemData>
     */
    public function fetch(Source $source): iterable;
}
