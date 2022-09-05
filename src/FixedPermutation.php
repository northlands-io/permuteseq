<?php

namespace Northlands\Permuteseq;

use InvalidArgumentException;

class FixedPermutation extends BasePermutation
{
    public function __construct(int $min, int $max, int $rounds = 7)
    {
        if (! $this->hasValidRange($min, $max)) {
            throw new InvalidArgumentException("Invalid range: The difference between minimum and maximum values should be at least 3.");
        }

        /*if ($rounds < 3) {
            throw new InvalidArgumentException("Must be an odd integer greater or equal to 3.");
        }*/

        $this->min = $min;
        $this->max = $max;

        $this->rounds = $rounds;

        // parent::__construct($)
    }
}
