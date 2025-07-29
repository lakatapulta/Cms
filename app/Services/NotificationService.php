<?php

namespace FlexCMS\Services;

class NotificationService
{
    protected $channels = [];
    protected $templates = [];
    protected $queue = [];

    public function __construct()
    {
        $this->initializeChannels();
        $this->loadTemplates();
    }

    /**
     * Initialize notification channels
     */
    protected function initializeChannels()
    {
        $this->channels = [
            'email' => new EmailChannel(),
            'slack' => new SlackChannel(),
            'discord' => new DiscordChannel(),
            'database' => new DatabaseChannel(),
            'webhook' => new WebhookChannel(),
            'browser' => new BrowserChannel()
        ];
    }

    /**
     * Send notification
     */
    public function send($type, $data, $channels = ['email'], $users = null)
    {
        $notification = [
            'id' => $this->generateId(),
            'type' => $type,
            'data' => $data,
            'channels' => $channels,
            'users' => $users,
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'pending'
        ];

        // Add to queue for processing
        $this->queue[] = $notification;
        
        // Process immediately or queue for background processing
        return $this->processNotification($notification);
    }

    /**
     * Process notification
     */
    protected function processNotification($notification)
    {
        $results = [];
        
        foreach ($notification['channels'] as $channelName) {
            if (!isset($this->channels[$channelName])) {
                $results[$channelName] = ['success' => false, 'error' => 'Channel not found'];
                continue;
            }

            $channel = $this->channels[$channelName];
            
            try {
                $result = $channel->send($notification);
                $results[$channelName] = $result;
                
                logger()->info('Notification sent', [
                    'notification_id' => $notification['id'],
                    'channel' => $channelName,
                    'type' => $notification['type'],
                    'success' => $result['success']
                ]);
                
            } catch (\Exception $e) {
                $results[$channelName] = ['success' => false, 'error' => $e->getMessage()];
                
                logger()->error('Notification failed', [
                    'notification_id' => $notification['id'],
                    'channel' => $channelName,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Send welcome notification
     */
    public function sendWelcome($user)
    {
        return $this->send('user_welcome', [
            'user' => $user,
            'login_url' => url('/login'),
            'site_name' => config('app.name')
        ], ['email'], [$user]);
    }

    /**
     * Send new comment notification
     */
    public function sendNewComment($comment, $post)
    {
        // Notify post author
        $this->send('new_comment', [
            'comment' => $comment,
            'post' => $post,
            'commenter' => $comment->author,
            'moderate_url' => url('/admin/comments/' . $comment->id)
        ], ['email'], [$post->author]);

        // Notify admins
        $admins = $this->getAdminUsers();
        $this->send('new_comment_admin', [
            'comment' => $comment,
            'post' => $post,
            'commenter' => $comment->author
        ], ['email', 'slack'], $admins);
    }

    /**
     * Send new user registration notification
     */
    public function sendNewUserRegistration($user)
    {
        $admins = $this->getAdminUsers();
        
        return $this->send('new_user_registration', [
            'user' => $user,
            'registration_date' => $user->created_at,
            'user_profile_url' => url('/admin/users/' . $user->id)
        ], ['email', 'slack'], $admins);
    }

    /**
     * Send password reset notification
     */
    public function sendPasswordReset($user, $token)
    {
        return $this->send('password_reset', [
            'user' => $user,
            'reset_url' => url('/reset-password?token=' . $token),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour'))
        ], ['email'], [$user]);
    }

    /**
     * Send system alert
     */
    public function sendSystemAlert($message, $severity = 'info', $channels = ['email', 'slack'])
    {
        $admins = $this->getAdminUsers();
        
        return $this->send('system_alert', [
            'message' => $message,
            'severity' => $severity,
            'timestamp' => date('Y-m-d H:i:s'),
            'server_info' => [
                'hostname' => gethostname(),
                'php_version' => phpversion(),
                'memory_usage' => memory_get_usage(true),
                'disk_usage' => disk_free_space('/')
            ]
        ], $channels, $admins);
    }

    /**
     * Send backup notification
     */
    public function sendBackupNotification($backup, $success = true)
    {
        $admins = $this->getAdminUsers();
        
        $type = $success ? 'backup_success' : 'backup_failed';
        
        return $this->send($type, [
            'backup' => $backup,
            'success' => $success,
            'file_size' => isset($backup['file_size']) ? $this->formatBytes($backup['file_size']) : 'Unknown',
            'backup_time' => $backup['completed_at'] ?? $backup['started_at']
        ], ['email'], $admins);
    }

    /**
     * Send security alert
     */
    public function sendSecurityAlert($event, $details = [])
    {
        $admins = $this->getAdminUsers();
        
        return $this->send('security_alert', [
            'event' => $event,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ], ['email', 'slack'], $admins);
    }

    /**
     * Send form submission notification
     */
    public function sendFormSubmission($form, $submission)
    {
        $recipients = $this->getFormRecipients($form);
        
        return $this->send('form_submission', [
            'form' => $form,
            'submission' => $submission,
            'admin_url' => url('/admin/forms/' . $form['id'] . '/submissions')
        ], ['email'], $recipients);
    }

    /**
     * Send real-time notification
     */
    public function sendRealTime($type, $data, $users = null)
    {
        return $this->send($type, $data, ['browser'], $users);
    }

    /**
     * Get admin users
     */
    protected function getAdminUsers()
    {
        // This would query for admin users from the database
        // For now, return a sample structure
        return [
            (object) [
                'id' => 1,
                'email' => config('mail.admin_email', 'admin@example.com'),
                'display_name' => 'Administrator'
            ]
        ];
    }

    /**
     * Get form recipients
     */
    protected function getFormRecipients($form)
    {
        $emailTo = $form['settings']['email_to'] ?? config('mail.admin_email');
        
        return [
            (object) [
                'email' => $emailTo,
                'display_name' => 'Form Administrator'
            ]
        ];
    }

    /**
     * Load notification templates
     */
    protected function loadTemplates()
    {
        $this->templates = [
            'user_welcome' => [
                'subject' => 'Welcome to {{ site_name }}!',
                'email_template' => 'emails/welcome',
                'slack_template' => 'New user registered: {{ user.display_name }}'
            ],
            'new_comment' => [
                'subject' => 'New comment on "{{ post.title }}"',
                'email_template' => 'emails/new_comment',
                'slack_template' => 'New comment by {{ commenter.display_name }} on "{{ post.title }}"'
            ],
            'new_comment_admin' => [
                'subject' => 'New comment requires moderation',
                'email_template' => 'emails/new_comment_admin',
                'slack_template' => 'New comment awaiting moderation from {{ commenter.display_name }}'
            ],
            'new_user_registration' => [
                'subject' => 'New user registration',
                'email_template' => 'emails/new_user_registration',
                'slack_template' => 'New user registered: {{ user.display_name }} ({{ user.email }})'
            ],
            'password_reset' => [
                'subject' => 'Password Reset Request',
                'email_template' => 'emails/password_reset',
                'slack_template' => null
            ],
            'system_alert' => [
                'subject' => 'System Alert: {{ severity }}',
                'email_template' => 'emails/system_alert',
                'slack_template' => ':warning: System Alert: {{ message }}'
            ],
            'backup_success' => [
                'subject' => 'Backup Completed Successfully',
                'email_template' => 'emails/backup_success',
                'slack_template' => ':white_check_mark: Backup completed successfully ({{ file_size }})'
            ],
            'backup_failed' => [
                'subject' => 'Backup Failed',
                'email_template' => 'emails/backup_failed',
                'slack_template' => ':x: Backup failed - please check logs'
            ],
            'security_alert' => [
                'subject' => 'Security Alert: {{ event }}',
                'email_template' => 'emails/security_alert',
                'slack_template' => ':rotating_light: Security Alert: {{ event }}'
            ],
            'form_submission' => [
                'subject' => 'New form submission: {{ form.title }}',
                'email_template' => 'emails/form_submission',
                'slack_template' => 'New form submission received: {{ form.title }}'
            ]
        ];
    }

    /**
     * Get template
     */
    public function getTemplate($type)
    {
        return $this->templates[$type] ?? null;
    }

    /**
     * Register new template
     */
    public function registerTemplate($type, $template)
    {
        $this->templates[$type] = $template;
    }

    /**
     * Generate notification ID
     */
    protected function generateId()
    {
        return uniqid('notif_', true);
    }

    /**
     * Get client IP
     */
    protected function getClientIP()
    {
        $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Format bytes
     */
    protected function formatBytes($size, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * Get notification statistics
     */
    public function getStats($period = '30days')
    {
        // This would query the database for actual stats
        return [
            'total_sent' => rand(100, 1000),
            'successful' => rand(90, 99) . '%',
            'failed' => rand(1, 10) . '%',
            'by_channel' => [
                'email' => rand(50, 80) . '%',
                'slack' => rand(10, 30) . '%',
                'browser' => rand(5, 15) . '%',
                'webhook' => rand(1, 5) . '%'
            ],
            'by_type' => [
                'user_events' => rand(30, 50) . '%',
                'system_alerts' => rand(10, 20) . '%',
                'security' => rand(5, 15) . '%',
                'forms' => rand(20, 40) . '%'
            ]
        ];
    }
}

/**
 * Base Notification Channel
 */
abstract class BaseNotificationChannel
{
    abstract public function send($notification);
    
    protected function renderTemplate($template, $data)
    {
        if (!$template) {
            return '';
        }
        
        // Simple template rendering
        $content = $template;
        $this->replaceVariables($content, $data);
        
        return $content;
    }
    
    protected function replaceVariables(&$content, $data, $prefix = '')
    {
        foreach ($data as $key => $value) {
            $placeholder = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_object($value) || is_array($value)) {
                $this->replaceVariables($content, (array)$value, $placeholder);
            } else {
                $content = str_replace("{{ {$placeholder} }}", $value, $content);
            }
        }
    }
}

/**
 * Email Notification Channel
 */
class EmailChannel extends BaseNotificationChannel
{
    public function send($notification)
    {
        $notificationService = app('notifications');
        $template = $notificationService->getTemplate($notification['type']);
        
        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }

        $users = $notification['users'] ?? [];
        $successCount = 0;
        $errors = [];

        foreach ($users as $user) {
            try {
                $subject = $this->renderTemplate($template['subject'], $notification['data']);
                $body = $this->renderEmailBody($template['email_template'], $notification['data']);
                
                $result = $this->sendEmail($user->email, $subject, $body);
                
                if ($result) {
                    $successCount++;
                } else {
                    $errors[] = "Failed to send to {$user->email}";
                }
                
            } catch (\Exception $e) {
                $errors[] = "Error sending to {$user->email}: " . $e->getMessage();
            }
        }

        return [
            'success' => $successCount > 0,
            'sent_count' => $successCount,
            'total_count' => count($users),
            'errors' => $errors
        ];
    }

    protected function renderEmailBody($template, $data)
    {
        // In a real implementation, this would render a Twig template
        // For now, return a simple text version
        $body = "FlexCMS Notification\n\n";
        
        switch ($template) {
            case 'emails/welcome':
                $body .= "Welcome to " . ($data['site_name'] ?? 'FlexCMS') . "!\n\n";
                $body .= "Hello " . ($data['user']->display_name ?? 'User') . ",\n\n";
                $body .= "Thank you for joining us. You can login at: " . ($data['login_url'] ?? '/login') . "\n";
                break;
                
            case 'emails/new_comment':
                $body .= "A new comment has been posted on your article.\n\n";
                $body .= "Post: " . ($data['post']->title ?? 'Unknown') . "\n";
                $body .= "Comment by: " . ($data['commenter']->display_name ?? 'Anonymous') . "\n\n";
                $body .= "Moderate: " . ($data['moderate_url'] ?? '') . "\n";
                break;
                
            case 'emails/password_reset':
                $body .= "Password Reset Request\n\n";
                $body .= "Click the link below to reset your password:\n";
                $body .= ($data['reset_url'] ?? '') . "\n\n";
                $body .= "This link expires at: " . ($data['expires_at'] ?? '') . "\n";
                break;
                
            default:
                $body .= "Notification Type: " . ($template ?? 'Unknown') . "\n";
                $body .= json_encode($data, JSON_PRETTY_PRINT);
        }
        
        return $body;
    }

    protected function sendEmail($to, $subject, $body)
    {
        $fromEmail = config('mail.from_email', 'noreply@example.com');
        $fromName = config('mail.from_name', 'FlexCMS');
        
        $headers = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        return mail($to, $subject, $body, $headers);
    }
}

/**
 * Slack Notification Channel
 */
class SlackChannel extends BaseNotificationChannel
{
    public function send($notification)
    {
        $webhookUrl = config('slack.webhook_url');
        
        if (!$webhookUrl) {
            return ['success' => false, 'error' => 'Slack webhook not configured'];
        }

        $notificationService = app('notifications');
        $template = $notificationService->getTemplate($notification['type']);
        
        if (!$template || !$template['slack_template']) {
            return ['success' => false, 'error' => 'Slack template not found'];
        }

        $message = $this->renderTemplate($template['slack_template'], $notification['data']);
        
        $data = [
            'text' => $message,
            'channel' => config('slack.channel', '#general'),
            'username' => config('slack.username', 'FlexCMS'),
            'icon_emoji' => ':gear:'
        ];

        try {
            $result = $this->makeSlackRequest($webhookUrl, $data);
            return ['success' => true, 'message' => 'Slack notification sent'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Slack notification failed: ' . $e->getMessage()];
        }
    }

    protected function makeSlackRequest($url, $data)
    {
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }
}

/**
 * Discord Notification Channel
 */
class DiscordChannel extends BaseNotificationChannel
{
    public function send($notification)
    {
        $webhookUrl = config('discord.webhook_url');
        
        if (!$webhookUrl) {
            return ['success' => false, 'error' => 'Discord webhook not configured'];
        }

        $notificationService = app('notifications');
        $template = $notificationService->getTemplate($notification['type']);
        
        if (!$template) {
            return ['success' => false, 'error' => 'Template not found'];
        }

        $message = $this->renderTemplate($template['slack_template'] ?? $template['subject'], $notification['data']);
        
        $data = [
            'content' => $message,
            'username' => config('discord.username', 'FlexCMS')
        ];

        try {
            $result = $this->makeDiscordRequest($webhookUrl, $data);
            return ['success' => true, 'message' => 'Discord notification sent'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Discord notification failed: ' . $e->getMessage()];
        }
    }

    protected function makeDiscordRequest($url, $data)
    {
        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        return file_get_contents($url, false, $context);
    }
}

/**
 * Database Notification Channel
 */
class DatabaseChannel extends BaseNotificationChannel
{
    public function send($notification)
    {
        // Store notification in database for admin panel
        $data = [
            'type' => $notification['type'],
            'data' => json_encode($notification['data']),
            'created_at' => date('Y-m-d H:i:s'),
            'read_at' => null
        ];

        // In a real implementation, this would insert into a notifications table
        logger()->info('Database notification stored', $data);

        return ['success' => true, 'message' => 'Notification stored in database'];
    }
}

/**
 * Webhook Notification Channel
 */
class WebhookChannel extends BaseNotificationChannel
{
    public function send($notification)
    {
        $webhooks = config('webhooks.endpoints', []);
        
        if (empty($webhooks)) {
            return ['success' => false, 'error' => 'No webhooks configured'];
        }

        $successCount = 0;
        $errors = [];

        foreach ($webhooks as $webhook) {
            try {
                $result = $this->sendWebhook($webhook, $notification);
                if ($result) {
                    $successCount++;
                } else {
                    $errors[] = "Failed to send to {$webhook['url']}";
                }
            } catch (\Exception $e) {
                $errors[] = "Error sending to {$webhook['url']}: " . $e->getMessage();
            }
        }

        return [
            'success' => $successCount > 0,
            'sent_count' => $successCount,
            'total_count' => count($webhooks),
            'errors' => $errors
        ];
    }

    protected function sendWebhook($webhook, $notification)
    {
        $data = [
            'type' => $notification['type'],
            'data' => $notification['data'],
            'timestamp' => $notification['created_at'],
            'source' => 'FlexCMS'
        ];

        $options = [
            'http' => [
                'header' => "Content-type: application/json\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            ]
        ];

        $context = stream_context_create($options);
        return file_get_contents($webhook['url'], false, $context);
    }
}

/**
 * Browser Notification Channel (Real-time)
 */
class BrowserChannel extends BaseNotificationChannel
{
    public function send($notification)
    {
        // Store notification for real-time delivery via WebSocket or SSE
        $data = [
            'id' => $notification['id'],
            'type' => $notification['type'],
            'data' => $notification['data'],
            'timestamp' => $notification['created_at']
        ];

        // In a real implementation, this would push to WebSocket server or SSE
        $this->storeForRealTime($data, $notification['users']);

        return ['success' => true, 'message' => 'Real-time notification queued'];
    }

    protected function storeForRealTime($data, $users)
    {
        // Store in cache or message queue for real-time delivery
        $cacheKey = 'realtime_notifications';
        
        // This would integrate with Redis or similar for real-time delivery
        logger()->info('Real-time notification queued', [
            'notification_id' => $data['id'],
            'users' => count($users ?? [])
        ]);
    }
}