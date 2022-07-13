A small PHP library to obfuscate numbers. Use it when you don't want to expose your database numeric ids to users.

It is similar to [Optimus](https://github.com/jenssegers/optimus), but supports *truly* custom numeric ranges. A secret 64-bit key can be used to generate several different outputs from the same input range.

## Disclaimer

The library should not be considered cryptographically strong and used to sensitive data. *This is not a true encryption algorithm.*

## Credits

* https://github.com/dverite/permuteseq \
The PostgreSQL extension by Daniel Vérité used as code porting.

* https://czep.net/21/obfuscate.html \
An article on how to obfuscate primary keys in databases.

## Alternatives

* https://github.com/ioleo/cryptomute \
Antoher Format Preserving Encryption library supporting multiple ciphers (DES, AES and Camellia) but slower performance.

* https://github.com/vinkla/hashids \
Generates YouTube-like ids from numbers. Can be limited to digit-alphabet, but returns strings like `"09284"` instead of safe integers.

* https://github.com/jenssegers/optimus \
Super-fast number obfuscation based on Knuth's integer hash. However, range can only be defined by 4-62 bits.

## License

Permutation is licensed under [The MIT License (MIT)](LICENSE).