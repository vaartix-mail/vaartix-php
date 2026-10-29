# Releasing

Packagist reads releases from this repository's Git tags; there is no upload step and no token.

1. Update `VERSION` in `src/Vaartix.php` and move the "Unreleased" notes in `CHANGELOG.md` under
   the new version. Merge that to `main` through a pull request.
2. Create a GitHub release with the tag `v<version>` (for example `v0.2.0`) on `main`.
3. Packagist picks up the new tag within a minute (through the GitHub integration). Check
   https://packagist.org/packages/vaartix/vaartix-php.

## One-time setup (done once by a maintainer)

- packagist.org → log in with GitHub (the `vaartix-mail` account) → **Submit** →
  `https://github.com/vaartix-mail/vaartix-php`.
- Packagist then keeps the package updated automatically through its GitHub app. If a release
  doesn't appear, open the package page and click **Update**.
- Only you can create `v*` tags (tag ruleset), so only you can release.
