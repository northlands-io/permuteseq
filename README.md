A small PHP library to obfuscate numbers. Use it when you don't want to expose your database numeric ids to users.

The permutations are collision-free and fully deterministic. The random-looking effect is due to encryption, not to a PRNG. The same boundary range with the same secret key will always produce the same output.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/northlands/permuteseq.svg?style=flat-square)](https://packagist.org/packages/northlands/permuteseq)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/northlands-io/permuteseq/run-tests?label=tests)](https://github.com/northlands-io/permuteseq/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/northlands/permuteseq.svg?style=flat-square)](https://packagist.org/packages/northlands/permuteseq)

## Installation

You can install the package via composer:

```bash
composer require northlands/permuteseq
```

## Usage

```php
$permuteseq = new Permuteseq(123456789012345, 1000, 9999); // Range 1000-9999

$encoded = $permuteseq->encode(1000); // 4070
$decoded = $permuteseq->decode($encoded); // 1000
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Disclaimer

The library should not be considered cryptographically strong or used to sensitive data.

## Credits

* https://github.com/dverite/permuteseq \
The PostgreSQL extension by Daniel Vérité.

* https://czep.net/21/obfuscate.html \
An article on how to obfuscate primary keys in databases.

## Alternatives

* https://github.com/ioleo/cryptomute \
Another Format Preserving Encryption library supporting multiple ciphers (DES, AES and Camellia) but slower performance.

* https://github.com/vinkla/hashids \
Generates YouTube-like ids from numbers. Can be limited to digit-alphabet, but returns strings like `"09284"` instead of safe integers.

* https://github.com/jenssegers/optimus \
Super-fast number obfuscation based on Knuth's integer hash. However, range can only be defined by 4-62 bits.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
