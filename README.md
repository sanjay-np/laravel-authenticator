# Laravel Authenticator

A comprehensive Laravel package for TOTP (Time-based One-Time Password) authentication with QR code scanning capabilities. This package provides Google Authenticator-like functionality with the ability to generate TOTP codes, create QR codes for easy setup, and verify codes through both Blade shortcodes and direct API calls.

## Features

- 🔐 **TOTP Generation & Verification**: Generate and verify time-based one-time passwords
- 📱 **QR Code Support**: Generate QR codes for easy setup with authenticator apps
- 🔍 **QR Code Parsing**: Parse and extract TOTP parameters from QR code content
- 🎨 **Blade Shortcodes**: Display TOTP codes directly in Blade templates
- ⚙️ **Configurable**: Fully customizable settings for periods, digits, algorithms
- 🔒 **Secure**: Built with industry-standard TOTP libraries

## Installation

Install the package via Composer:

```bash
composer require sanjay-np/laravel-authenticator
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="authenticator-config"
```

## Configuration

The configuration file `config/authenticator.php` allows you to customize:

```php
return [
    'totp' => [
        'period' => 60,        // Time period in seconds
        'digits' => 6,         // Number of digits in code
        'algorithm' => 'sha1', // Hash algorithm
        'window' => 1,         // Validation window
    ],
    'qr_code' => [
        'size' => 200,         // QR code size in pixels
        'margin' => 10,        // QR code margin
        'format' => 'png',     // Format: png or svg
    ],
    'shortcodes' => [
        'enabled' => true,
        'tag' => 'totp',
    ],
];
```

## Usage

### Basic TOTP Operations

```php
use LaravelAuthenticator\Facades\LaravelAuthenticator;

// Generate a new secret
$secret = LaravelAuthenticator::generateSecret();

// Get current TOTP code
$code = LaravelAuthenticator::getCurrentCode($secret);

// Verify a TOTP code
$isValid = LaravelAuthenticator::verifyCode($secret, '123456');

// Generate QR code
$qrCode = LaravelAuthenticator::generateQrCode($secret, 'user@example.com', 'MyApp');

// Parse QR code content
$params = LaravelAuthenticator::parseQrCode('otpauth://totp/MyApp:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=MyApp');

// Get provisioning URI
$uri = LaravelAuthenticator::getProvisioningUri($secret, 'user@example.com', 'MyApp');
```

### Blade Shortcodes

Display TOTP codes directly in your Blade templates using the shortcode service:

```blade
{{-- Display TOTP code with secret ID --}}
@totp(secret_id="1" display="code" show_timer="true")

{{-- Display QR code --}}
@totp(secret_id="1" display="qr")

{{-- Display both code and QR code --}}
@totp(secret_id="1" display="both" show_timer="true")

{{-- Minimal display --}}
@totp(secret_id="1" display="minimal")
```

### Shortcode Options

- `secret_id`: The ID of the stored secret
- `display`: Display type (`code`, `qr`, `both`, `minimal`)
- `show_timer`: Show expiration timer (`true`/`false`)
- `format`: Display format (`default`)
- `size`: Size (`small`, `medium`, `large`)
- `refresh`: Refresh mode (`manual`, `auto`)

### Advanced Usage

#### Working with TOTP Objects

```php
use LaravelAuthenticator\Facades\LaravelAuthenticator;

// Create a TOTP instance with custom settings
$totp = LaravelAuthenticator::createTotp($secret, 'user@example.com', 'MyApp');

// Generate QR code with different formats
$pngQrCode = LaravelAuthenticator::generateQrCode($secret, 'user@example.com', 'MyApp', 'png');
$svgQrCode = LaravelAuthenticator::generateQrCode($secret, 'user@example.com', 'MyApp', 'svg');

// Parse QR code content to extract parameters
$qrContent = 'otpauth://totp/MyApp:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=MyApp&period=30&digits=6';
$params = LaravelAuthenticator::parseQrCode($qrContent);
// Returns: ['secret' => 'JBSWY3DPEHPK3PXP', 'label' => 'MyApp:user@example.com', 'issuer' => 'MyApp', ...]
```

#### Integration with Your Application

```php
// In your controller
class TwoFactorController extends Controller
{
    public function setup(Request $request)
    {
        $user = $request->user();
        
        // Generate a new secret for the user
        $secret = LaravelAuthenticator::generateSecret();
        
        // Store the secret (you'll need to implement storage)
        $user->update(['totp_secret' => encrypt($secret)]);
        
        // Generate QR code for setup
        $qrCode = LaravelAuthenticator::generateQrCode(
            $secret, 
            $user->email, 
            config('app.name')
        );
        
        return view('auth.two-factor-setup', compact('qrCode', 'secret'));
    }
    
    public function verify(Request $request)
    {
        $user = $request->user();
        $code = $request->input('code');
        
        // Get the user's secret
        $secret = decrypt($user->totp_secret);
        
        // Verify the code
        $isValid = LaravelAuthenticator::verifyCode($secret, $code);
        
        if ($isValid) {
            // Code is valid, proceed with authentication
            return redirect()->intended();
        }
        
        return back()->withErrors(['code' => 'Invalid authentication code']);
    }
}
```

## Security Considerations

- **Secret Storage**: Always encrypt TOTP secrets when storing them in your database using Laravel's `encrypt()` function
- **HTTPS**: Always use HTTPS in production to protect sensitive data transmission
- **Rate Limiting**: Implement rate limiting on TOTP verification endpoints to prevent brute force attacks
- **Secret Rotation**: Consider implementing secret rotation policies for enhanced security
- **Validation Window**: Use appropriate validation windows to balance security and usability
- **Backup Codes**: Consider implementing backup codes for account recovery

## Testing

Run the package tests:

```bash
composer test
```

## Requirements

- PHP ^8.3
- Laravel ^10.0||^11.0||^12.0

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## Security Vulnerabilities

If you discover a security vulnerability, please send an e-mail to [excelblade10@gmail.com](mailto:excelblade10@gmail.com).

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Credits

- **Author**: [Sanjay Chaudhary](https://github.com/sanjay-np)
- Built with [Spatie Laravel Package Tools](https://github.com/spatie/laravel-package-tools)
- Uses [OTPHP](https://github.com/Spomky-Labs/otphp) for TOTP generation
- Uses [Endroid QR Code](https://github.com/endroid/qr-code) for QR code generation
- Uses [Bacon QR Code](https://github.com/Bacon/BaconQrCode) for QR code reading
