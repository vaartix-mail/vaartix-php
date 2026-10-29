# Changelog

## Unreleased

## 0.2.0

- Laravel mail driver: set `MAIL_MAILER=vaartix` and Laravel's `Mail`, Mailables, notifications
  and queued mail go through Vaartix (Laravel 12 and 13). Registered automatically by package
  discovery.
- `Vaartix\Laravel\VaartixTransport` also works as a Symfony Mailer transport outside Laravel.
- Laravel's `->tag()` and `->metadata()` become Vaartix tags.

## 0.1.0

- First release: `emails->send`, `get`, `list`, `cancel`, `batch->send`, webhook endpoints, and
  `Webhook::verify()` for signed webhooks (Standard Webhooks).
- Idempotency keys, scheduled sending and test mode.
- PHP 8.1 or newer; no dependencies beyond the curl and json extensions.
