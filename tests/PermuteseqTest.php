<?php

it('can encode and decode random numbers', function () {
    $permuteseq = new Northlands\Permuteseq\Permuteseq(123456789012345, 0, 1000);

    for ($number = 0; $number < 1000; $number++) {
        $encoded = $permuteseq->encode($number);

        expect($number)->toEqual($permuteseq->decode($encoded));
    }
});

it('is collision-free', function () {
    $permuteseq = new Northlands\Permuteseq\Permuteseq(123456789012345, 0, 1000);

    $unique = [];

    for ($number = 0; $number < 1000; $number++) {
        $encoded = $permuteseq->encode($number);

        expect(array_key_exists($encoded, $unique))->toBeFalse();

        $unique[$encoded] = true;
    }
});

it('supports static constructor', function () {
    $permuteseq = Northlands\Permuteseq\Permuteseq::create(123456789012345);

    expect($permuteseq)->toBeInstanceOf(Northlands\Permuteseq\Permuteseq::class);
});

it('throws exception if not 64-bit secret key', function () {
    new Northlands\Permuteseq\Permuteseq(1234);
})->throws(InvalidArgumentException::class, "Key must be 64-bit integer.");

it('throws exception with invalid range arguments', function () {
    new Northlands\Permuteseq\Permuteseq(123456789012345, 1, 3);
})->throws(InvalidArgumentException::class, "Invalid range");

it('throws exception with invalid rounds argument', function () {
    new Northlands\Permuteseq\Permuteseq(123456789012345, 0, 1000, 2);
})->throws(InvalidArgumentException::class, "Must be an odd integer greater or equal to 3.");

it('throws exception when input is out of range', function () {
    $permuteseq = new Northlands\Permuteseq\Permuteseq(123456789012345, 1000, 9999);

    $permuteseq->encode(10000);
})->throws(InvalidArgumentException::class, "Value out of range.");
