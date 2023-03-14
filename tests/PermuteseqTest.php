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

it('throws exception with invalid secret key range', function () {
    new Northlands\Permuteseq\Permuteseq(PHP_INT_MAX + 1);
})->throws(InvalidArgumentException::class, "The key must be in range");

it('throws exception with invalid minimum range value', function () {
    new Northlands\Permuteseq\Permuteseq(123456789012345, PHP_INT_MIN - 1);
})->throws(InvalidArgumentException::class, "Invalid range: The minimum value");

it('throws exception with invalid maximum range value', function () {
    new Northlands\Permuteseq\Permuteseq(123456789012345, 0, PHP_INT_MAX + 1);
})->throws(InvalidArgumentException::class, "Invalid range: The maximum value");

it('throws exception with invalid range arguments', function () {
    new Northlands\Permuteseq\Permuteseq(123456789012345, 1, 3);
})->throws(InvalidArgumentException::class, "Invalid range: The difference between minimum and maximum values should be at least 3.");

it('throws exception with invalid rounds argument', function () {
    new Northlands\Permuteseq\Permuteseq(123456789012345, 0, 1000, 2);
})->throws(InvalidArgumentException::class, "Must be an odd integer greater or equal to 3.");

it('throws exception when input is out of range', function () {
    $permuteseq = new Northlands\Permuteseq\Permuteseq(123456789012345, 1000, 9999);

    $permuteseq->encode(10000);
})->throws(InvalidArgumentException::class, "Value out of range.");

it('supports 64-bit values', function () {
    $permuteseq = new Northlands\Permuteseq\Permuteseq(PHP_INT_MAX, PHP_INT_MIN, PHP_INT_MAX);

    $encoded = $permuteseq->encode(1);

    expect($encoded)->toEqual(-5372718357785807610)
        ->and(1)->toEqual($permuteseq->decode($encoded));

    $encoded = $permuteseq->encode(PHP_INT_MAX);

    expect($encoded)->toEqual(-1087206226205305280)
        ->and(PHP_INT_MAX)->toEqual($permuteseq->decode($encoded));
});

it('is compatible with postgres extension', function () {
    $tests = [
        // [min, max, key]
        [0, 100_000, 123456789012345],
        [-100_000, 100_000, 123456789012345],
        [0, PHP_INT_MAX, 123456789012345],
        [PHP_INT_MIN, 0, 123456789012345],
        [1_000, 9_999, 100],
    ];

    $pdo = new \PDO('pgsql:host=localhost;port=5432;dbname=postgres;', 'postgres', 'local');

    foreach ($tests as $test) {
        $min = $test[0];
        $max = $test[1];
        $key = $test[2];

        mt_srand(1);

        $permuteseq = new Northlands\Permuteseq\Permuteseq($key, $min, $max, 9);

        for ($i = 0; $i < 1000; $i++) {
            $value = mt_rand($min, $max);

            $stmt = $pdo->prepare('SELECT range_encrypt_element(:value, :min, :max, :key)');
            $stmt->execute(['value' => $value, 'min' => $min, 'max' => $max, 'key' => $key]);

            $expected = $stmt->fetch(\PDO::FETCH_OBJ)->range_encrypt_element;
            $actual = $permuteseq->encode($value);

            expect($expected)->toEqual($actual);
        }
    }
})->group('integration');
