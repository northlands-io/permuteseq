<?php

namespace Northlands\Permuteseq;

use Brick\Math\BigInteger;
use InvalidArgumentException;

/**
 * The allowed range with 64-bit support is -9223372036854775808 to +9223372036854775807.
 *
 * @see https://github.com/dverite/permuteseq/blob/dcff6e91eaebe69e3ee9b77e3cf00d6f66edb425/permuteseq.c
 */
class Permuteseq
{
    protected BigInteger $key;

    protected BigInteger $min;

    protected BigInteger $max;

    protected int $rounds;

    /**
     * @param int|\Brick\Math\BigNumber $key
     * @param int|\Brick\Math\BigNumber $min
     * @param int|\Brick\Math\BigNumber $max
     * @param int $rounds Number of rounds of the Feistel Network. Must be an odd integer greater or equal to 3.
     * @throws \Brick\Math\Exception\MathException
     */
    final public function __construct(mixed $key, mixed $min = PHP_INT_MIN, mixed $max = PHP_INT_MAX, int $rounds = 7)
    {
        $key = BigInteger::of($key);

        if ($key->isLessThan(PHP_INT_MIN) || $key->isGreaterThan(PHP_INT_MAX)) {
            throw new InvalidArgumentException(
                sprintf("The key must be in range %+d to %+d.", PHP_INT_MIN, PHP_INT_MAX)
            );
        }

        $min = BigInteger::of($min);

        if ($min->isLessThan(PHP_INT_MIN)) {
            throw new InvalidArgumentException(
                sprintf("Invalid range: The minimum value must be not be less than %+d.", PHP_INT_MIN)
            );
        }

        $max = BigInteger::of($max);

        if ($max->isGreaterThan(PHP_INT_MAX)) {
            throw new InvalidArgumentException(
                sprintf("Invalid range: The maximum value must not be greater than %+d.", PHP_INT_MAX)
            );
        }

        if (! $this->hasValidRange($min, $max)) {
            throw new InvalidArgumentException(
                "Invalid range: The difference between minimum and maximum values should be at least 3."
            );
        }

        if ($rounds < 3) {
            throw new InvalidArgumentException("Must be an odd integer greater or equal to 3.");
        }

        /**
         * Scramble the key. This is not strictly necessary, but will
         * help if the user-supplied key is weak, for instance with only a
         * few right-most bits set.
         */
        $key = BigInteger::of(
            $this->hash($key->and(4294967295)->toInt()) // 32 bit
        )->or(BigInteger::of(
            $this->hash($key->shiftedRight(32)->and(4294967295)->toInt()) // 32 bit
        )->shiftedLeft(32));

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
     * @param int $value
     * @param bool $reverse
     * @return int
     * @throws \Brick\Math\Exception\MathException
     */
    public function permute(int $value, bool $reverse = false): int
    {
        $value = BigInteger::of($value);

        if ($value->isLessThan($this->min) || $value->isGreaterThan($this->max)) {
            throw new InvalidArgumentException("Value out of range.");
        }

        /**
         * Arbitrary maximum number of "walks" along the results
         * searching for a value inside the [min,max] range.
         * It's mainly to avoid an infinite loop in case the chain of
         * results has a cycle (which would imply a bug somewhere).
         */
        $max = 1000000;

        $interval = $this->max->minus($this->min)->plus(1);

        $count = 0;

        /**
         * Compute the half block size: it's the smallest power of 2 such as two
         * blocks are greater than or equal to the size of interval in bits. The
         * half-blocks have equal lengths.
         */
        $hsz = 1;

        while ($hsz < 32 && BigInteger::of(1)->shiftedLeft(2*$hsz)->isLessThan($interval)) {
            $hsz++;
        }

        $mask = (1 << $hsz) - 1;

        /**
         * Initialize the two half blocks.
         * Work with the offset into the interval rather than the actual value.
         * This allows to use the full 32-bit range.
         */
        $l1 = ($value->minus($this->min))->shiftedRight($hsz)->toInt();
        $r1 = ($value->minus($this->min))->and($mask)->toInt();

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
                $ki = $this->key->shiftedRight(($hsz * ($reverse ? $this->rounds - 1 - $i : $i) & 0x3f));
                $ki = $ki->and(4294967295); // 32 bit

                $ki = $ki->plus($reverse ? $this->rounds - 1 - $i : $i);
                $ki = $ki->and(4294967295); // 32 bit

                $r2 = ($l1 ^ $this->hash($r1) ^ $this->hash($ki->toInt())) & $mask;

                $l1 = $l2;
                $r1 = $r2;
            }

            $result = BigInteger::of($r1)->shiftedLeft($hsz)->or($l1);

            // Swap one more time to prepare for the next cycle
            $l1 = $r2;
            $r1 = $l2;
        } while (($result->isLessThan(0) || $result->isGreaterThan($this->max->minus($this->min))) && $count++ < $max);

        if ($count >= $max) {
            throw new \RuntimeException("Infinite cycle walking detected.");
        }

        // Convert the offset in the interval to an absolute value, possibly negative.
        return $this->min->plus($result)->toInt();
    }

    public function encode($value): int
    {
        return $this->permute($value);
    }

    public function decode($value): int
    {
        return $this->permute($value, true);
    }

    /**
     * @throws \Brick\Math\Exception\MathException
     */
    protected function hasValidRange(BigInteger $min, BigInteger $max): bool
    {
        if ($min->isGreaterThan(0) && $max->isLessThan($min->plus(PHP_INT_MIN))) {
            return true;
        }

        if ($min->isLessThan(0) && $max->isGreaterThan($min->plus(PHP_INT_MAX))) {
            return true;
        }

        return $max->minus($min)->isGreaterThanOrEqualTo(3);
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

        return $c;
    }

    /**
     * @see https://doxygen.postgresql.org/hashfn_8c.html#aae44e21ada356d9d84450d5440fbb0c4
     */
    protected function rot($x, $k): int
    {
        return ($x << $k) | ($x >> (32 - $k));
    }
}
