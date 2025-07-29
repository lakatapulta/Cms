<?php

namespace FlexCMS\Controllers;

use FlexCMS\Models\Post;
use FlexCMS\Models\Page;
use FlexCMS\Models\Category;
use FlexCMS\Models\User;
use Symfony\Component\HttpFoundation\Request;

class SearchController
{
    /**
     * Search everything
     */
    public function index(Request $request)
    {
        $query = trim($request->query->get('q', ''));
        $type = $request->query->get('type', 'all'); // all, posts, pages, users
        $page = (int) $request->query->get('page', 1);
        $perPage = 10;

        if (empty($query)) {
            return view('search/index.twig', [
                'page_title' => 'Search',
                'query' => '',
                'results' => [],
                'total' => 0,
                'type' => $type
            ]);
        }

        $results = [];
        $total = 0;

        switch ($type) {
            case 'posts':
                $results = $this->searchPosts($query, $page, $perPage);
                $total = $this->countPosts($query);
                break;
            
            case 'pages':
                $results = $this->searchPages($query, $page, $perPage);
                $total = $this->countPages($query);
                break;
            
            case 'users':
                if (user_can('view_users')) {
                    $results = $this->searchUsers($query, $page, $perPage);
                    $total = $this->countUsers($query);
                }
                break;
            
            default: // 'all'
                $results = $this->searchAll($query, $page, $perPage);
                $total = $this->countAll($query);
                break;
        }

        $totalPages = ceil($total / $perPage);

        // Log search query
        logger()->info('Search performed', [
            'query' => $query,
            'type' => $type,
            'results_count' => count($results),
            'user_id' => user() ? user()->id : null
        ]);

        return view('search/results.twig', [
            'page_title' => "Search results for: {$query}",
            'query' => $query,
            'type' => $type,
            'results' => $results,
            'total' => $total,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage,
                'total' => $total,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => max(1, $page - 1),
                'next_page' => min($totalPages, $page + 1)
            ],
            'popular_searches' => $this->getPopularSearches()
        ]);
    }

    /**
     * Search posts
     */
    protected function searchPosts($query, $page, $perPage)
    {
        $offset = ($page - 1) * $perPage;
        
        return Post::published()
            ->with('author', 'categories')
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%")
                  ->orWhereJsonContains('tags', $query);
            })
            ->orderByRaw("
                CASE 
                    WHEN title LIKE ? THEN 1
                    WHEN excerpt LIKE ? THEN 2
                    WHEN content LIKE ? THEN 3
                    ELSE 4
                END
            ", ["%{$query}%", "%{$query}%", "%{$query}%"])
            ->orderBy('published_at', 'DESC')
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(function($post) use ($query) {
                return [
                    'type' => 'post',
                    'title' => $post->title,
                    'excerpt' => $this->highlightQuery($post->excerpt, $query),
                    'url' => $post->url,
                    'author' => $post->author->display_name,
                    'date' => $post->published_at->format('M j, Y'),
                    'categories' => $post->categories->pluck('name')->toArray(),
                    'featured_image' => $post->featured_image_url
                ];
            });
    }

    /**
     * Search pages
     */
    protected function searchPages($query, $page, $perPage)
    {
        $offset = ($page - 1) * $perPage;
        
        return Page::published()
            ->with('author')
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN title LIKE ? THEN 1
                    WHEN excerpt LIKE ? THEN 2
                    WHEN content LIKE ? THEN 3
                    ELSE 4
                END
            ", ["%{$query}%", "%{$query}%", "%{$query}%"])
            ->orderBy('updated_at', 'DESC')
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(function($page) use ($query) {
                return [
                    'type' => 'page',
                    'title' => $page->title,
                    'excerpt' => $this->highlightQuery($page->excerpt, $query),
                    'url' => $page->url,
                    'author' => $page->author->display_name,
                    'date' => $page->updated_at->format('M j, Y')
                ];
            });
    }

    /**
     * Search users
     */
    protected function searchUsers($query, $page, $perPage)
    {
        $offset = ($page - 1) * $perPage;
        
        return User::active()
            ->where(function($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")
                  ->orWhere('display_name', 'like', "%{$query}%")
                  ->orWhere('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('bio', 'like', "%{$query}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN username LIKE ? THEN 1
                    WHEN display_name LIKE ? THEN 2
                    WHEN CONCAT(first_name, ' ', last_name) LIKE ? THEN 3
                    ELSE 4
                END
            ", ["%{$query}%", "%{$query}%", "%{$query}%"])
            ->skip($offset)
            ->take($perPage)
            ->get()
            ->map(function($user) use ($query) {
                return [
                    'type' => 'user',
                    'title' => $user->display_name,
                    'excerpt' => $this->highlightQuery($user->bio ?: "Member since {$user->created_at->format('M Y')}", $query),
                    'url' => $user->profile_url,
                    'author' => $user->role_info['name'] ?? $user->role,
                    'date' => "Joined {$user->created_at->format('M j, Y')}",
                    'avatar' => $user->avatar_url
                ];
            });
    }

    /**
     * Search all content types
     */
    protected function searchAll($query, $page, $perPage)
    {
        $results = collect();
        
        // Search posts (limit to 5)
        $posts = $this->searchPosts($query, 1, 5);
        $results = $results->merge($posts);
        
        // Search pages (limit to 3)
        $pages = $this->searchPages($query, 1, 3);
        $results = $results->merge($pages);
        
        // Search users if permitted (limit to 2)
        if (user_can('view_users')) {
            $users = $this->searchUsers($query, 1, 2);
            $results = $results->merge($users);
        }
        
        // Sort by relevance and paginate
        $offset = ($page - 1) * $perPage;
        return $results->slice($offset, $perPage)->values();
    }

    /**
     * Count posts matching query
     */
    protected function countPosts($query)
    {
        return Post::published()
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%")
                  ->orWhereJsonContains('tags', $query);
            })
            ->count();
    }

    /**
     * Count pages matching query
     */
    protected function countPages($query)
    {
        return Page::published()
            ->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('content', 'like', "%{$query}%")
                  ->orWhere('excerpt', 'like', "%{$query}%");
            })
            ->count();
    }

    /**
     * Count users matching query
     */
    protected function countUsers($query)
    {
        return User::active()
            ->where(function($q) use ($query) {
                $q->where('username', 'like', "%{$query}%")
                  ->orWhere('display_name', 'like', "%{$query}%")
                  ->orWhere('first_name', 'like', "%{$query}%")
                  ->orWhere('last_name', 'like', "%{$query}%")
                  ->orWhere('bio', 'like', "%{$query}%");
            })
            ->count();
    }

    /**
     * Count all content matching query
     */
    protected function countAll($query)
    {
        $count = $this->countPosts($query) + $this->countPages($query);
        
        if (user_can('view_users')) {
            $count += $this->countUsers($query);
        }
        
        return $count;
    }

    /**
     * Highlight search query in text
     */
    protected function highlightQuery($text, $query)
    {
        if (empty($text) || empty($query)) {
            return $text;
        }
        
        $highlighted = preg_replace(
            "/(" . preg_quote($query, '/') . ")/i",
            '<mark>$1</mark>',
            $text
        );
        
        return $highlighted;
    }

    /**
     * Get popular search queries
     */
    protected function getPopularSearches()
    {
        // This would typically come from a search_logs table
        // For now, return some example popular searches
        return [
            'WordPress',
            'PHP',
            'Tutorial',
            'JavaScript',
            'CSS',
            'HTML',
            'Bootstrap',
            'API'
        ];
    }

    /**
     * Autocomplete API endpoint
     */
    public function autocomplete(Request $request)
    {
        $query = trim($request->query->get('q', ''));
        
        if (strlen($query) < 2) {
            return response()->json([]);
        }
        
        $suggestions = [];
        
        // Post titles
        $postTitles = Post::published()
            ->where('title', 'like', "%{$query}%")
            ->limit(5)
            ->pluck('title')
            ->toArray();
            
        foreach ($postTitles as $title) {
            $suggestions[] = [
                'text' => $title,
                'type' => 'post'
            ];
        }
        
        // Categories
        $categories = Category::where('name', 'like', "%{$query}%")
            ->limit(3)
            ->pluck('name')
            ->toArray();
            
        foreach ($categories as $category) {
            $suggestions[] = [
                'text' => $category,
                'type' => 'category'
            ];
        }
        
        // Tags (from popular tags)
        $popularTags = $this->getPopularTags();
        $matchingTags = array_filter(array_keys($popularTags), function($tag) use ($query) {
            return stripos($tag, $query) !== false;
        });
        
        foreach (array_slice($matchingTags, 0, 3) as $tag) {
            $suggestions[] = [
                'text' => $tag,
                'type' => 'tag'
            ];
        }
        
        return response()->json(array_slice($suggestions, 0, 10));
    }

    /**
     * Get popular tags helper
     */
    protected function getPopularTags()
    {
        $posts = Post::published()
            ->whereNotNull('tags')
            ->pluck('tags');

        $tagCounts = [];
        
        foreach ($posts as $postTags) {
            if (is_array($postTags)) {
                foreach ($postTags as $tag) {
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            }
        }

        arsort($tagCounts);
        return $tagCounts;
    }
}