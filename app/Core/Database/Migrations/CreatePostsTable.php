<?php

use Illuminate\Database\Schema\Blueprint;

class CreatePostsTable
{
    /**
     * Run the migration
     */
    public function up()
    {
        $schema = app('database')->schema();
        
        if (!$schema->hasTable('posts')) {
            $schema->create('posts', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('excerpt')->nullable();
                $table->longText('content');
                $table->string('featured_image')->nullable();
                $table->enum('status', ['draft', 'published', 'private', 'scheduled'])->default('draft');
                $table->enum('type', ['post', 'page'])->default('post');
                $table->foreignId('author_id')->constrained('users')->onDelete('cascade');
                $table->timestamp('published_at')->nullable();
                $table->json('meta')->nullable();
                $table->integer('views_count')->default(0);
                $table->integer('comments_count')->default(0);
                $table->timestamps();
                
                $table->index(['status', 'type', 'published_at']);
                $table->index('slug');
                $table->index('author_id');
                $table->fullText(['title', 'content']);
            });
        }
    }

    /**
     * Reverse the migration
     */
    public function down()
    {
        $schema = app('database')->schema();
        $schema->dropIfExists('posts');
    }
}