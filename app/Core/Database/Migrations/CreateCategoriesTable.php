<?php

use Illuminate\Database\Schema\Blueprint;

class CreateCategoriesTable
{
    /**
     * Run the migration
     */
    public function up()
    {
        $schema = app('database')->schema();
        
        if (!$schema->hasTable('categories')) {
            $schema->create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->string('color')->nullable();
                $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('set null');
                $table->integer('sort_order')->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
                
                $table->index(['parent_id', 'sort_order']);
                $table->index('slug');
            });
        }

        // Create pivot table for post-category relationships
        if (!$schema->hasTable('post_categories')) {
            $schema->create('post_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('post_id')->constrained('posts')->onDelete('cascade');
                $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
                $table->timestamps();
                
                $table->unique(['post_id', 'category_id']);
                $table->index('post_id');
                $table->index('category_id');
            });
        }
    }

    /**
     * Reverse the migration
     */
    public function down()
    {
        $schema = app('database')->schema();
        $schema->dropIfExists('post_categories');
        $schema->dropIfExists('categories');
    }
}