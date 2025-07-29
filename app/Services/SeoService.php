<?php

namespace FlexCMS\Services;

use FlexCMS\Models\Post;
use FlexCMS\Models\Page;
use FlexCMS\Models\Category;

class SeoService
{
    /**
     * Generate XML sitemap
     */
    public function generateSitemap()
    {
        $urls = [];
        $baseUrl = config('app.url');
        
        // Homepage
        $urls[] = [
            'loc' => $baseUrl,
            'lastmod' => date('c'),
            'changefreq' => 'daily',
            'priority' => '1.0'
        ];
        
        // Posts
        $posts = Post::published()->orderBy('updated_at', 'desc')->get();
        foreach ($posts as $post) {
            $urls[] = [
                'loc' => $baseUrl . '/posts/' . $post->slug,
                'lastmod' => $post->updated_at->format('c'),
                'changefreq' => 'weekly',
                'priority' => '0.8'
            ];
        }
        
        // Pages
        $pages = Page::published()->orderBy('updated_at', 'desc')->get();
        foreach ($pages as $page) {
            $urls[] = [
                'loc' => $baseUrl . '/pages/' . $page->slug,
                'lastmod' => $page->updated_at->format('c'),
                'changefreq' => 'monthly',
                'priority' => '0.7'
            ];
        }
        
        // Categories
        $categories = Category::all();
        foreach ($categories as $category) {
            $urls[] = [
                'loc' => $baseUrl . '/category/' . $category->slug,
                'lastmod' => date('c'),
                'changefreq' => 'weekly',
                'priority' => '0.6'
            ];
        }
        
        return $this->buildSitemapXml($urls);
    }
    
    /**
     * Build sitemap XML
     */
    protected function buildSitemapXml($urls)
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        
        foreach ($urls as $url) {
            $xml .= '<url>';
            $xml .= '<loc>' . htmlspecialchars($url['loc']) . '</loc>';
            $xml .= '<lastmod>' . $url['lastmod'] . '</lastmod>';
            $xml .= '<changefreq>' . $url['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $url['priority'] . '</priority>';
            $xml .= '</url>';
        }
        
        $xml .= '</urlset>';
        return $xml;
    }
    
    /**
     * Generate Schema.org structured data
     */
    public function generateStructuredData($type, $data)
    {
        switch ($type) {
            case 'article':
                return $this->articleSchema($data);
            case 'organization':
                return $this->organizationSchema($data);
            case 'website':
                return $this->websiteSchema($data);
            case 'breadcrumb':
                return $this->breadcrumbSchema($data);
            default:
                return null;
        }
    }
    
