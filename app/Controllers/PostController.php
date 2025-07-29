<?php

namespace FlexCMS\Controllers;

use FlexCMS\Models\Post;
use FlexCMS\Models\Category;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PostController
{
    /**
     * Display blog index
     */
    public function index(Request $request)
    {
        $page = (int) $request->query->get('page', 1);
        $perPage = 12;
        $category = $request->query->get('category');
        $tag = $request->query->get('tag');
        $search = $request->query->get('search');

        // Build query
        $query = Post::published()
            ->with(['author', 'categories'])
            ->orderBy('published_at', 'DESC');

        // Filter by category
        if ($category) {
            $query->whereHas('categories', function($q) use ($category) {
                $q->where('slug', $category);
            });
        }

        // Filter by tag
        if ($tag) {
            $query->whereJsonContains('tags', $tag);
        }

        // Search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('excerpt', 'like', "%{$search}%");
            });
        }

        // Pagination
        $offset = ($page - 1) * $perPage;
        $posts = $query->skip($offset)->take($perPage)->get();
        $total = $query->count();
        $totalPages = ceil($total / $perPage);

        // Get categories for sidebar
        $categories = Category::withCount('posts')
            ->orderBy('posts_count', 'DESC')
            ->take(10)
            ->get();

        // Get popular tags
        $popularTags = $this->getPopularTags();

        // Get recent posts for sidebar
        $recentPosts = Post::published()
            ->orderBy('published_at', 'DESC')
            ->take(5)
            ->get();

        return view('blog/index.twig', [
            'page_title' => $search ? "Search results for: {$search}" : 'Blog',
            'meta_description' => 'Latest blog posts and articles',
            'posts' => $posts,
            'categories' => $categories,
            'popular_tags' => $popularTags,
            'recent_posts' => $recentPosts,
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
            'current_category' => $category,
            'current_tag' => $tag,
            'search_query' => $search
        ]);
    }

    /**
     * Display single post
     */
    public function show(Request $request, $slug)
    {
        $post = Post::published()
            ->with(['author', 'categories', 'comments' => function($q) {
                $q->approved()->with('user')->orderBy('created_at', 'ASC');
            }])
            ->where('slug', $slug)
            ->first();

        if (!$post) {
            return $this->notFound();
        }

        // Increment view count
        $post->increment('views_count');

        // Get related posts
        $relatedPosts = $this->getRelatedPosts($post);

        // Get previous/next posts
        $prevPost = Post::published()
            ->where('published_at', '<', $post->published_at)
            ->orderBy('published_at', 'DESC')
            ->first();

        $nextPost = Post::published()
            ->where('published_at', '>', $post->published_at)
            ->orderBy('published_at', 'ASC')
            ->first();

        return view('blog/single.twig', [
            'page_title' => $post->title,
            'meta_description' => $post->excerpt ?: substr(strip_tags($post->content), 0, 160),
            'meta_keywords' => $post->meta_keywords,
            'meta_image' => $post->featured_image ? asset('uploads/posts/' . $post->featured_image) : null,
            'post' => $post,
            'related_posts' => $relatedPosts,
            'prev_post' => $prevPost,
            'next_post' => $nextPost,
            'canonical_url' => url("/posts/{$post->slug}"),
            'structured_data' => $this->getPostStructuredData($post)
        ]);
    }

    /**
     * Display posts by category
     */
    public function category(Request $request, $slug)
    {
        $category = Category::where('slug', $slug)->first();
        
        if (!$category) {
            return $this->notFound();
        }

        $page = (int) $request->query->get('page', 1);
        $perPage = 12;

        $query = $category->posts()
            ->published()
            ->with('author')
            ->orderBy('published_at', 'DESC');

        $offset = ($page - 1) * $perPage;
        $posts = $query->skip($offset)->take($perPage)->get();
        $total = $query->count();
        $totalPages = ceil($total / $perPage);

        return view('blog/category.twig', [
            'page_title' => $category->name,
            'meta_description' => $category->description ?: "Posts in {$category->name} category",
            'category' => $category,
            'posts' => $posts,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage,
                'total' => $total,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => max(1, $page - 1),
                'next_page' => min($totalPages, $page + 1)
            ]
        ]);
    }

    /**
     * Display posts by tag
     */
    public function tag(Request $request, $tag)
    {
        $page = (int) $request->query->get('page', 1);
        $perPage = 12;

        $query = Post::published()
            ->whereJsonContains('tags', $tag)
            ->with('author')
            ->orderBy('published_at', 'DESC');

        $offset = ($page - 1) * $perPage;
        $posts = $query->skip($offset)->take($perPage)->get();
        $total = $query->count();
        $totalPages = ceil($total / $perPage);

        return view('blog/tag.twig', [
            'page_title' => "Posts tagged: {$tag}",
            'meta_description' => "Posts tagged with {$tag}",
            'tag' => $tag,
            'posts' => $posts,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'per_page' => $perPage,
                'total' => $total,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_page' => max(1, $page - 1),
                'next_page' => min($totalPages, $page + 1)
            ]
        ]);
    }

    /**
     * RSS Feed
     */
    public function rss()
    {
        $posts = Post::published()
            ->with('author')
            ->orderBy('published_at', 'DESC')
            ->take(20)
            ->get();

        $xml = view('blog/rss.xml.twig', [
            'posts' => $posts,
            'site_title' => config('app.name'),
            'site_description' => config('app.description'),
            'site_url' => config('app.url'),
            'build_date' => date('r')
        ]);

        return new Response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8'
        ]);
    }

    /**
     * Get related posts
     */
    protected function getRelatedPosts(Post $post, $limit = 4)
    {
        $categoryIds = $post->categories->pluck('id')->toArray();
        
        $query = Post::published()
            ->where('id', '!=', $post->id)
            ->with('author');

        if (!empty($categoryIds)) {
            $query->whereHas('categories', function($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        return $query->orderBy('published_at', 'DESC')
            ->take($limit)
            ->get();
    }

    /**
     * Get popular tags
     */
    protected function getPopularTags($limit = 20)
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
        return array_slice($tagCounts, 0, $limit, true);
    }

    /**
     * Get structured data for post
     */
    protected function getPostStructuredData(Post $post)
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->title,
            'description' => $post->excerpt,
            'image' => $post->featured_image ? asset('uploads/posts/' . $post->featured_image) : null,
            'author' => [
                '@type' => 'Person',
                'name' => $post->author->display_name
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
            'url' => url("/posts/{$post->slug}")
        ];
    }

    /**
     * Handle 404 not found
     */
    protected function notFound()
    {
        return new Response(
            view('errors/404.twig', ['page_title' => 'Page Not Found']),
            404
        );
    }
}