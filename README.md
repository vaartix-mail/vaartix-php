# Vaartix PHP SDK

Official PHP SDK for [Vaartix](https://vaartix.com), the email API for Indian developers.

> Vaartix is in private beta. [Request early access](https://vaartix.com/#early-access).

```sh
composer require vaartix/vaartix-php
```

PHP 8.1 or newer, with the standard `curl` and `json` extensions. No other dependencies.

## Send an email

```php
use Vaartix\Vaartix;

$vaartix = new Vaartix(); // reads the VAARTIX_API_KEY environment variable

$email = $vaartix->emails->send([
    'from' => 'Acme <onboarding@acme.in>',
    'to' => ['priya@example.com'],
    'subject' => 'Hello from Vaartix',
    'html' => '<strong>It works!</strong>',
]);

echo $email['id']; // em_...
```

Keys can be `snake_case` (`reply_to`, `scheduled_at`) or the API's camelCase (`replyTo`,
`scheduledAt`). Responses are the API's JSON as arrays, exactly as in the
[API reference](https://vaartix.com/docs/).

```php
// Retry safely: the same key within 24 hours returns the first email instead of sending twice.
$vaartix->emails->send($email, idempotencyKey: "welcome-{$user->id}");

// Send later (up to 30 days ahead), and cancel if plans change.
$scheduled = $vaartix->emails->send([...$email, 'scheduled_at' => '2026-11-01T09:00:00+05:30']);
$vaartix->emails->cancel($scheduled['id']);

// Up to 100 emails in one request.
$vaartix->batch->send([$email1, $email2]);

// Look up an email and its delivery events, or list recent bounces.
$vaartix->emails->get('em_...');
$vaartix->emails->list(['status' => 'bounced', 'days' => 7]);

// Try it without a domain: test addresses simulate delivery, bounces and complaints.
$vaartix->emails->send([
    'from' => 'onboarding@test.vaartix.com',
    'to' => 'delivered@test.vaartix.com',
    'subject' => 'Hello',
    'text' => 'It works!',
]);
```

## Errors

API and network errors throw `Vaartix\Exceptions\VaartixException`:

```php
use Vaartix\Exceptions\VaartixException;

try {
    $vaartix->emails->send($email);
} catch (VaartixException $e) {
    echo $e->type, ' ', $e->statusCode, ' ', $e->getMessage(); // e.g. rate_limited 429 Too many requests...
}
```

`$e->type` is a stable code; the full list is in [Errors](https://vaartix.com/docs/concepts/errors/).
Network failures have `type === 'network_error'` and `statusCode === null`.

## Laravel

The package registers a `vaartix` mail driver automatically, so Laravel's `Mail` facade,
Mailables, notifications and queued mail all go through Vaartix.

```php
// .env
MAIL_MAILER=vaartix
MAIL_FROM_ADDRESS=noreply@acme.in   # on a domain verified in Vaartix
VAARTIX_API_KEY=vx_live_...
VAARTIX_WEBHOOK_SECRET=whsec_...

// config/services.php
'vaartix' => [
    'key' => env('VAARTIX_API_KEY'),
    'webhook_secret' => env('VAARTIX_WEBHOOK_SECRET'),
],

// config/mail.php, inside 'mailers'
'vaartix' => [
    'transport' => 'vaartix',
],
```

That's all. Existing code keeps working:

```php
Mail::to($user)->send(new OrderConfirmed($order));
```

Laravel's `->tag('welcome')` and `->metadata('order_id', 42)` become Vaartix tags, so you can
filter by them in the dashboard. `SentMessage::getMessageId()` returns the Vaartix email ID.

To call the API directly, inject the client; it's registered as a singleton from
`services.vaartix.key`:

```php
public function store(Request $request, \Vaartix\Vaartix $vaartix)
{
    $vaartix->emails->send([
        'from' => 'Acme <orders@acme.in>',
        'to' => $request->user()->email,
        'subject' => 'Your order is confirmed',
        'html' => view('emails.order-confirmed', ['order' => $order])->render(),
    ], idempotencyKey: "order-confirmed-{$order->id}");
}
```

**Symfony:** `Vaartix\Laravel\VaartixTransport` is a regular Symfony Mailer transport:
`new Mailer(new VaartixTransport(new Vaartix\Vaartix()))`.

## Webhooks

Check that a webhook request really came from Vaartix before trusting it. Pass the **raw** body.

```php
use Vaartix\Webhook;
use Vaartix\Exceptions\WebhookVerificationException;

// Plain PHP
try {
    $event = Webhook::verify(file_get_contents('php://input'), getallheaders(), getenv('VAARTIX_WEBHOOK_SECRET'));
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    exit;
}

// Laravel (exclude this route from CSRF protection)
Route::post('/webhooks/vaartix', function (Request $request) {
    try {
        $event = Webhook::verify($request->getContent(), $request->headers->all(), config('services.vaartix.webhook_secret'));
    } catch (WebhookVerificationException $e) {
        abort(400);
    }
    if ($event['type'] === 'email.bounced') {
        // stop emailing $event['data']['recipient']
    }
    return response()->noContent();
});
```

Signatures follow the [Standard Webhooks](https://www.standardwebhooks.com) spec. Manage endpoints
with `$vaartix->webhooks->create()`, `list()`, `get()`, `update()`, `remove()`, `rotateSecret()`,
`deliveries()` and `retryDelivery()`.

## Docs

Full API reference and guides: [vaartix.com/docs](https://vaartix.com/docs/).

## Contributing and security

Issues and pull requests are welcome: see [CONTRIBUTING.md](CONTRIBUTING.md). Please report
security problems privately as described in [SECURITY.md](SECURITY.md).

## License

MIT
