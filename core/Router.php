<?php

class Router
{
    private static array $routes = [];

    public static function add(string $method, string $path, callable $callback, bool $auth = false): void
    {
        // Converte path "/string/(\d+)" em expressão regex
        $pattern = "@^" . preg_replace('/\{(\w+)\}/', '(?P<\1>[^/]+)', $path) . "$@";
        self::$routes[] = compact('method', 'pattern', 'callback', 'auth');
    }

    public static function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        $uri = preg_replace('#^' . preg_quote($basePath) . '#', '', $uri);

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) continue;

            if (preg_match($route['pattern'], $uri, $matches)) {
                if ($route['auth'] && !Auth::extractToken()) {
                    http_response_code(401);
                    echo json_encode(['error' => 'Unauthorized']);
                    return;
                }

                // Extrai apenas parâmetros nomeados
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func_array($route['callback'], $params);
                return;
            }
        }

        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
    }
}
