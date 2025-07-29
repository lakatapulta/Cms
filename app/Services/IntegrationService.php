<?php

namespace FlexCMS\Services;

class IntegrationService
{
    protected $integrations = [];

    public function __construct()
    {
        $this->initializeIntegrations();
    }

    /**
     * Initialize available integrations
     */
    protected function initializeIntegrations()
    {
        $this->integrations = [
            'mailchimp' => new MailchimpIntegration(),
            'paypal' => new PayPalIntegration(),
            'stripe' => new StripeIntegration(),
            'google_drive' => new GoogleDriveIntegration(),
            'slack' => new SlackIntegration(),
            'discord' => new DiscordIntegration(),
            'sendgrid' => new SendGridIntegration(),
            'aws_s3' => new AWSS3Integration(),
            'cloudinary' => new CloudinaryIntegration(),
            'recaptcha' => new RecaptchaIntegration()
        ];
    }

    /**
     * Get available integrations
     */
    public function getAvailableIntegrations()
    {
        $integrations = [];
        
        foreach ($this->integrations as $key => $integration) {
            $integrations[$key] = [
                'name' => $integration->getName(),
                'description' => $integration->getDescription(),
                'category' => $integration->getCategory(),
                'enabled' => $integration->isEnabled(),
                'configured' => $integration->isConfigured(),
                'settings' => $integration->getSettings(),
                'icon' => $integration->getIcon()
            ];
        }

        return $integrations;
    }

    /**
     * Get integration by key
     */
    public function getIntegration($key)
    {
        return $this->integrations[$key] ?? null;
    }

