# Changelog

## Unreleased

- **Safer request headers.** The idempotency key passed to `emails->send()` must now be 1 to 256 printable ASCII characters; anything else throws a `validation_error` before a request is made. The cURL transport also refuses any header containing a line break or NUL byte, so an untrusted value can never add extra headers to a request.

## 0.4.1

- `WebhookEvent` docs cover the `details` of `email.delivery_delayed` and `email.bounced`,
  including `diagnosticCode`: the receiving server's exact reply, such as Yahoo's TSS04 line.

## 0.4.0

- `email.opened` and `email.clicked` webhook events. New `Vaartix\WebhookEvent` class with a
  constant for every event type and `WebhookEvent::ALL`.
- Open and click tracking: `'tracking' => ['opens' => ..., 'clicks' => ...]` on `emails->send()`
  and on each email in `batch->send()`, overriding the sending domain's defaults.
- Laravel and Symfony: an `X-Vaartix-Tracking: opens=on, clicks=off` header on a message becomes
  the API's tracking setting (same format as the SMTP relay) and is not forwarded. An invalid value
  is rejected before sending.
- `domains->update($id, ['open_tracking' => ..., 'click_tracking' => ...])` changes a domain's
  tracking defaults.

## 0.3.0

- Symfony Mailer DSN support: `Vaartix\Symfony\VaartixTransportFactory` builds the transport
  from `MAILER_DSN=vaartix+api://YOUR_API_KEY@default`. Register it in a Symfony app with the
  `mailer.transport_factory` tag (see the README). Optional `?timeout=` in seconds.
- `VaartixTransport` accepts Symfony's event dispatcher and logger, so Mailer events and
  logging work with it.

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
