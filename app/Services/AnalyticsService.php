<?php

namespace FlexCMS\Services;

class AnalyticsService
{
    protected $gaTrackingId;
    protected $gtagConfigured = false;
    
    public function __construct()
    {
        $this->gaTrackingId = config('analytics.google.tracking_id');
    }

    /**
     * Get Google Analytics tracking code
     */
    public function getTrackingCode()
    {
        if (!$this->gaTrackingId) {
            return '';
        }

        return $this->generateGACode();
    }

    /**
     * Generate Google Analytics 4 code
     */
    protected function generateGACode()
    {
        $trackingId = $this->gaTrackingId;
        $config = $this->getGAConfig();

        return "
<!-- Google Analytics 4 -->
<script async src=\"https://www.googletagmanager.com/gtag/js?id={$trackingId}\"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$trackingId}', " . json_encode($config) . ");
  
  // FlexCMS Enhanced Analytics
  window.flexcmsAnalytics = {
    trackingId: '{$trackingId}',
    trackEvent: function(action, category, label, value) {
      gtag('event', action, {
        event_category: category,
        event_label: label,
        value: value
      });
    },
    trackPageView: function(pageTitle, pagePath) {
      gtag('config', '{$trackingId}', {
        page_title: pageTitle,
        page_location: window.location.href,
        page_path: pagePath
      });
    },
    trackSearch: function(searchTerm, resultsCount) {
      gtag('event', 'search', {
        search_term: searchTerm,
        search_results: resultsCount
      });
    },
    trackDownload: function(fileName, fileUrl) {
      gtag('event', 'file_download', {
        file_name: fileName,
        file_url: fileUrl
      });
    },
    trackOutboundClick: function(url) {
      gtag('event', 'click', {
        event_category: 'outbound',
        event_label: url,
        transport_type: 'sendBeacon'
      });
    },
    trackScroll: function(percentage) {
      gtag('event', 'scroll', {
        event_category: 'engagement',
        event_label: percentage + '%'
      });
    },
    trackTimeOnPage: function(seconds) {
      gtag('event', 'timing_complete', {
        name: 'time_on_page',
        value: seconds
      });
    }
  };
</script>";
    }

    /**
     * Get Google Analytics configuration
     */
    protected function getGAConfig()
    {
        $config = [
            'anonymize_ip' => config('analytics.google.anonymize_ip', true),
            'allow_ad_features' => config('analytics.google.allow_ad_features', false),
            'allow_google_signals' => config('analytics.google.allow_google_signals', true),
            'cookie_expires' => config('analytics.google.cookie_expires', 63072000), // 2 years
        ];

        // Enhanced ecommerce
        if (config('analytics.google.enhanced_ecommerce', false)) {
            $config['enhanced_ecommerce'] = true;
        }

        // Custom dimensions
        $customDimensions = config('analytics.google.custom_dimensions', []);
        if (!empty($customDimensions)) {
            foreach ($customDimensions as $index => $value) {
                $config['custom_map.' . $index] = $value;
            }
        }

        return $config;
    }

