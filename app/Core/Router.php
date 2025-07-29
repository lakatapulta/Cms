<?php

namespace FlexCMS\Core;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Router
{
    /**
     * Registered routes
     */
    protected $routes = [];

    /**
     * Named routes
     */
    protected $namedRoutes = [];

    /**
     * Route groups
     */
    protected $groups = [];

    /**
     * Current group stack
     */
    protected $groupStack = [];

    /**
     * Add GET route
     */
    public function get($uri, $action, $name = null)
    {
        return $this->addRoute(['GET'], $uri, $action, $name);
    }

    /**
     * Add POST route
     */
    public function post($uri, $action, $name = null)
    {
        return $this->addRoute(['POST'], $uri, $action, $name);
    }

    /**
     * Add PUT route
     */
    public function put($uri, $action, $name = null)
    {
        return $this->addRoute(['PUT'], $uri, $action, $name);
    }

    /**
     * Add DELETE route
     */
    public function delete($uri, $action, $name = null)
    {
        return $this->addRoute(['DELETE'], $uri, $action, $name);
    }

    /**
     * Add PATCH route
     */
    public function patch($uri, $action, $name = null)
    {
        return $this->addRoute(['PATCH'], $uri, $action, $name);
    }

    /**
     * Add route for multiple methods
     */
    public function match($methods, $uri, $action, $name = null)
    {
        return $this->addRoute($methods, $uri, $action, $name);
    }

    /**
     * Add route for any method
     */
    public function any($uri, $action, $name = null)
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $uri, $action, $name);
    }

    /**
     * Add route group
     */
    public function group($attributes, $callback)
    {
        $this->groupStack[] = $attributes;
        
        call_user_func($callback, $this);
        
        array_pop($this->groupStack);
    }

    /**
     * Add route
     */
    protected function addRoute($methods, $uri, $action, $name = null)
    {
        $route = [
            'methods' => $methods,
            'uri' => $this->formatUri($uri),
            'action' => $action,
            'name' => $name,
            'middleware' => [],
            'parameters' => [],
        ];

        // Apply group attributes
        if (!empty($this->groupStack)) {
            $route = $this->mergeGroupAttributes($route);
        }

        $this->routes[] = $route;

        // Register named route
        if ($name) {
            $this->namedRoutes[$name] = $route;
        }

        return $route;
    }

    /**
     * Format URI
     */
    protected function formatUri($uri)
    {
        return '/' . trim($uri, '/');
    }

    /**
     * Merge group attributes
     */
    protected function mergeGroupAttributes($route)
    {
        foreach ($this->groupStack as $group) {
            if (isset($group['prefix'])) {
                $route['uri'] = '/' . trim($group['prefix'], '/') . $route['uri'];
            }

            if (isset($group['middleware'])) {
                $route['middleware'] = array_merge(
                    (array) $group['middleware'],
                    $route['middleware']
                );
            }

            if (isset($group['namespace'])) {
                if (is_string($route['action'])) {
                    $route['action'] = $group['namespace'] . '\\' . $route['action'];
                }
            }
        }

        return $route;
    }

    /**
     * Dispatch request
     */
    public function dispatch(Request $request)
    {
        $method = $request->getMethod();
        $uri = $request->getPathInfo();

        foreach ($this->routes as $route) {
            if ($this->matchRoute($route, $method, $uri)) {
                return $this->callRoute($route, $request);
            }
        }

        return $this->handleNotFound();
    }

    /**
     * Match route
     */
    protected function matchRoute($route, $method, $uri)
    {
        if (!in_array($method, $route['methods'])) {
            return false;
        }

        $pattern = $this->compileRoute($route['uri']);
        
        if (preg_match($pattern, $uri, $matches)) {
            $route['parameters'] = array_slice($matches, 1);
            return true;
        }

        return false;
    }

    /**
     * Compile route to regex pattern
     */
    protected function compileRoute($uri)
    {
        $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $uri);
        return '#^' . $pattern . '$#';
    }

    /**
     * Call route action
     */
    protected function callRoute($route, Request $request)
    {
        $action = $route['action'];

        if (is_callable($action)) {
            return call_user_func_array($action, [$request]);
        }

        if (is_string($action)) {
            return $this->callControllerAction($action, $request, $route['parameters']);
        }

        if (is_array($action) && count($action) === 2) {
            return $this->callControllerMethod($action[0], $action[1], $request, $route['parameters']);
        }

        throw new \Exception("Invalid route action");
    }

    /**
     * Call controller action (Class@method format)
     */
    protected function callControllerAction($action, Request $request, $parameters = [])
    {
        list($controller, $method) = explode('@', $action);
        return $this->callControllerMethod($controller, $method, $request, $parameters);
    }

    /**
     * Call controller method
     */
    protected function callControllerMethod($controller, $method, Request $request, $parameters = [])
    {
        if (!class_exists($controller)) {
            throw new \Exception("Controller {$controller} not found");
        }

        $instance = new $controller();

        if (!method_exists($instance, $method)) {
            throw new \Exception("Method {$method} not found in controller {$controller}");
        }

        return call_user_func_array([$instance, $method], array_merge([$request], $parameters));
    }

    /**
     * Handle 404 not found
     */
    protected function handleNotFound()
    {
        return new Response('<h1>404 - Page Not Found</h1>', 404);
    }

    /**
     * Generate URL for named route
     */
    public static function url($name, $parameters = [])
    {
        $router = app('router');
        
        if (!isset($router->namedRoutes[$name])) {
            throw new \Exception("Named route {$name} not found");
        }

        $route = $router->namedRoutes[$name];
        $uri = $route['uri'];

        // Replace parameters
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
        }

        return url($uri);
    }

    /**
     * Get all routes
     */
    public function getRoutes()
    {
        return $this->routes;
    }
}