<?php

namespace App\Services\Maps\Places;

use RuntimeException;

/** An address service said its allowance is used up. $forToday is false when it is only a short "slow down". */
class PlaceLimitReached extends RuntimeException
{
    public function __construct(public bool $forToday = true)
    {
        parent::__construct($forToday ? 'Daily allowance used up' : 'Asked too quickly');
    }
}
