<?php

namespace Northlands\Permuteseq;

interface PermutationInterface
{
    public function permute($value, bool $reverse = false);
}
