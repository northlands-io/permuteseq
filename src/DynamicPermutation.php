<?php

namespace Northlands\Permuteseq;

use InvalidArgumentException;

class DynamicPermutation extends BasePermutation
{
    protected int $minlength;

    protected int $maxlength;

    public function __construct(int $minlength, int $maxlength = null, int $rounds = 7)
    {
        if ($minlength < 1) {
            throw new InvalidArgumentException("Minimum length must be 1 or greater.");
        }

        $this->minlength = $minlength;
        $this->maxlength = $maxlength;

        // TODO The $maxlength behave like an upper limit that won't expand.

        $this->rounds = $rounds;
    }

    public function permute($value, bool $reverse = false): int
    {
        if ($value < 0) {
            throw new InvalidArgumentException("Number must be positive.");
        }
        // TODO Adjust min/max based on input length

        $length = $value > 9 ? floor(log10($value + 1)) : 1;

        if ($length < $this->minlength) {
            $length = $this->minlength;
        }

        $this->min = pow(10, $length);
        $this->max = $this->min * 10 - 1;

        if (! $this->hasValidRange($this->min, $this->max)) {
            throw new InvalidArgumentException("Invalid range: The difference between minimum and maximum values should be at least 3.");
        }

        if ($reverse) {
            $value -= $this->min; // TODO Post permute?
        } else {
            $value += $this->min;
        }

        return parent::permute($value, $reverse);
    }
}
