# Laravel Authenticator

A Laravel package for Time-based One-Time Password (TOTP) authentication with Blade shortcode support and optional QR code display.

## Features

- **TOTP generation and verification** using `spomky-labs/otphp`.
- **Encrypted secrets** stored via Laravel Crypt.
- **Per-client secrets** with active scope and last-used tracking.
- **Blade directive** `@totp([...])` to render current code with timer.
- **Configurable** period, digits, algorithm, window, QR settings.
- **Tested** with Pest + Orchestra Testbench.

## Requirements

- **PHP**: ^8.3
- **Laravel**: ^10 || ^11 || ^12

## Installation

1. Install via Composer:

```bash
composer require sanjay-np/laravel-authenticator
```

2. Publish only what you need (config or migration):

- Publish config only:

```bash
php artisan vendor:publish --tag=laravel-authenticator-config
```

- Publish migration only:

```bash
php artisan vendor:publish --tag=laravel-authenticator-migrations
```

This will place:
- **Config** at `config/authenticator.php`
- **Migration** (timestamped) under `database/migrations`

3. Run migrations:

```bash
php artisan migrate
```

## Configuration

Update `config/authenticator.php` as needed:

- **totp.period**: default 60 seconds
- **totp.digits**: default 6
- **totp.algorithm**: `sha1|sha256|sha512`
- **totp.window**: allowed drift windows for verification

## Usage

### Service API

```php
use LaravelAuthenticator\LaravelAuthenticator;

$auth = app(\LaravelAuthenticator\LaravelAuthenticator::class);

// Create a secret for a client (e.g., user id)
$record = $auth->generateClientSecret(clientId: 123, label: 'My Device');

// Get current TOTP code
$code = $auth->getCurrentCode($record->secret);

// Verify a code
$isValid = $auth->verifyCode($record->secret, $code);

// Verify by client id via model lookup
$isValid = $auth->verifyTotpCode(123, $code);

// Data for UI display (timer, progress, etc.)
$data = $auth->getClientTotpDisplayData($record->id);
```

### Facade

```php
use LaravelAuthenticator\Facades\LaravelAuthenticator;

$secret = LaravelAuthenticator::generateSecret();
```

### Blade Directive (Shortcode)

Render a live-updating code with a progress timer (auto-refresh via page reload each period):

```blade
@totp(['secret_id' => $secretId, 'display' => 'code', 'show_timer' => 'true'])
```

- **secret_id**: the `authenticator_secrets.id` value
- **display**: `code|qr|both|minimal` (currently `code` is implemented)
- **show_timer**: `true|false`

## Database

Table: `authenticator_secrets`

- **client_id**: bigint (application user id or similar)
- **secret**: encrypted string via `Crypt`
- **label, issuer, algorithm, digits, period, is_active, last_used_at**

A migration is provided and can be published using the commands above. Ensure APP_KEY is set.

## Testing

This package ships with Pest tests.

- Run all tests:

```bash
composer test
```

- With coverage:

```bash
composer test-coverage
```

## Security Considerations

- Secrets are encrypted at rest using Laravel Crypt.
- Ensure **APP_KEY** is configured in production.
- Consider rotating secrets if compromised and track `last_used_at`.
- Limit exposure of raw secrets; `getClientTotpDisplayData` hides secrets by default.

## Versioning & Release

- Follow **SemVer**: `MAJOR.MINOR.PATCH`.
- Add changes to a `CHANGELOG.md`.
- Tag releases in Git: `git tag vX.Y.Z && git push --tags`.
- Configure GitHub Actions (optional) to run tests and build on PRs.

### Suggested GitHub Actions Workflow (optional)

Create `.github/workflows/tests.yml`:

```yaml
name: tests
on: [push, pull_request]
jobs:
  pest:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: xdebug
      - run: composer install --no-interaction --prefer-dist
      - run: composer test-coverage
```

## License

MIT