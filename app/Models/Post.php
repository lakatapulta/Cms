<?php

namespace FlexCMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $table = 'cms_posts';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'type',
        'author_id',
        'published_at',
        'views_count',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'tags',
        'custom_fields'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'tags' => 'array',
        'custom_fields' => 'array'
    ];

    /**
     * Author relationship
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Categories relationship
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'cms_post_categories', 'post_id', 'category_id');
    }

    /**
     * Comments relationship
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /**
     * Approved comments
     */
    public function approvedComments()
    {
        return $this->comments()->approved();
    }

    /**
     * Scope: Published posts
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                    ->where('published_at', '<=', now());
    }

    /**
     * Scope: Draft posts
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope: By type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: By author
     */
    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    /**
     * Scope: Popular posts
     */
    public function scopePopular($query, $days = 30)
    {
        return $query->where('published_at', '>=', now()->subDays($days))
                    ->orderBy('views_count', 'DESC');
    }

    /**
     * Scope: Recent posts
     */
    public function scopeRecent($query, $limit = 5)
    {
        return $query->published()
                    ->orderBy('published_at', 'DESC')
                    ->limit($limit);
    }

    /**
     * Scope: Featured posts
     */
    public function scopeFeatured($query)
    {
        return $query->whereJsonContains('custom_fields->featured', true);
    }

    /**
     * Scope: Search
     */
    public function scopeSearch($query, $term)
    {
        return $query->where(function($q) use ($term) {
            $q->where('title', 'like', "%{$term}%")
              ->orWhere('content', 'like', "%{$term}%")
              ->orWhere('excerpt', 'like', "%{$term}%");
        });
    }

    /**
     * Accessor: Excerpt
     */
    public function getExcerptAttribute($value)
    {
        if ($value) {
            return $value;
        }

        // Auto-generate excerpt from content
        $content = strip_tags($this->content);
        return strlen($content) > 160 ? substr($content, 0, 160) . '...' : $content;
    }

    /**
     * Accessor: Reading time
     */
    public function getReadingTimeAttribute()
    {
        $wordCount = str_word_count(strip_tags($this->content));
        $wordsPerMinute = 200; // Average reading speed
        $minutes = ceil($wordCount / $wordsPerMinute);
        
        return $minutes . ' min read';
    }

    /**
     * Accessor: URL
     */
    public function getUrlAttribute()
    {
        return url("/posts/{$this->slug}");
    }

    /**
     * Accessor: Edit URL
     */
    public function getEditUrlAttribute()
    {
        return url("/admin/posts/{$this->id}/edit");
    }

    /**
     * Accessor: Featured image URL
     */
    public function getFeaturedImageUrlAttribute()
    {
        if (!$this->featured_image) {
            return null;
        }

        return asset('uploads/posts/' . $this->featured_image);
    }

    /**
     * Accessor: Is published
     */
    public function getIsPublishedAttribute()
    {
        return $this->status === 'published' && $this->published_at <= now();
    }

    /**
     * Accessor: Is draft
     */
    public function getIsDraftAttribute()
    {
        return $this->status === 'draft';
    }

    /**
     * Accessor: Is scheduled
     */
    public function getIsScheduledAttribute()
    {
        return $this->status === 'published' && $this->published_at > now();
    }

    /**
     * Accessor: Status label
     */
    public function getStatusLabelAttribute()
    {
        switch ($this->status) {
            case 'published':
                return $this->published_at > now() ? 'Scheduled' : 'Published';
            case 'draft':
                return 'Draft';
            case 'private':
                return 'Private';
            default:
                return ucfirst($this->status);
        }
    }

    /**
     * Accessor: Comments count
     */
    public function getCommentsCountAttribute()
    {
        return $this->comments()->approved()->count();
    }

    /**
     * Accessor: Is featured
     */
    public function getIsFeaturedAttribute()
    {
        return $this->custom_fields['featured'] ?? false;
    }

    /**
     * Mutator: Slug
     */
    public function setSlugAttribute($value)
    {
        if (!$value && $this->title) {
            $value = $this->generateSlug($this->title);
        }
        
        $this->attributes['slug'] = $value;
    }

    /**
     * Mutator: Title
     */
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        
        // Auto-generate slug if not set
        if (!$this->slug) {
            $this->attributes['slug'] = $this->generateSlug($value);
        }
    }

    /**
     * Boot model
     */
    protected static function boot()
    {
        parent::boot();

        // Set published_at when publishing
        static::saving(function ($post) {
            if ($post->status === 'published' && !$post->published_at) {
                $post->published_at = now();
            }
        });

        // Log when post is created
        static::created(function ($post) {
            logger()->info('Post created', [
                'post_id' => $post->id,
                'title' => $post->title,
                'author_id' => $post->author_id
            ]);
        });

        // Log when post is published
        static::updated(function ($post) {
            if ($post->wasChanged('status') && $post->status === 'published') {
                logger()->info('Post published', [
                    'post_id' => $post->id,
                    'title' => $post->title
                ]);
            }
        });
    }

    /**
     * Generate unique slug
     */
    protected function generateSlug($title)
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->where('id', '!=', $this->id ?? 0)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Get related posts
     */
    public function getRelated($limit = 4)
    {
        $categoryIds = $this->categories->pluck('id')->toArray();
        
        $query = static::published()
            ->where('id', '!=', $this->id)
            ->with('author');

        if (!empty($categoryIds)) {
            $query->whereHas('categories', function($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }

        return $query->orderBy('published_at', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get next post
     */
    public function getNext()
    {
        return static::published()
            ->where('published_at', '>', $this->published_at)
            ->orderBy('published_at', 'ASC')
            ->first();
    }

    /**
     * Get previous post
     */
    public function getPrevious()
    {
        return static::published()
            ->where('published_at', '<', $this->published_at)
            ->orderBy('published_at', 'DESC')
            ->first();
    }

    /**
     * Check if user can edit this post
     */
    public function canEdit($user)
    {
        if (!$user) {
            return false;
        }

        // Author can edit own posts
        if ($this->author_id === $user->id && $user->can('edit_own_posts')) {
            return true;
        }

        // Editors and above can edit any post
        return $user->can('edit_posts');
    }

    /**
     * Check if user can delete this post
     */
    public function canDelete($user)
    {
        if (!$user) {
            return false;
        }

        // Author can delete own posts
        if ($this->author_id === $user->id && $user->can('delete_own_posts')) {
            return true;
        }

        // Editors and above can delete any post
        return $user->can('delete_posts');
    }

    /**
     * Mark as featured
     */
    public function markAsFeatured()
    {
        $customFields = $this->custom_fields ?? [];
        $customFields['featured'] = true;
        $this->update(['custom_fields' => $customFields]);
    }

    /**
     * Unmark as featured
     */
    public function unmarkAsFeatured()
    {
        $customFields = $this->custom_fields ?? [];
        unset($customFields['featured']);
        $this->update(['custom_fields' => $customFields]);
    }

    /**
     * Publish the post
     */
    public function publish($publishAt = null)
    {
        $this->update([
            'status' => 'published',
            'published_at' => $publishAt ?: now()
        ]);
    }

    /**
     * Make draft
     */
    public function makeDraft()
    {
        $this->update(['status' => 'draft']);
    }

    /**
     * Schedule publication
     */
    public function schedule($publishAt)
    {
        $this->update([
            'status' => 'published',
            'published_at' => $publishAt
        ]);
    }

    /**
     * Get post statistics
     */
    public function getStats()
    {
        return [
            'views' => $this->views_count,
            'comments' => $this->comments_count,
            'shares' => $this->custom_fields['shares'] ?? 0,
            'likes' => $this->custom_fields['likes'] ?? 0,
            'reading_time' => $this->reading_time,
            'word_count' => str_word_count(strip_tags($this->content))
        ];
    }

    /**
     * Increment view count
     */
    public function incrementViews()
    {
        $this->increment('views_count');
    }

    /**
     * Add tag
     */
    public function addTag($tag)
    {
        $tags = $this->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->update(['tags' => $tags]);
        }
    }

    /**
     * Remove tag
     */
    public function removeTag($tag)
    {
        $tags = $this->tags ?? [];
        $tags = array_filter($tags, function($t) use ($tag) {
            return $t !== $tag;
        });
        $this->update(['tags' => array_values($tags)]);
    }
}