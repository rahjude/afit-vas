<?php

namespace App\Config;

class Router
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $path, string $controller, string $method, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $controller, $method, $middleware);
    }

    public function post(string $path, string $controller, string $method, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $controller, $method, $middleware);
    }

    public function put(string $path, string $controller, string $method, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $controller, $method, $middleware);
    }

    public function delete(string $path, string $controller, string $method, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $controller, $method, $middleware);
    }

    private function addRoute(string $httpMethod, string $path, string $controller, string $method, array $middleware): void
    {
        $this->routes[] = [
            'httpMethod'  => $httpMethod,
            'path'        => $path,
            'controller'  => $controller,
            'method'      => $method,
            'middleware'   => $middleware,
        ];
    }

    public function resolve(string $httpMethod, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';

        // Remove /api prefix
        if (str_starts_with($uri, '/api')) {
            $uri = substr($uri, 4) ?: '/';
        }

        foreach ($this->routes as $route) {
            if ($route['httpMethod'] !== $httpMethod) {
                continue;
            }

            $pattern = preg_replace('/\/:([^\/]+)/', '/(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run middleware
                $request = [
                    'params'  => $params,
                    'query'   => $_GET,
                    'body'    => $this->getBody(),
                    'headers' => $this->getHeaders(),
                    'files'   => $_FILES,
                    'auth'    => null,
                ];

                foreach ($route['middleware'] as $mw) {
                    $middlewareClass = new $mw();
                    $request = $middlewareClass->handle($request);
                    if ($request === null) {
                        return;
                    }
                }

                $controller = new $route['controller']();
                $controller->{$route['method']}($request);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Route not found']);
    }

    private function getBody(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            return json_decode(file_get_contents('php://input'), true) ?? [];
        }
        return $_POST;
    }

    private function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($name)] = $value;
            }
        }
        return $headers;
    }
}
