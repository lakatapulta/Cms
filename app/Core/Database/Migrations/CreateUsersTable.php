<?php

use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable
{
    /**
     * Run the migration
     */
    public function up()
    {
        $schema = app('database')->schema();
        
        if (!$schema->hasTable('users')) {
            $schema->create('users', function (Blueprint $table) {
                $table->id();
                $table->string('username')->unique();
                $table->string('email')->unique();
                $table->string('password');
                $table->string('first_name')->nullable();
                $table->string('last_name')->nullable();
                $table->string('display_name')->nullable();
                $table->text('bio')->nullable();
                $table->string('avatar')->nullable();
                $table->string('role')->default('user');
                $table->enum('status', ['active', 'inactive', 'banned'])->default('active');
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                
                $table->index(['role', 'status']);
                $table->index('email_verified_at');
            });
        }
    }

    /**
     * Reverse the migration
     */
    public function down()
    {
        $schema = app('database')->schema();
        $schema->dropIfExists('users');
    }
}