    /**
     * Track custom event
     */
    public function trackEvent($action, $category = 'general', $label = null, $value = null)
    {
        $eventData = [
            'action' => $action,
            'category' => $category,
            'timestamp' => time(),
            'user_id' => user() ? user()->id : null,
            'session_id' => session_id(),
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        if ($label) {
            $eventData['label'] = $label;
        }

        if ($value !== null) {
            $eventData['value'] = $value;
        }

        // Store in database for internal analytics
        $this->storeEvent($eventData);

        // Return JavaScript code for client-side tracking
        return $this->generateEventTrackingJS($action, $category, $label, $value);
    }

    /**
     * Generate event tracking JavaScript
     */
    protected function generateEventTrackingJS($action, $category, $label, $value)
    {
        $params = [
            'event_category' => $category
        ];

        if ($label) {
            $params['event_label'] = $label;
        }

        if ($value !== null) {
            $params['value'] = $value;
        }

        return sprintf(
            "gtag('event', '%s', %s);",
            $action,
            json_encode($params)
        );
    }

    /**
     * Track page view
     */
    public function trackPageView($pageTitle = null, $pagePath = null)
    {
        $pageData = [
            'type' => 'page_view',
            'page_title' => $pageTitle ?: (isset($GLOBALS['page_title']) ? $GLOBALS['page_title'] : 'Unknown'),
            'page_path' => $pagePath ?: $_SERVER['REQUEST_URI'],
            'page_url' => $this->getCurrentUrl(),
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'timestamp' => time(),
            'user_id' => user() ? user()->id : null,
            'session_id' => session_id(),
            'ip_address' => $this->getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];

        $this->storeEvent($pageData);

        return sprintf(
            "gtag('config', '%s', { page_title: '%s', page_path: '%s' });",
            $this->gaTrackingId,
            addslashes($pageData['page_title']),
            addslashes($pageData['page_path'])
        );
    }

    /**
     * Track search
     */
    public function trackSearch($searchTerm, $resultsCount = null)
    {
        $searchData = [
            'type' => 'search',
            'search_term' => $searchTerm,
            'results_count' => $resultsCount,
            'timestamp' => time(),
            'user_id' => user() ? user()->id : null,
            'session_id' => session_id(),
            'ip_address' => $this->getClientIP()
        ];

        $this->storeEvent($searchData);

        $params = ['search_term' => $searchTerm];
        if ($resultsCount !== null) {
            $params['search_results'] = $resultsCount;
        }

        return sprintf(
            "gtag('event', 'search', %s);",
            json_encode($params)
        );
    }

    /**
     * Track ecommerce purchase
     */
    public function trackPurchase($transactionId, $items, $value, $currency = 'USD')
    {
        $purchaseData = [
            'type' => 'purchase',
            'transaction_id' => $transactionId,
            'value' => $value,
            'currency' => $currency,
            'items' => $items,
            'timestamp' => time(),
            'user_id' => user() ? user()->id : null,
            'session_id' => session_id(),
            'ip_address' => $this->getClientIP()
        ];

        $this->storeEvent($purchaseData);

        return sprintf(
            "gtag('event', 'purchase', {
                transaction_id: '%s',
                value: %s,
                currency: '%s',
                items: %s
            });",
            $transactionId,
            $value,
            $currency,
            json_encode($items)
        );
    }

    /**
     * Store event in database
     */
    protected function storeEvent($eventData)
    {
        // Store analytics events in database for internal reporting
        // This would use an analytics_events table
        
        $event = [
            'type' => $eventData['type'] ?? $eventData['action'] ?? 'custom',
            'data' => json_encode($eventData),
            'user_id' => $eventData['user_id'],
            'session_id' => $eventData['session_id'],
            'ip_address' => $eventData['ip_address'],
            'created_at' => date('Y-m-d H:i:s', $eventData['timestamp'])
        ];

        // Insert into analytics events table
        // AnalyticsEvent::create($event);
        
        // For now, log to file
        logger()->info('Analytics event', $event);
    }

    /**
     * Get analytics dashboard data
     */
    public function getDashboardData($period = '30days')
    {
        // This would query the analytics_events table
        // For now, return sample data structure
        
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$period}"));

        return [
            'overview' => [
                'page_views' => $this->getPageViews($startDate, $endDate),
                'unique_visitors' => $this->getUniqueVisitors($startDate, $endDate),
                'bounce_rate' => $this->getBounceRate($startDate, $endDate),
                'avg_session_duration' => $this->getAverageSessionDuration($startDate, $endDate)
            ],
            'top_pages' => $this->getTopPages($startDate, $endDate),
            'top_referrers' => $this->getTopReferrers($startDate, $endDate),
            'search_terms' => $this->getTopSearchTerms($startDate, $endDate),
            'daily_stats' => $this->getDailyStats($startDate, $endDate),
            'real_time' => $this->getRealTimeData()
        ];
    }

    /**
     * Get page views
     */
    protected function getPageViews($startDate, $endDate)
    {
        // Query analytics_events table
        // SELECT COUNT(*) FROM analytics_events WHERE type = 'page_view' AND created_at BETWEEN ? AND ?
        return rand(1000, 5000); // Sample data
    }

    /**
     * Get unique visitors
     */
    protected function getUniqueVisitors($startDate, $endDate)
    {
        // Query for unique sessions or IPs
        return rand(500, 2000); // Sample data
    }

    /**
     * Get bounce rate
     */
    protected function getBounceRate($startDate, $endDate)
    {
        // Calculate sessions with only one page view
        return rand(30, 70) . '%'; // Sample data
    }

    /**
     * Get average session duration
     */
    protected function getAverageSessionDuration($startDate, $endDate)
    {
        // Calculate from session start/end times
        return rand(120, 300) . ' seconds'; // Sample data
    }

    /**
     * Get top pages
     */
    protected function getTopPages($startDate, $endDate)
    {
        // Sample data - would query actual analytics
        return [
            ['page' => '/', 'views' => rand(500, 1000), 'title' => 'Home Page'],
            ['page' => '/about', 'views' => rand(200, 400), 'title' => 'About Us'],
            ['page' => '/contact', 'views' => rand(100, 300), 'title' => 'Contact'],
            ['page' => '/blog', 'views' => rand(300, 600), 'title' => 'Blog'],
            ['page' => '/services', 'views' => rand(150, 350), 'title' => 'Services']
        ];
    }

    /**
     * Get top referrers
     */
    protected function getTopReferrers($startDate, $endDate)
    {
        return [
            ['referrer' => 'google.com', 'visits' => rand(400, 800)],
            ['referrer' => 'facebook.com', 'visits' => rand(100, 300)],
            ['referrer' => 'twitter.com', 'visits' => rand(50, 150)],
            ['referrer' => 'linkedin.com', 'visits' => rand(30, 100)],
            ['referrer' => 'direct', 'visits' => rand(200, 500)]
        ];
    }

    /**
     * Get top search terms
     */
    protected function getTopSearchTerms($startDate, $endDate)
    {
        return [
            ['term' => 'wordpress', 'searches' => rand(50, 100)],
            ['term' => 'cms', 'searches' => rand(30, 80)],
            ['term' => 'web development', 'searches' => rand(20, 60)],
            ['term' => 'php', 'searches' => rand(15, 40)],
            ['term' => 'tutorial', 'searches' => rand(10, 30)]
        ];
    }

    /**
     * Get daily statistics
     */
    protected function getDailyStats($startDate, $endDate)
    {
        $stats = [];
        $current = strtotime($startDate);
        $end = strtotime($endDate);

        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $stats[] = [
                'date' => $date,
                'page_views' => rand(50, 200),
                'unique_visitors' => rand(20, 100),
                'sessions' => rand(30, 150)
            ];
            $current = strtotime('+1 day', $current);
        }

        return $stats;
    }

    /**
     * Get real-time data
     */
    protected function getRealTimeData()
    {
        return [
            'active_users' => rand(5, 50),
            'active_pages' => [
                ['page' => '/', 'users' => rand(1, 10)],
                ['page' => '/blog', 'users' => rand(1, 8)],
                ['page' => '/about', 'users' => rand(1, 5)]
            ],
            'traffic_sources' => [
                'organic' => rand(40, 70),
                'direct' => rand(20, 40),
                'social' => rand(5, 20),
                'referral' => rand(5, 15)
            ]
        ];
    }

    /**
     * Get client IP address
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
     * Get current URL
     */
    protected function getCurrentUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        return $protocol . $host . $uri;
    }

    /**
     * Generate Google Tag Manager code
     */
    public function getGTMCode($containerId = null)
    {
        $containerId = $containerId ?: config('analytics.gtm.container_id');
        
        if (!$containerId) {
            return '';
        }

        return "
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','{$containerId}');</script>
<!-- End Google Tag Manager -->

<!-- Google Tag Manager (noscript) -->
<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$containerId}\"
height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->";
    }

    /**
     * Generate Facebook Pixel code
     */
    public function getFacebookPixelCode($pixelId = null)
    {
        $pixelId = $pixelId ?: config('analytics.facebook.pixel_id');
        
        if (!$pixelId) {
            return '';
        }

        return "
<!-- Facebook Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$pixelId}');
fbq('track', 'PageView');
</script>
<noscript><img height=\"1\" width=\"1\" style=\"display:none\"
src=\"https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1\"
/></noscript>
<!-- End Facebook Pixel Code -->";
    }

    /**
     * Check if analytics is configured
     */
    public function isConfigured()
    {
        return !empty($this->gaTrackingId);
    }

    /**
     * Validate tracking ID format
     */
    public function validateTrackingId($trackingId)
    {
        // Google Analytics 4 format: G-XXXXXXXXXX
        // Universal Analytics format: UA-XXXXXXXX-X
        return preg_match('/^(G-[A-Z0-9]{10}|UA-[0-9]{4,9}-[0-9]{1,4})$/', $trackingId);
    }

    /**
     * Get analytics configuration for admin
     */
    public function getConfiguration()
    {
        return [
            'google_analytics' => [
                'tracking_id' => config('analytics.google.tracking_id'),
                'anonymize_ip' => config('analytics.google.anonymize_ip', true),
                'enhanced_ecommerce' => config('analytics.google.enhanced_ecommerce', false),
                'allow_ad_features' => config('analytics.google.allow_ad_features', false)
            ],
            'google_tag_manager' => [
                'container_id' => config('analytics.gtm.container_id')
            ],
            'facebook_pixel' => [
                'pixel_id' => config('analytics.facebook.pixel_id')
            ]
        ];
    }

    /**
     * Update analytics configuration
     */
    public function updateConfiguration($config)
    {
        $envUpdates = [];

        if (isset($config['google_analytics']['tracking_id'])) {
            $envUpdates['ANALYTICS_GA_TRACKING_ID'] = $config['google_analytics']['tracking_id'];
        }

        if (isset($config['google_tag_manager']['container_id'])) {
            $envUpdates['ANALYTICS_GTM_CONTAINER_ID'] = $config['google_tag_manager']['container_id'];
        }

        if (isset($config['facebook_pixel']['pixel_id'])) {
            $envUpdates['ANALYTICS_FB_PIXEL_ID'] = $config['facebook_pixel']['pixel_id'];
        }

        // Update .env file
        $this->updateEnvFile($envUpdates);

        return true;
    }

    /**
     * Update .env file
     */
    protected function updateEnvFile($updates)
    {
        $envPath = ROOT_PATH . '/.env';
        $envContent = file_exists($envPath) ? file_get_contents($envPath) : '';

        foreach ($updates as $key => $value) {
            $pattern = "/^{$key}=.*/m";
            $replacement = "{$key}={$value}";

            if (preg_match($pattern, $envContent)) {
                $envContent = preg_replace($pattern, $replacement, $envContent);
            } else {
                $envContent .= "\n{$replacement}";
            }
        }

        file_put_contents($envPath, $envContent);
    }
}