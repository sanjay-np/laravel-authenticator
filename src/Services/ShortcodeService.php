<?php

namespace LaravelAuthenticator\Services;

class ShortcodeService
{
    /**
     * Parse and render shortcode
     */
    public function render(string $expression): string
    {
        // Parse the shortcode expression
        $attributes = $this->parseAttributes($expression);

        // Validate required parameters
        if (!$this->validateAttributes($attributes)) {
            return $this->renderError('Invalid shortcode parameters');
        }

        try {
            // Get TOTP data based on shortcode type
            $data = $this->getTotpData($attributes);

            dd($data);

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
    private function parseAttributes(string $expression): array
    {
        $attributes = [];

        // Remove quotes and parse key="value" pairs
        preg_match_all('/(\w+)=["\']([^"\']*)["\']/', $expression, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $attributes[$match[1]] = $match[2];
        }

        // Set defaults
        $attributes = array_merge([
            'display' => 'code',
            'format' => 'default',
            'refresh' => 'manual',
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
        return [
            'secret_id' => $secretId,
        ];
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
        $showQr = $attributes['show_qr'] === 'true' || $display === 'qr' || $display === 'both';
        $size = $attributes['size'];

        // Generate unique ID for this shortcode instance
        $instanceId = 'totp-' . uniqid();

        $html = '<div class="laravel-verified-shortcode ' . $format . ' ' . $size . '" id="' . $instanceId . '">';

        // Add CSS if not already added
        $html .= $this->getShortcodeStyles();

        // Render based on display type
        switch ($display) {
            case 'code':
                $html .= $this->renderCodeDisplay($data, $showTimer, $refresh, $instanceId);
                break;
            case 'qr':
                $html .= $this->renderQrDisplay($data);
                break;
            case 'both':
                $html .= $this->renderCodeDisplay($data, $showTimer, $refresh, $instanceId);
                $html .= $this->renderQrDisplay($data);
                break;
            case 'minimal':
                $html .= $this->renderMinimalDisplay($data);
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
        $code = $data['current_code'] ?? $data['currentCode'] ?? '------';
        $expiresIn = $data['expires_in'] ?? $data['expiresIn'] ?? 0;
        $period = $data['period'] ?? 60;

        $html = '<div class="totp-code-display">';
        $html .= '<div class="totp-code">' . chunk_split($code, 3, ' ') . '</div>';

        if ($showTimer) {
            $progressPercentage = round((($period - $expiresIn) / $period) * 100, 2);
            $html .= '<div class="totp-timer">';
            $html .= '<div class="timer-bar"><div class="timer-progress" style="width: ' . $progressPercentage . '%"></div></div>';
            $html .= '<div class="timer-text">Expires in ' . $expiresIn . 's</div>';
            $html .= '</div>';
        }

        if ($refresh === 'manual') {
            $html .= '<button class="totp-refresh-btn" onclick="refreshTotp(\'' . $instanceId . '\')">Refresh</button>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render QR code display
     */
    private function renderQrDisplay(array $data): string
    {
        if (!isset($data['qrCode']) && !isset($data['qr_code'])) {
            return '<div class="totp-qr-error">QR code not available</div>';
        }

        $qrCode = $data['qrCode'] ?? $data['qr_code'];

        $html = '<div class="totp-qr-display">';
        $html .= '<img src="' . htmlspecialchars($qrCode) . '" alt="TOTP QR Code" class="totp-qr-image" />';
        $html .= '</div>';

        return $html;
    }

    /**
     * Render minimal display
     */
    private function renderMinimalDisplay(array $data): string
    {
        $code = $data['current_code'] ?? $data['currentCode'] ?? '------';
        return '<span class="totp-minimal">' . $code . '</span>';
    }

    /**
     * Render error message
     */
    private function renderError(string $message): string
    {
        return '<div class="laravel-verified-error">' . htmlspecialchars($message) . '</div>';
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

    /**
     * Get JavaScript for auto-refresh functionality
     */
    private function getAutoRefreshScript(string $instanceId, array $data): string
    {
        $refreshInterval = ($data['period'] ?? 60) * 1000; // Convert to milliseconds

        return '<script>
            (function() {
                function refreshTotp(id) {
                    // This would need to be implemented based on your specific needs
                    // For now, just reload the page or make an AJAX call
                    location.reload();
                }

                // Auto-refresh every period
                setInterval(function() {
                    refreshTotp("' . $instanceId . '");
                }, ' . $refreshInterval . ');

                // Make refresh function globally available
                window.refreshTotp = refreshTotp;
            })();
        </script>';
    }
}
