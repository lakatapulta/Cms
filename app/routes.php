<?php

/**
 * FlexCMS Core Routes
 */

$router = app('router');

// Home page
$router->get('/', function() {
    return view('home.twig', [
        'page_title' => 'Home',
        'recent_posts' => [], // This would be populated by a controller
    ]);
}, 'home');

// Blog routes
$router->get('/posts', 'FlexCMS\Controllers\PostController@index', 'posts.index');
$router->get('/posts/{slug}', 'FlexCMS\Controllers\PostController@show', 'posts.show');

// Page routes
$router->get('/pages/{slug}', 'FlexCMS\Controllers\PageController@show', 'pages.show');

// User authentication routes
$router->get('/login', 'FlexCMS\Controllers\AuthController@showLogin', 'auth.login');
$router->post('/login', 'FlexCMS\Controllers\AuthController@login');
$router->get('/register', 'FlexCMS\Controllers\AuthController@showRegister', 'auth.register');
$router->post('/register', 'FlexCMS\Controllers\AuthController@register');
$router->post('/logout', 'FlexCMS\Controllers\AuthController@logout', 'auth.logout');

// User profile routes
$router->get('/profile', 'FlexCMS\Controllers\ProfileController@show', 'profile.show');
$router->post('/profile', 'FlexCMS\Controllers\ProfileController@update', 'profile.update');

// Search
$router->get('/search', 'FlexCMS\Controllers\SearchController@index', 'search.index');

// Contact
$router->get('/contact', 'FlexCMS\Controllers\ContactController@show', 'contact.show');
$router->post('/contact', 'FlexCMS\Controllers\ContactController@send', 'contact.send');

// About page
$router->get('/about', function() {
    return view('pages/about.twig', [
        'page_title' => 'About Us',
        'meta_description' => 'Learn more about FlexCMS and our mission.',
    ]);
}, 'about');

// Admin routes group
$router->group(['prefix' => 'admin', 'middleware' => 'auth.admin'], function($router) {
    // Dashboard
    $router->get('/', 'FlexCMS\Controllers\Admin\DashboardController@index', 'admin.dashboard');
    
    // Posts management
    $router->get('/posts', 'FlexCMS\Controllers\Admin\PostController@index', 'admin.posts.index');
    $router->get('/posts/create', 'FlexCMS\Controllers\Admin\PostController@create', 'admin.posts.create');
    $router->post('/posts', 'FlexCMS\Controllers\Admin\PostController@store', 'admin.posts.store');
    $router->get('/posts/{id}/edit', 'FlexCMS\Controllers\Admin\PostController@edit', 'admin.posts.edit');
    $router->put('/posts/{id}', 'FlexCMS\Controllers\Admin\PostController@update', 'admin.posts.update');
    $router->delete('/posts/{id}', 'FlexCMS\Controllers\Admin\PostController@destroy', 'admin.posts.destroy');
    
    // Pages management
    $router->get('/pages', 'FlexCMS\Controllers\Admin\PageController@index', 'admin.pages.index');
    $router->get('/pages/create', 'FlexCMS\Controllers\Admin\PageController@create', 'admin.pages.create');
    $router->post('/pages', 'FlexCMS\Controllers\Admin\PageController@store', 'admin.pages.store');
    $router->get('/pages/{id}/edit', 'FlexCMS\Controllers\Admin\PageController@edit', 'admin.pages.edit');
    $router->put('/pages/{id}', 'FlexCMS\Controllers\Admin\PageController@update', 'admin.pages.update');
    $router->delete('/pages/{id}', 'FlexCMS\Controllers\Admin\PageController@destroy', 'admin.pages.destroy');
    
    // Users management
    $router->get('/users', 'FlexCMS\Controllers\Admin\UserController@index', 'admin.users.index');
    $router->get('/users/create', 'FlexCMS\Controllers\Admin\UserController@create', 'admin.users.create');
    $router->post('/users', 'FlexCMS\Controllers\Admin\UserController@store', 'admin.users.store');
    $router->get('/users/{id}/edit', 'FlexCMS\Controllers\Admin\UserController@edit', 'admin.users.edit');
    $router->put('/users/{id}', 'FlexCMS\Controllers\Admin\UserController@update', 'admin.users.update');
    $router->delete('/users/{id}', 'FlexCMS\Controllers\Admin\UserController@destroy', 'admin.users.destroy');
    
    // Categories management
    $router->get('/categories', 'FlexCMS\Controllers\Admin\CategoryController@index', 'admin.categories.index');
    $router->post('/categories', 'FlexCMS\Controllers\Admin\CategoryController@store', 'admin.categories.store');
    $router->put('/categories/{id}', 'FlexCMS\Controllers\Admin\CategoryController@update', 'admin.categories.update');
    $router->delete('/categories/{id}', 'FlexCMS\Controllers\Admin\CategoryController@destroy', 'admin.categories.destroy');
    
    // Settings
    $router->get('/settings', 'FlexCMS\Controllers\Admin\SettingsController@index', 'admin.settings.index');
    $router->post('/settings', 'FlexCMS\Controllers\Admin\SettingsController@update', 'admin.settings.update');
    
    // Themes management
    $router->get('/themes', 'FlexCMS\Controllers\Admin\ThemeController@index', 'admin.themes.index');
    $router->post('/themes/{name}/activate', 'FlexCMS\Controllers\Admin\ThemeController@activate', 'admin.themes.activate');
    
    // Modules management
    $router->get('/modules', 'FlexCMS\Controllers\Admin\ModuleController@index', 'admin.modules.index');
    $router->post('/modules/{name}/activate', 'FlexCMS\Controllers\Admin\ModuleController@activate', 'admin.modules.activate');
    $router->post('/modules/{name}/deactivate', 'FlexCMS\Controllers\Admin\ModuleController@deactivate', 'admin.modules.deactivate');
    
    // File manager
    $router->get('/files', 'FlexCMS\Controllers\Admin\FileController@index', 'admin.files.index');
    $router->post('/files/upload', 'FlexCMS\Controllers\Admin\FileController@upload', 'admin.files.upload');
    $router->delete('/files/{path}', 'FlexCMS\Controllers\Admin\FileController@delete', 'admin.files.delete');
});

// API routes group
$router->group(['prefix' => 'api/v1'], function($router) {
    // Posts API
    $router->get('/posts', 'FlexCMS\Controllers\Api\PostController@index', 'api.posts.index');
    $router->get('/posts/{id}', 'FlexCMS\Controllers\Api\PostController@show', 'api.posts.show');
    
    // Categories API
    $router->get('/categories', 'FlexCMS\Controllers\Api\CategoryController@index', 'api.categories.index');
    
    // Search API
    $router->get('/search', 'FlexCMS\Controllers\Api\SearchController@search', 'api.search');
});

// Asset serving routes (for development)
if (config('app.env') === 'development') {
    $router->get('/themes/{theme}/assets/{path}', function($request, $theme, $path) {
        $filePath = ROOT_PATH . '/themes/' . $theme . '/assets/' . $path;
        
        if (file_exists($filePath)) {
            $mimeType = mime_content_type($filePath);
            return new Response(file_get_contents($filePath), 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }
        
        return new Response('File not found', 404);
    });
}