    /**
     * Article schema for blog posts
     */
    protected function articleSchema($post)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->excerpt,
            'image' => $post->featured_image_url,
            'author' => [
                '@type' => 'Person',
                'name' => $post->author->display_name,
                'url' => $post->author->profile_url
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => asset('images/logo.png')
                ]
            ],
            'datePublished' => $post->published_at->format('c'),
            'dateModified' => $post->updated_at->format('c'),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $post->url
            ]
        ];
    }
    
    /**
     * Organization schema
     */
    protected function organizationSchema($data)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('app.name'),
            'url' => config('app.url'),
            'logo' => asset('images/logo.png'),
            'description' => config('app.description'),
            'sameAs' => $data['social_links'] ?? []
        ];
    }
    
    /**
     * Website schema
     */
    protected function websiteSchema($data)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => config('app.url'),
            'description' => config('app.description'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => config('app.url') . '/search?q={search_term_string}',
                'query-input' => 'required name=search_term_string'
            ]
        ];
    }
    
    /**
     * Breadcrumb schema
     */
    protected function breadcrumbSchema($breadcrumbs)
    {
        $items = [];
        foreach ($breadcrumbs as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url']
            ];
        }
        
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items
        ];
    }
    
    /**
     * Generate Open Graph meta tags
     */
    public function generateOpenGraph($data)
    {
        $og = [
            'og:type' => $data['type'] ?? 'website',
            'og:title' => $data['title'] ?? config('app.name'),
            'og:description' => $data['description'] ?? config('app.description'),
            'og:url' => $data['url'] ?? config('app.url'),
            'og:site_name' => config('app.name'),
            'og:locale' => 'es_ES'
        ];
        
        if (isset($data['image'])) {
            $og['og:image'] = $data['image'];
            $og['og:image:width'] = '1200';
            $og['og:image:height'] = '630';
        }
        
        if (isset($data['article'])) {
            $og['article:author'] = $data['article']['author'];
            $og['article:published_time'] = $data['article']['published_time'];
            $og['article:modified_time'] = $data['article']['modified_time'];
            $og['article:section'] = $data['article']['section'];
            $og['article:tag'] = $data['article']['tags'] ?? [];
        }
        
        return $og;
    }
    
    /**
     * Generate Twitter Card meta tags
     */
    public function generateTwitterCard($data)
    {
        $twitter = [
            'twitter:card' => $data['card_type'] ?? 'summary_large_image',
            'twitter:title' => $data['title'] ?? config('app.name'),
            'twitter:description' => $data['description'] ?? config('app.description'),
            'twitter:site' => config('social.twitter_handle', '@flexcms')
        ];
        
        if (isset($data['image'])) {
            $twitter['twitter:image'] = $data['image'];
        }
        
        if (isset($data['creator'])) {
            $twitter['twitter:creator'] = $data['creator'];
        }
        
        return $twitter;
    }
    
    /**
     * Analyze SEO for content
     */
    public function analyzeSeo($content, $target_keyword = null)
    {
        $analysis = [
            'score' => 0,
            'issues' => [],
            'recommendations' => []
        ];
        
        // Title analysis
        $title_length = strlen($content['title'] ?? '');
        if ($title_length < 30) {
            $analysis['issues'][] = 'El título es demasiado corto (menos de 30 caracteres)';
        } elseif ($title_length > 60) {
            $analysis['issues'][] = 'El título es demasiado largo (más de 60 caracteres)';
        } else {
            $analysis['score'] += 20;
        }
        
        // Meta description analysis
        $desc_length = strlen($content['meta_description'] ?? '');
        if ($desc_length < 120) {
            $analysis['issues'][] = 'La meta descripción es demasiado corta (menos de 120 caracteres)';
        } elseif ($desc_length > 160) {
            $analysis['issues'][] = 'La meta descripción es demasiado larga (más de 160 caracteres)';
        } else {
            $analysis['score'] += 20;
        }
        
        // Content length analysis
        $content_length = strlen(strip_tags($content['content'] ?? ''));
        if ($content_length < 300) {
            $analysis['issues'][] = 'El contenido es demasiado corto (menos de 300 caracteres)';
        } else {
            $analysis['score'] += 20;
        }
        
        // Keyword analysis
        if ($target_keyword) {
            $keyword_density = $this->calculateKeywordDensity($content['content'], $target_keyword);
            if ($keyword_density < 0.5) {
                $analysis['recommendations'][] = "Aumentar la densidad de la palabra clave '{$target_keyword}'";
            } elseif ($keyword_density > 3) {
                $analysis['issues'][] = "La palabra clave '{$target_keyword}' aparece demasiado (keyword stuffing)";
            } else {
                $analysis['score'] += 20;
            }
        }
        
        // Images analysis
        if (isset($content['featured_image'])) {
            $analysis['score'] += 10;
        } else {
            $analysis['recommendations'][] = 'Agregar una imagen destacada mejorará el SEO';
        }
        
        // URL analysis
        if (isset($content['slug'])) {
            $slug_length = strlen($content['slug']);
            if ($slug_length > 50) {
                $analysis['issues'][] = 'La URL es demasiado larga';
            } else {
                $analysis['score'] += 10;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Calculate keyword density
     */
    protected function calculateKeywordDensity($content, $keyword)
    {
        $content = strtolower(strip_tags($content));
        $keyword = strtolower($keyword);
        
        $total_words = str_word_count($content);
        $keyword_count = substr_count($content, $keyword);
        
        return $total_words > 0 ? ($keyword_count / $total_words) * 100 : 0;
    }
    
    /**
     * Generate robots.txt
     */
    public function generateRobotsTxt()
    {
        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /api/\n";
        $content .= "Disallow: /*.json$\n";
        $content .= "\n";
        $content .= "Sitemap: " . config('app.url') . "/sitemap.xml\n";
        
        return $content;
    }
    
    /**
     * Save sitemap to file
     */
    public function saveSitemap()
    {
        $xml = $this->generateSitemap();
        $path = public_path('sitemap.xml');
        file_put_contents($path, $xml);
        
        // Also save robots.txt
        $robots = $this->generateRobotsTxt();
        $robotsPath = public_path('robots.txt');
        file_put_contents($robotsPath, $robots);
        
        return true;
    }
    
    /**
     * Get SEO recommendations for site
     */
    public function getSiteRecommendations()
    {
        $recommendations = [];
        
        // Check if sitemap exists
        if (!file_exists(public_path('sitemap.xml'))) {
            $recommendations[] = 'Generar sitemap.xml para mejorar la indexación';
        }
        
        // Check if robots.txt exists
        if (!file_exists(public_path('robots.txt'))) {
            $recommendations[] = 'Crear robots.txt para guiar a los motores de búsqueda';
        }
        
        // Check SSL
        if (!str_starts_with(config('app.url'), 'https://')) {
            $recommendations[] = 'Configurar SSL (HTTPS) para mejorar la seguridad y SEO';
        }
        
        return $recommendations;
    }
}