<?php

namespace Northlands\Permuteseq;

use InvalidArgumentException;

/**
 * @see https://github.com/dverite/permuteseq/blob/dcff6e91eaebe69e3ee9b77e3cf00d6f66edb425/permuteseq.c
 */
class Permuteseq
{
    protected int $key;

    protected int $min;

    protected int $max;

    protected int $rounds;

    /**
     * @param int $key
     * @param int $min
     * @param int $max
     * @param int $rounds Number of rounds of the Feistel Network. Must be an odd integer greater or equal to 3.
     */
    final public function __construct(int $key, int $min = 0, int $max = PHP_INT_MAX, int $rounds = 7)
    {
        if ($key < 2_147_483_648) {
            throw new InvalidArgumentException("Key must be 64-bit integer.");
        }

        if (! $this->hasValidRange($min, $max)) {
            throw new InvalidArgumentException("Invalid range: The difference between minimum and maximum values should be at least 3.");
        }

        if ($rounds < 3) {
            throw new InvalidArgumentException("Must be an odd integer greater or equal to 3.");
        }

        /**
         * Scramble the key. This is not strictly necessary, but will
         * help if the user-supplied key is weak, for instance with only a
         * few right-most bits set.
         *
         * The dverite/permuteseq extension supports 0xFFFFFFFFFFFFFFFF.
         * This project cannot be fully compatible without GMP or BCMath extensions.
         */
        $key = ($this->hash($key & 0xFFFFFFFF) | ($this->hash($key >> 32) & 0xFFFFFFFF) << 32) & 0x7FFFFFFFFFFFFFFF;

        $this->key = $key;
        $this->min = $min;
        $this->max = $max;
        $this->rounds = $rounds;
    }

    public static function create(int $key, int $min = 0, int $max = PHP_INT_MAX, int $rounds = 7): static
    {
        return new static($key, $min, $max, $rounds);
    }

    /**
     * The output is constrained to the boundaries of the range by
     * using a cycle-walking cipher on top of a Feistel network.
     *
     * @param $value
     * @param bool $reverse
     * @return int
     */
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
                $ki = $this->key >> ($hsz * ($reverse ? $this->rounds - 1 - $i : $i) & 0x3f);
                $ki &= 0xFFFFFFFF;

                $ki += ($reverse ? $this->rounds - 1 - $i : $i);
                $ki &= 0xFFFFFFFF;

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

    public function encode($value): int
    {
        return $this->permute($value);
    }

    public function decode($value): int
    {
        return $this->permute($value, true);
    }

    protected function hasValidRange($min, $max): bool
    {
        if (($min > 0 && $max < PHP_INT_MIN + $min) || ($min < 0 && $max > PHP_INT_MAX + $min)) {
            return true;
        }

        return $max - $min >= 4 - 1;
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
        $c &= 0xFFFFFFFF;

        $a ^= $c; $a -= $this->rot($c, 11);
        $a &= 0xFFFFFFFF;

        $b ^= $a; $b -= $this->rot($a, 25);
        $b &= 0xFFFFFFFF;

        $c ^= $b; $c -= $this->rot($b, 16);
        $c &= 0xFFFFFFFF;

        $a ^= $c; $a -= $this->rot($c, 4);
        $a &= 0xFFFFFFFF;

        $b ^= $a; $b -= $this->rot($a, 14);
        $b &= 0xFFFFFFFF;

        $c ^= $b; $c -= $this->rot($b, 24);
        $c &= 0xFFFFFFFF;

        return (int) $c;
    }

    /**
     * @see https://doxygen.postgresql.org/hashfn_8c.html#aae44e21ada356d9d84450d5440fbb0c4
     */
    protected function rot($x, $k): int
    {
        return ($x << $k) | ($x >> (32 - $k));
    }
}
