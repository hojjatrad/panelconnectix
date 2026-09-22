<?php
require_once __DIR__ . '/Helpers.php';

class Router {
    private array $routes = [];

    public function get(string $path, callable|array $handler): void {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable|array $handler): void {
        $this->routes[] = [
            'method' => $method,
            'path' => trim($path, '/'),
            'handler' => $handler
        ];
    }

    public function dispatch(): void {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($requestMethod === 'HEAD') {
            $requestMethod = 'GET';
        }
        
        // Support ?route=path or clean REQUEST_URI
        if (!empty($_GET['route'])) {
            $uri = trim($_GET['route'], '/');
        } else {
            $parsed = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $basePath = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
            if (!empty($basePath) && strpos($parsed, $basePath) === 0) {
                $parsed = substr($parsed, strlen($basePath));
            }
            $uri = trim($parsed, '/');
            if ($uri === 'index.php') {
                $uri = '';
            } elseif (strpos($uri, 'index.php/') === 0) {
                $uri = trim(substr($uri, 9), '/');
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) continue;

            // Pattern match: e.g. sub/{token}
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $method] = $handler;
                    $controller = new $class();
                    call_user_func_array([$controller, $method], $params);
                } else {
                    call_user_func_array($handler, $params);
                }
                return;
            }
        }

        // 404 handler
        http_response_code(404);
        echo "<div style='font-family:sans-serif; text-align:center; padding:50px; background:#0f172a; color:#fff;'>";
        echo "<h2>صفحه مورد نظر یافت نشد (404)</h2>";
        echo "<p>مسیر درخواستی در سامانه وجود ندارد.</p>";
        echo "<a href='" . Helpers::url('dashboard') . "' style='color:#a855f7;'>بازگشت به داشبورد</a>";
        echo "</div>";
    }
}
