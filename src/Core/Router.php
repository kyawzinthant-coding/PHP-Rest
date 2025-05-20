<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    /**
     * Add a GET route.
     * @param string $path
     * @param callable|array $handler
     */
    public function get(string $path, $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    /**
     * Add a POST route.
     * @param string $path
     * @param callable|array $handler
     */
    public function post(string $path, $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    /**
     * Add a PUT route.
     * @param string $path
     * @param callable|array $handler
     */
    public function put(string $path, $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    /**
     * Add a DELETE route.
     * @param string $path
     * @param callable|array $handler
     */
    public function delete(string $path, $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Add a route to the internal routes array.
     * @param string $method
     * @param string $path
     * @param callable|array $handler
     */
    private function addRoute(string $method, string $path, $handler): void
    {
        // Convert dynamic segments like {id} to regex groups
        // Example: /api/v1/products/{id} becomes #^/api/v1/products/(?P<id>[^/]+)$#
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[^/]+)', $this->normalizePath($path));

        $pattern = '#^' . $pattern . '$#'; // Add start and end anchors for exact match

        $this->routes[$method][] = [
            'path' => $path, // Keep original path for debugging if needed
            'pattern' => $pattern, // The regex pattern for matching
            'handler' => $handler
        ];
    }

    /**
     * Normalize a path by removing trailing slashes.
     * @param string $path
     * @return string
     */
    private function normalizePath(string $path): string
    {
        return rtrim($path, '/');
    }

    /**
     * Resolve the incoming request to a handler.
     * @param string $requestUri
     * @param string $requestMethod
     */
    public function resolve(string $requestUri, string $requestMethod): void
    {
        // Remove query string and normalize
        $requestPath = $this->normalizePath(parse_url($requestUri, PHP_URL_PATH));

        // Iterate through routes for the given method
        if (!isset($this->routes[$requestMethod])) {
            $this->notFound();
            return;
        }

        foreach ($this->routes[$requestMethod] as $route) {
            // Use preg_match to match against the regex pattern
            // If a match is found, $matches will contain the captured groups
            if (preg_match($route['pattern'], $requestPath, $matches)) {
                $handler = $route['handler'];
                $params = [];

                // Extract named parameters from regex matches
                foreach ($matches as $key => $value) {
                    // Only include string keys (named capture groups)
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                if (is_callable($handler)) {
                    // Call the function and pass extracted parameters
                    call_user_func_array($handler, $params);
                } elseif (is_array($handler) && count($handler) === 2) {
                    $className = $handler[0];
                    $methodName = $handler[1];

                    if (class_exists($className)) {
                        $controller = new $className();
                        if (method_exists($controller, $methodName)) {
                            // Call the controller method and pass extracted parameters
                            call_user_func_array([$controller, $methodName], $params);
                            return; // Route found and handled
                        }
                    }
                }
            }
        }

        // No route matched
        $this->notFound();
    }

    /**
     * Handle 404 Not Found response.
     */
    private function notFound(): void
    {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Not Found'
        ]);
        exit();
    }
}
