<?php

namespace Northlands\Permuteseq;

use InvalidArgumentException;

abstract class BasePermutation implements PermutationInterface
{
    protected int $key;

    protected int $min;

    protected int $max;

    protected int $rounds;

    /*public function __construct(int $key, int $rounds = 7)
    {
        $this->key = $key;
        $this->rounds = $rounds;
    }*/

    public function permute($value, bool $reverse = false): int
    {
        if ($value < $this->min || $value > $this->max) {
            throw new InvalidArgumentException("Value out of range.");
        }

        /**
         * Arbitrary maximum number of "walks" along the results
         * searching for a value inside the [min,max] range.
         * It's mainly to avoid an infinite loop in case the chain of
         * results has a cycle (which would imply a bug somewhere).
         */
        $max = 1000000;

        $interval = $this->max - $this->min + 1;

        $count = 0;

        /**
         * Compute the half block size: it's the smallest power of 2 such as two
         * blocks are greater than or equal to the size of interval in bits. The
         * half-blocks have equal lengths.
         */
        $hsz = 1;

        while ($hsz < 32 && 1 << (2*$hsz) < $interval) {
            $hsz++;
        }

        $mask = (1 << $hsz) - 1;

        /**
         * Scramble the key. This is not strictly necessary, but will
         * help if the user-supplied key is weak, for instance with only a
         * few right-most bits set.
         */
        $key = $this->hash($this->key & 0xffffffff) | ($this->hash($this->key >> 32) & 0xffffffff) << 32;

        /**
         * Initialize the two half blocks.
         * Work with the offset into the interval rather than the actual value.
         * This allows to use the full 32-bit range.
         */
        $l1 = ($value - $this->min) >> $hsz;
        $r1 = ($value - $this->min) & $mask;

        $l2 = $r2 = 0;

        do {
            for ($i = 0; $i < $this->rounds; $i++) {
                $l2 = $r1;

                /**
                 * The sub-key Ki for the round i is a sliding and cycling window
                 * of hsz bits over K, moving left to right, so each round takes
                 * different bits out of the crypt key. The round function is
                 * simply hash(Ri) XOR hash(Ki).
                 * When decrypting, Ki corresponds to the Kj of encryption with
                 * j=(NR-1-i), i.e. we iterate over sub-keys in the reverse order.
                 */
                $ki = $key >> ($hsz * ($reverse ? $this->rounds - 1 - $i : $i) & 0x3f);
                $ki += ($reverse ? $this->rounds - 1 - $i : $i);

                $r2 = ($l1 ^ $this->hash($r1) ^ $this->hash($ki)) & $mask;

                $l1 = $l2;
                $r1 = $r2;
            }

            $result = ($r1 << $hsz) | $l1;

            // Swap one more time to prepare for the next cycle
            $l1 = $r2;
            $r1 = $l2;
        } while (($result < 0 || $result > $this->max - $this->min) && $count++ < $max);

        if ($count >= $max) {
            throw new \RuntimeException("Infinite cycle walking detected.");
        }

        // Convert the offset in the interval to an absolute value, possibly negative.
        return $this->min + $result;
    }

    /**
     * @see https://doxygen.postgresql.org/hashfn_8c.html#a0e8a5084b019b55453fa64ac0329e73e
     * @see https://doxygen.postgresql.org/hashfn_8c.html#ab4646d77540701d2eb2c877effbe5739
     */
    protected function hash($k): int
    {
        $a = $b = $c = 0x9e3779b9 + 4 + 3923095;

        $a += $k;

        $c ^= $b; $c -= $this->rot($b, 14);
        $c &= 0xffffffff;

        $a ^= $c; $a -= $this->rot($c, 11);
        $a &= 0xffffffff;

        $b ^= $a; $b -= $this->rot($a, 25);
        $b &= 0xffffffff;

        $c ^= $b; $c -= $this->rot($b, 16);
        $c &= 0xffffffff;

        $a ^= $c; $a -= $this->rot($c, 4);
        $a &= 0xffffffff;

        $b ^= $a; $b -= $this->rot($a, 14);
        $b &= 0xffffffff;

        $c ^= $b; $c -= $this->rot($b, 24);

        return (int) $c & 0xffffffff;
    }

    /**
     * @see https://doxygen.postgresql.org/hashfn_8c.html#aae44e21ada356d9d84450d5440fbb0c4
     */
    protected function rot($x, $k): int
    {
        return ($x << $k) | ($x >> (32 - $k));
    }

    protected function hasValidRange($min, $max): bool
    {
        if (($min > 0 && $max < PHP_INT_MIN + $min) || ($min < 0 && $max > PHP_INT_MAX + $min)) {
            return true;
        }

        return $max - $min >= 4 - 1;
    }
}
