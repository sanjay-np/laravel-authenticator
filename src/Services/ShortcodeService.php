<?php

namespace LaravelAuthenticator\Services;

use Illuminate\Support\Facades\Log;
use LaravelAuthenticator\Facades\LaravelAuthenticator;

class ShortcodeService
{
    /**
     * Render shortcode from array of attributes
     */
    public function render(array $attributes)
    {
        // Parse the shortcode expression
        $attributes = $this->parseAttributes($attributes);

        // Validate required parameters
        if (!$this->validateAttributes($attributes)) {
            return $this->renderError('Invalid shortcode parameters');
        }

        try {
            // Get TOTP data based on shortcode type
            $data = $this->getTotpData($attributes);

            if (!$data) {
                return $this->renderError('Unable to load TOTP data');
            }

            // Render the shortcode
            return $this->renderShortcode($attributes, $data);
        } catch (\Exception $e) {
            return $this->renderError('Shortcode rendering failed: ' . $e->getMessage());
        }
    }

    /**
     * Parse shortcode attributes from expression
     */
    private function parseAttributes(array $attributes): array
    {
        $attributes = array_merge([
            'display' => 'code',
            'format' => 'default',
            'refresh' => 'auto',
            'show_timer' => 'true',
            'show_qr' => 'false',
            'size' => 'medium',
        ], $attributes);

        return $attributes;
    }

    /**
     * Validate shortcode attributes
     */
    private function validateAttributes(array $attributes): bool
    {
        // Must have either email, secret_id, or user parameter
        if (!isset($attributes['email']) && !isset($attributes['secret_id']) && !isset($attributes['user'])) {
            return false;
        }

        // Validate display options
        $validDisplays = ['code', 'qr', 'both', 'minimal'];
        if (isset($attributes['display']) && !in_array($attributes['display'], $validDisplays)) {
            return false;
        }

        return true;
    }

    /**
     * Get TOTP data based on shortcode attributes
     */
    private function getTotpData(array $attributes): ?array
    {
        if (isset($attributes['secret_id'])) {
            return $this->getTotpDataBySecretId((int) $attributes['secret_id']);
        }
        return null;
    }

    /**
     * Get TOTP data by secret ID
     */
    private function getTotpDataBySecretId(int $secretId): ?array
    {
        try {
            $data = LaravelAuthenticator::getClientTotpDisplayData($secretId, ['showCurrentCode' => true]);
            return array_merge($data, [
                'source' => 'verified_secrets',
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting TOTP data for secret ID ' . $secretId . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Render error message
     */
    private function renderError(string $message): string
    {
        return '<div class="laravel-verified-error">' . htmlspecialchars($message) . '</div>';
    }

    /**
     * Render the shortcode based on attributes and data
     */
    private function renderShortcode(array $attributes, array $data): string
    {
        $display = $attributes['display'];
        $format = $attributes['format'];
        $refresh = $attributes['refresh'];
        $showTimer = $attributes['show_timer'] === 'true';
        $size = $attributes['size'];
        /**
         *Generate unique ID for this shortcode instance
         *
         * */
        $instanceId = 'totp-' . uniqid();
        $html = '<div class="laravel-verified-shortcode ' . $format . ' ' . $size . '" id="' . $instanceId . '">';
        $html .= $this->getShortcodeStyles();
        switch ($display) {
            case 'code':
                $html .= $this->renderCodeDisplay($data, $showTimer, $refresh, $instanceId);
                break;
        }
        $html .= '</div>';
        // Add JavaScript for auto-refresh if enabled
        if ($refresh === 'auto') {
            $html .= $this->getAutoRefreshScript($instanceId, $data);
        }

        return $html;
    }

    /**
     * Render code display
     */
    private function renderCodeDisplay(array $data, bool $showTimer, string $refresh, string $instanceId): string
    {
        $code = $data['current_code'] ?? $data['currentCode'] ?? '--- ---';
        $expiresIn = $data['expires_in'] ?? $data['expiresIn'] ?? 0;
        $period = $data['period'] ?? 60;

        $html = '<div id="' . $instanceId . '" class="totp-code-display">';
        $html .= '<div class="totp-code">' . chunk_split($code, 3, ' ') . '</div>';

        if ($showTimer) {
            $progressPercentage = round((($period - $expiresIn) / $period) * 100, 2);
            $html .= '<div class="totp-timer">';
            $html .= '<div class="timer-bar"><div class="timer-progress" style="width: ' . $progressPercentage . '%"></div></div>';
            $html .= '<div class="timer-text">Expires in ' . $expiresIn . 's</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Get CSS styles for shortcodes
     */
    private function getShortcodeStyles(): string
    {
        static $stylesAdded = false;
        if ($stylesAdded) {
            return '';
        }
        $stylesAdded = true;
        return '<style>
            .laravel-verified-shortcode { margin: 10px 0; }
            .laravel-verified-shortcode.small { font-size: 0.8em; }
            .laravel-verified-shortcode.medium { font-size: 1em; }
            .laravel-verified-shortcode.large { font-size: 1.2em; }
            .totp-code-display { text-align: center; padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9; }
            .totp-code { font-family: monospace; font-size: 2em; font-weight: bold; color: #333; margin-bottom: 10px; }
            .totp-timer { margin-top: 10px; }
            .timer-bar { width: 100%; height: 6px; background: #eee; border-radius: 3px; overflow: hidden; }
            .timer-progress { height: 100%; background: linear-gradient(90deg, #4CAF50, #FFC107, #F44336); transition: width 1s linear; }
            .timer-text { font-size: 0.9em; color: #666; margin-top: 5px; }
            .totp-refresh-btn { background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; margin-top: 10px; }
            .totp-refresh-btn:hover { background: #0056b3; }
            .totp-qr-display { text-align: center; margin: 10px 0; }
            .totp-qr-image { max-width: 200px; border: 1px solid #ddd; padding: 10px; background: white; }
            .totp-minimal { font-family: monospace; font-weight: bold; padding: 2px 6px; background: #f0f0f0; border-radius: 3px; }
            .laravel-verified-error { color: #d32f2f; background: #ffebee; padding: 8px; border-radius: 4px; border: 1px solid #ffcdd2; }
        </style>';
    }

    private function getAutoRefreshScript(string $instanceId, array $data): string
    {
        $expiresIn = $data['expires_in'] ?? $data['expiresIn'] ?? 0;
        $period = $data['period'] ?? 60;

        return '<script>
        (function() {
            let expiresIn = ' . intval($expiresIn) . ';
            const period = ' . intval($period) . ';
            const timerText = document.querySelector("#' . $instanceId . ' .timer-text");
            const timerProgress = document.querySelector("#' . $instanceId . ' .timer-progress");

            function updateTimer() {
                expiresIn--;
                if (expiresIn < 0) {
                    location.reload(); // reload page instead of fetching
                    return;
                }
                if (timerText) {
                    timerText.textContent = "Expires in " + expiresIn + "s";
                }
                if (timerProgress) {
                    const progress = ((period - expiresIn) / period) * 100;
                    timerProgress.style.width = progress + "%";
                }
            }
            setInterval(updateTimer, 1000);
        })();
    </script>';
    }
}
