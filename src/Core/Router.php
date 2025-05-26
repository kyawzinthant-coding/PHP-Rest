<?php

namespace App\Core;

use App\Core\Request;

class Router
{
    private array $routes = [];

    public function get(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }


    public function put(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middlewares);
    }


    public function delete(string $path, $handler, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, $handler, array $middlewares = []): void
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[^/]+)', $this->normalizePath($path));
        $pattern = '#^' . $pattern . '$#';

        $this->routes[$method][] = [
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }


    private function normalizePath(string $path): string
    {
        // Ensure path starts with a / if not empty, and handle root path correctly
        if (empty($path) || $path === '/') {
            return '/';
        }
        return '/' . trim(rtrim($path, '/'), '/');
    }


    /**
     * Resolve the incoming request to a handler.
     * It now creates and uses a Request object.
     */
    public function resolve(): void
    {
        $request = new Request(); // Create the Request object
        $requestPath = $request->getPath();
        $requestMethod = $request->getMethod();

        if (!isset($this->routes[$requestMethod])) {
            $this->notFound();
            return;
        }

        foreach ($this->routes[$requestMethod] as $route) {
            if (preg_match($route['pattern'], $requestPath, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                $handler = $route['handler'];
                $middlewares = $route['middlewares'] ?? [];

                $finalAction = function (Request $processedRequest) use ($handler, $params) {

                    if (is_callable($handler)) {
                        // For simple callable handlers, you might pass $processedRequest as the first arg
                        // call_user_func_array($handler, array_merge([$processedRequest], $params));
                        call_user_func_array($handler, $params); // Or keep it simple
                    } elseif (is_array($handler) && count($handler) === 2) {
                        $className = $handler[0];
                        $methodName = $handler[1];

                        if (class_exists($className)) {
                            $controller = new $className();
                            if (method_exists($controller, $methodName)) {
                                // Check if the controller method expects a Request object
                                $reflectionMethod = new \ReflectionMethod($className, $methodName);
                                $methodParams = $reflectionMethod->getParameters();
                                $argsToPass = [];

                                // First, check for Request type hint
                                if (count($methodParams) > 0 && $methodParams[0]->getType() && $methodParams[0]->getType()->getName() === Request::class) {
                                    $argsToPass[] = $processedRequest;
                                    // Then add named URL parameters
                                    foreach ($params as $paramName => $paramValue) {
                                        $argsToPass[] = $paramValue; // This assumes order or further logic for named params
                                    }
                                } else {
                                    $argsToPass = array_values($params); // Original behavior
                                }
                                $controllerArgs = [];
                                $urlParamIndex = 0;
                                foreach ($methodParams as $reflectionParam) {
                                    if ($reflectionParam->getType() && $reflectionParam->getType()->getName() === Request::class) {
                                        $controllerArgs[] = $processedRequest;
                                    } else {
                                        // Attempt to map named route params to controller method param names
                                        // Or fall back to positional mapping (less ideal but simpler for now)
                                        if (isset($params[$reflectionParam->getName()])) {
                                            $controllerArgs[] = $params[$reflectionParam->getName()];
                                        } else if (isset(array_values($params)[$urlParamIndex])) {
                                            // Fallback to positional if named not found
                                            // This part can get complex and frameworks have sophisticated solutions
                                            $controllerArgs[] = array_values($params)[$urlParamIndex];
                                            $urlParamIndex++;
                                        }
                                    }
                                }
                                call_user_func_array([$controller, $methodName], $controllerArgs);
                            } else {
                                http_response_code(500);
                                echo json_encode(['status' => 'error', 'message' => "Method {$methodName} not found in controller {$className}"]);
                                exit;
                            }
                        } else {
                            http_response_code(500);
                            echo json_encode(['status' => 'error', 'message' => "Controller class {$className} not found"]);
                            exit;
                        }
                    }
                };

                $pipeline = array_reduce(
                    array_reverse($middlewares),
                    function ($nextMiddleware, $middlewareClass) {
                        return function (Request $request) use ($nextMiddleware, $middlewareClass) {
                            if (!class_exists($middlewareClass)) {
                                http_response_code(500);
                                echo json_encode(['status' => 'error', 'message' => "Middleware class {$middlewareClass} not found."]);
                                exit;
                            }
                            $middlewareInstance = new $middlewareClass();
                            return $middlewareInstance->handle($request, $nextMiddleware);
                        };
                    },
                    $finalAction
                );

                $pipeline($request); // Pass the initial Request object here
                return;
            }
        }
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
