<?php

use Illuminate\Database\Schema\Blueprint;

class CreateSettingsTable
{
    /**
     * Run the migration
     */
    public function up()
    {
        $schema = app('database')->schema();
        
        if (!$schema->hasTable('settings')) {
            $schema->create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->longText('value')->nullable();
                $table->string('type')->default('string'); // string, integer, boolean, json, array
                $table->string('group')->default('general');
                $table->text('description')->nullable();
                $table->boolean('autoload')->default(true);
                $table->timestamps();
                
                $table->index(['group', 'autoload']);
                $table->index('key');
            });
        }

        // Insert default settings
        $this->insertDefaultSettings();
    }

    /**
     * Insert default CMS settings
     */
    protected function insertDefaultSettings()
    {
        $connection = app('database')->connection();
        
        $defaultSettings = [
            // General settings
            ['key' => 'site_title', 'value' => 'FlexCMS', 'type' => 'string', 'group' => 'general', 'description' => 'Site title'],
            ['key' => 'site_description', 'value' => 'A flexible content management system', 'type' => 'string', 'group' => 'general', 'description' => 'Site description'],
            ['key' => 'site_url', 'value' => 'http://localhost', 'type' => 'string', 'group' => 'general', 'description' => 'Site URL'],
            ['key' => 'admin_email', 'value' => 'admin@flexcms.local', 'type' => 'string', 'group' => 'general', 'description' => 'Administrator email'],
            ['key' => 'timezone', 'value' => 'UTC', 'type' => 'string', 'group' => 'general', 'description' => 'Site timezone'],
            ['key' => 'date_format', 'value' => 'Y-m-d', 'type' => 'string', 'group' => 'general', 'description' => 'Date format'],
            ['key' => 'time_format', 'value' => 'H:i:s', 'type' => 'string', 'group' => 'general', 'description' => 'Time format'],
            
            // Theme settings
            ['key' => 'active_theme', 'value' => 'default', 'type' => 'string', 'group' => 'theme', 'description' => 'Active theme'],
            ['key' => 'posts_per_page', 'value' => '10', 'type' => 'integer', 'group' => 'theme', 'description' => 'Posts per page'],
            
            // Email settings
            ['key' => 'mail_driver', 'value' => 'smtp', 'type' => 'string', 'group' => 'email', 'description' => 'Mail driver'],
            ['key' => 'mail_from_name', 'value' => 'FlexCMS', 'type' => 'string', 'group' => 'email', 'description' => 'Mail from name'],
            ['key' => 'mail_from_email', 'value' => 'noreply@flexcms.local', 'type' => 'string', 'group' => 'email', 'description' => 'Mail from email'],
            
            // SEO settings
            ['key' => 'seo_meta_description', 'value' => 'FlexCMS - A flexible content management system', 'type' => 'text', 'group' => 'seo', 'description' => 'Default meta description'],
            ['key' => 'seo_meta_keywords', 'value' => 'cms, php, flexible, content management', 'type' => 'string', 'group' => 'seo', 'description' => 'Default meta keywords'],
            
            // Security settings
            ['key' => 'user_registration', 'value' => 'true', 'type' => 'boolean', 'group' => 'security', 'description' => 'Allow user registration'],
            ['key' => 'require_email_verification', 'value' => 'true', 'type' => 'boolean', 'group' => 'security', 'description' => 'Require email verification'],
            ['key' => 'max_login_attempts', 'value' => '5', 'type' => 'integer', 'group' => 'security', 'description' => 'Maximum login attempts'],
        ];

        foreach ($defaultSettings as $setting) {
            $exists = $connection->table('settings')->where('key', $setting['key'])->exists();
            
            if (!$exists) {
                $connection->table('settings')->insert(array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /**
     * Reverse the migration
     */
    public function down()
    {
        $schema = app('database')->schema();
        $schema->dropIfExists('settings');
    }
}