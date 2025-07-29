<?php

namespace FlexCMS\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $table = 'cms_categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'meta_title',
        'meta_description',
        'color',
        'icon',
        'sort_order'
    ];

    /**
     * Parent category
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Child categories
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Posts in this category
     */
    public function posts()
    {
        return $this->belongsToMany(Post::class, 'cms_post_categories', 'category_id', 'post_id');
    }

    /**
     * Published posts in this category
     */
    public function publishedPosts()
    {
        return $this->posts()->published();
    }

    /**
     * Scope: Root categories (no parent)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Accessor: URL
     */
    public function getUrlAttribute()
    {
        return url("/category/{$this->slug}");
    }

    /**
     * Accessor: Posts count
     */
    public function getPostsCountAttribute()
    {
        return $this->publishedPosts()->count();
    }

    /**
     * Get category tree
     */
    public static function getTree()
    {
        return static::root()
            ->with('children')
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get all descendants
     */
    public function getAllDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getAllDescendants());
        }
        
        return $descendants;
    }

    /**
     * Check if category has children
     */
    public function hasChildren()
    {
        return $this->children()->count() > 0;
    }
}