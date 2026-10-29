# Contributing

Thanks for helping improve the Vaartix PHP SDK.

```sh
composer install
composer test       # PHPUnit
composer analyse    # PHPStan, level max
```

- Open an issue first for anything larger than a small fix, so we can agree on the approach.
- Keep the SDK free of runtime dependencies beyond PHP's curl and json extensions.
- Add a line to `CHANGELOG.md` under "Unreleased".