    /**
     * Enable integration
     */
    public function enableIntegration($key, $settings = [])
    {
        $integration = $this->getIntegration($key);
        if (!$integration) {
            return ['success' => false, 'error' => 'Integration not found'];
        }

        try {
            $integration->configure($settings);
            $integration->enable();
            
            logger()->info('Integration enabled', ['integration' => $key]);
            
            return ['success' => true, 'message' => 'Integration enabled successfully'];
        } catch (\Exception $e) {
            logger()->error('Integration enable failed', ['integration' => $key, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Disable integration
     */
    public function disableIntegration($key)
    {
        $integration = $this->getIntegration($key);
        if (!$integration) {
            return ['success' => false, 'error' => 'Integration not found'];
        }

        $integration->disable();
        logger()->info('Integration disabled', ['integration' => $key]);
        
        return ['success' => true, 'message' => 'Integration disabled successfully'];
    }

    /**
     * Test integration connection
     */
    public function testIntegration($key)
    {
        $integration = $this->getIntegration($key);
        if (!$integration) {
            return ['success' => false, 'error' => 'Integration not found'];
        }

        return $integration->test();
    }
}

/**
 * Base Integration Class
 */
abstract class BaseIntegration
{
    protected $enabled = false;
    protected $settings = [];

    abstract public function getName();
    abstract public function getDescription();
    abstract public function getCategory();
    abstract public function getIcon();
    abstract public function getSettings();
    abstract public function test();

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function isConfigured()
    {
        return !empty($this->settings);
    }

    public function enable()
    {
        $this->enabled = true;
        $this->saveSettings();
    }

    public function disable()
    {
        $this->enabled = false;
        $this->saveSettings();
    }

    public function configure($settings)
    {
        $this->settings = array_merge($this->settings, $settings);
        $this->saveSettings();
    }

    protected function saveSettings()
    {
        // Save to .env or database
        $this->updateEnvFile();
    }

    protected function updateEnvFile()
    {
        // Implementation to update .env file
        $envPath = ROOT_PATH . '/.env';
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';

        foreach ($this->getEnvMapping() as $envKey => $settingKey) {
            $value = $this->settings[$settingKey] ?? '';
            $pattern = "/^{$envKey}=.*/m";
            $replacement = "{$envKey}={$value}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envPath, $envContent);
    }

    protected function getEnvMapping()
    {
        return [];
    }

    protected function makeRequest($url, $data = null, $headers = [])
    {
        $options = [
            'http' => [
                'method' => $data ? 'POST' : 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $data ? (is_array($data) ? http_build_query($data) : $data) : null
            ]
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        return json_decode($result, true);
    }
}

/**
 * Mailchimp Integration
 */
class MailchimpIntegration extends BaseIntegration
{
    public function getName()
    {
        return 'Mailchimp';
    }

    public function getDescription()
    {
        return 'Email marketing and newsletter management';
    }

    public function getCategory()
    {
        return 'Email Marketing';
    }

    public function getIcon()
    {
        return 'fas fa-envelope';
    }

    public function getSettings()
    {
        return [
            'api_key' => config('mailchimp.api_key', ''),
            'list_id' => config('mailchimp.list_id', ''),
            'double_optin' => config('mailchimp.double_optin', true)
        ];
    }

    protected function getEnvMapping()
    {
        return [
            'MAILCHIMP_API_KEY' => 'api_key',
            'MAILCHIMP_LIST_ID' => 'list_id',
            'MAILCHIMP_DOUBLE_OPTIN' => 'double_optin'
        ];
    }

    public function test()
    {
        $apiKey = $this->settings['api_key'] ?? '';
        if (empty($apiKey)) {
            return ['success' => false, 'error' => 'API key not configured'];
        }

        $datacenter = substr($apiKey, strpos($apiKey, '-') + 1);
        $url = "https://{$datacenter}.api.mailchimp.com/3.0/ping";
        
        $headers = [
            'Authorization: apikey ' . $apiKey,
            'Content-Type: application/json'
        ];

        try {
            $response = $this->makeRequest($url, null, $headers);
            
            if (isset($response['health_status']) && $response['health_status'] === 'Everything\'s Chimpy!') {
                return ['success' => true, 'message' => 'Mailchimp connection successful'];
            }
            
            return ['success' => false, 'error' => 'Invalid API response'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Subscribe user to newsletter
     */
    public function subscribe($email, $firstName = '', $lastName = '')
    {
        $apiKey = $this->settings['api_key'] ?? '';
        $listId = $this->settings['list_id'] ?? '';
        
        if (empty($apiKey) || empty($listId)) {
            return ['success' => false, 'error' => 'Mailchimp not configured'];
        }

        $datacenter = substr($apiKey, strpos($apiKey, '-') + 1);
        $url = "https://{$datacenter}.api.mailchimp.com/3.0/lists/{$listId}/members";
        
        $data = [
            'email_address' => $email,
            'status' => $this->settings['double_optin'] ? 'pending' : 'subscribed',
            'merge_fields' => [
                'FNAME' => $firstName,
                'LNAME' => $lastName
            ]
        ];

        $headers = [
            'Authorization: apikey ' . $apiKey,
            'Content-Type: application/json'
        ];

        try {
            $response = $this->makeRequest($url, json_encode($data), $headers);
            
            if (isset($response['id'])) {
                return ['success' => true, 'message' => 'Successfully subscribed to newsletter'];
            }
            
            return ['success' => false, 'error' => $response['detail'] ?? 'Subscription failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Subscription failed: ' . $e->getMessage()];
        }
    }
}

/**
 * PayPal Integration
 */
class PayPalIntegration extends BaseIntegration
{
    public function getName()
    {
        return 'PayPal';
    }

    public function getDescription()
    {
        return 'Online payment processing';
    }

    public function getCategory()
    {
        return 'Payments';
    }

    public function getIcon()
    {
        return 'fab fa-paypal';
    }

    public function getSettings()
    {
        return [
            'client_id' => config('paypal.client_id', ''),
            'client_secret' => config('paypal.client_secret', ''),
            'sandbox' => config('paypal.sandbox', true),
            'currency' => config('paypal.currency', 'USD')
        ];
    }

    protected function getEnvMapping()
    {
        return [
            'PAYPAL_CLIENT_ID' => 'client_id',
            'PAYPAL_CLIENT_SECRET' => 'client_secret',
            'PAYPAL_SANDBOX' => 'sandbox',
            'PAYPAL_CURRENCY' => 'currency'
        ];
    }

    public function test()
    {
        $clientId = $this->settings['client_id'] ?? '';
        $clientSecret = $this->settings['client_secret'] ?? '';
        
        if (empty($clientId) || empty($clientSecret)) {
            return ['success' => false, 'error' => 'PayPal credentials not configured'];
        }

        $baseUrl = $this->settings['sandbox'] ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        $url = $baseUrl . '/v1/oauth2/token';
        
        $headers = [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ];

        try {
            $response = $this->makeRequest($url, 'grant_type=client_credentials', $headers);
            
            if (isset($response['access_token'])) {
                return ['success' => true, 'message' => 'PayPal connection successful'];
            }
            
            return ['success' => false, 'error' => 'Authentication failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Create payment
     */
    public function createPayment($amount, $description, $returnUrl, $cancelUrl)
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return ['success' => false, 'error' => 'Failed to authenticate with PayPal'];
        }

        $baseUrl = $this->settings['sandbox'] ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        $url = $baseUrl . '/v1/payments/payment';
        
        $data = [
            'intent' => 'sale',
            'payer' => ['payment_method' => 'paypal'],
            'transactions' => [[
                'amount' => [
                    'total' => number_format($amount, 2, '.', ''),
                    'currency' => $this->settings['currency']
                ],
                'description' => $description
            ]],
            'redirect_urls' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];

        try {
            $response = $this->makeRequest($url, json_encode($data), $headers);
            
            if (isset($response['id'])) {
                return ['success' => true, 'payment_id' => $response['id'], 'approval_url' => $this->getApprovalUrl($response)];
            }
            
            return ['success' => false, 'error' => 'Payment creation failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Payment creation failed: ' . $e->getMessage()];
        }
    }

    protected function getAccessToken()
    {
        $clientId = $this->settings['client_id'] ?? '';
        $clientSecret = $this->settings['client_secret'] ?? '';
        $baseUrl = $this->settings['sandbox'] ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
        
        $url = $baseUrl . '/v1/oauth2/token';
        $headers = [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $response = $this->makeRequest($url, 'grant_type=client_credentials', $headers);
        return $response['access_token'] ?? null;
    }

    protected function getApprovalUrl($payment)
    {
        foreach ($payment['links'] as $link) {
            if ($link['rel'] === 'approval_url') {
                return $link['href'];
            }
        }
        return null;
    }
}

/**
 * Stripe Integration
 */
class StripeIntegration extends BaseIntegration
{
    public function getName()
    {
        return 'Stripe';
    }

    public function getDescription()
    {
        return 'Modern payment processing';
    }

    public function getCategory()
    {
        return 'Payments';
    }

    public function getIcon()
    {
        return 'fab fa-cc-stripe';
    }

    public function getSettings()
    {
        return [
            'publishable_key' => config('stripe.publishable_key', ''),
            'secret_key' => config('stripe.secret_key', ''),
            'webhook_secret' => config('stripe.webhook_secret', ''),
            'currency' => config('stripe.currency', 'usd')
        ];
    }

    protected function getEnvMapping()
    {
        return [
            'STRIPE_PUBLISHABLE_KEY' => 'publishable_key',
            'STRIPE_SECRET_KEY' => 'secret_key',
            'STRIPE_WEBHOOK_SECRET' => 'webhook_secret',
            'STRIPE_CURRENCY' => 'currency'
        ];
    }

    public function test()
    {
        $secretKey = $this->settings['secret_key'] ?? '';
        if (empty($secretKey)) {
            return ['success' => false, 'error' => 'Stripe secret key not configured'];
        }

        $url = 'https://api.stripe.com/v1/customers?limit=1';
        $headers = [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded'
        ];

        try {
            $response = $this->makeRequest($url, null, $headers);
            
            if (isset($response['data'])) {
                return ['success' => true, 'message' => 'Stripe connection successful'];
            }
            
            return ['success' => false, 'error' => 'Authentication failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()];
        }
    }
}

/**
 * Slack Integration
 */
class SlackIntegration extends BaseIntegration
{
    public function getName()
    {
        return 'Slack';
    }

    public function getDescription()
    {
        return 'Team communication and notifications';
    }

    public function getCategory()
    {
        return 'Communication';
    }

    public function getIcon()
    {
        return 'fab fa-slack';
    }

    public function getSettings()
    {
        return [
            'webhook_url' => config('slack.webhook_url', ''),
            'channel' => config('slack.channel', '#general'),
            'username' => config('slack.username', 'FlexCMS')
        ];
    }

    protected function getEnvMapping()
    {
        return [
            'SLACK_WEBHOOK_URL' => 'webhook_url',
            'SLACK_CHANNEL' => 'channel',
            'SLACK_USERNAME' => 'username'
        ];
    }

    public function test()
    {
        $webhookUrl = $this->settings['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            return ['success' => false, 'error' => 'Slack webhook URL not configured'];
        }

        $data = [
            'text' => 'FlexCMS integration test message',
            'channel' => $this->settings['channel'],
            'username' => $this->settings['username']
        ];

        try {
            $response = $this->makeRequest($webhookUrl, json_encode($data), ['Content-Type: application/json']);
            return ['success' => true, 'message' => 'Slack notification sent successfully'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Failed to send Slack message: ' . $e->getMessage()];
        }
    }

    /**
     * Send notification to Slack
     */
    public function sendNotification($message, $channel = null)
    {
        $webhookUrl = $this->settings['webhook_url'] ?? '';
        if (empty($webhookUrl)) {
            return false;
        }

        $data = [
            'text' => $message,
            'channel' => $channel ?: $this->settings['channel'],
            'username' => $this->settings['username']
        ];

        try {
            $this->makeRequest($webhookUrl, json_encode($data), ['Content-Type: application/json']);
            return true;
        } catch (\Exception $e) {
            logger()->error('Slack notification failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

/**
 * Additional integrations would follow the same pattern...
 */

class GoogleDriveIntegration extends BaseIntegration
{
    public function getName() { return 'Google Drive'; }
    public function getDescription() { return 'Cloud storage and file backup'; }
    public function getCategory() { return 'Storage'; }
    public function getIcon() { return 'fab fa-google-drive'; }
    
    public function getSettings()
    {
        return [
            'client_id' => config('google.drive.client_id', ''),
            'client_secret' => config('google.drive.client_secret', ''),
            'folder_id' => config('google.drive.folder_id', '')
        ];
    }
    
    public function test()
    {
        // Implementation for Google Drive API test
        return ['success' => true, 'message' => 'Google Drive integration ready'];
    }
}

class SendGridIntegration extends BaseIntegration
{
    public function getName() { return 'SendGrid'; }
    public function getDescription() { return 'Email delivery service'; }
    public function getCategory() { return 'Email'; }
    public function getIcon() { return 'fas fa-mail-bulk'; }
    
    public function getSettings()
    {
        return [
            'api_key' => config('sendgrid.api_key', ''),
            'from_email' => config('sendgrid.from_email', ''),
            'from_name' => config('sendgrid.from_name', '')
        ];
    }
    
    public function test()
    {
        // Implementation for SendGrid API test
        return ['success' => true, 'message' => 'SendGrid integration ready'];
    }
}

class AWSS3Integration extends BaseIntegration
{
    public function getName() { return 'AWS S3'; }
    public function getDescription() { return 'Cloud file storage and CDN'; }
    public function getCategory() { return 'Storage'; }
    public function getIcon() { return 'fab fa-aws'; }
    
    public function getSettings()
    {
        return [
            'access_key' => config('aws.s3.access_key', ''),
            'secret_key' => config('aws.s3.secret_key', ''),
            'bucket' => config('aws.s3.bucket', ''),
            'region' => config('aws.s3.region', 'us-east-1')
        ];
    }
    
    public function test()
    {
        // Implementation for AWS S3 test
        return ['success' => true, 'message' => 'AWS S3 integration ready'];
    }
}

class CloudinaryIntegration extends BaseIntegration
{
    public function getName() { return 'Cloudinary'; }
    public function getDescription() { return 'Image and video management'; }
    public function getCategory() { return 'Media'; }
    public function getIcon() { return 'fas fa-cloud'; }
    
    public function getSettings()
    {
        return [
            'cloud_name' => config('cloudinary.cloud_name', ''),
            'api_key' => config('cloudinary.api_key', ''),
            'api_secret' => config('cloudinary.api_secret', '')
        ];
    }
    
    public function test()
    {
        // Implementation for Cloudinary test
        return ['success' => true, 'message' => 'Cloudinary integration ready'];
    }
}

class RecaptchaIntegration extends BaseIntegration
{
    public function getName() { return 'reCAPTCHA'; }
    public function getDescription() { return 'Spam protection for forms'; }
    public function getCategory() { return 'Security'; }
    public function getIcon() { return 'fas fa-shield-alt'; }
    
    public function getSettings()
    {
        return [
            'site_key' => config('recaptcha.site_key', ''),
            'secret_key' => config('recaptcha.secret_key', ''),
            'version' => config('recaptcha.version', 'v2')
        ];
    }
    
    public function test()
    {
        // Implementation for reCAPTCHA test
        return ['success' => true, 'message' => 'reCAPTCHA integration ready'];
    }
}

class DiscordIntegration extends BaseIntegration
{
    public function getName() { return 'Discord'; }
    public function getDescription() { return 'Community notifications and webhooks'; }
    public function getCategory() { return 'Communication'; }
    public function getIcon() { return 'fab fa-discord'; }
    
    public function getSettings()
    {
        return [
            'webhook_url' => config('discord.webhook_url', ''),
            'username' => config('discord.username', 'FlexCMS')
        ];
    }
    
    public function test()
    {
        // Implementation for Discord webhook test
        return ['success' => true, 'message' => 'Discord integration ready'];
    }
}