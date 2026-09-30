<?php

class Router
{
    private $routes = [];

    // Register a GET route
    public function get($uri, $action)
    {
        $this->routes['GET'][$uri] = $action;
    }

    // Register a POST route
    public function post($uri, $action)
    {
        $this->routes['POST'][$uri] = $action;
    }

    // Dispatch the request to the correct controller/method
    public function dispatch($uri, $method)
    {
        // 1. Strip query strings from the URI (e.g., ?id=1)
        $uri = strtok($uri, '?');
        $method = strtoupper($method);

        // 2. Normalize URI trailing slash
        $normalizedUri = (strlen($uri) > 1) ? rtrim($uri, '/') : $uri;

        // 3. Build candidate search list for maximum routing tolerance
        $candidates = [
            $uri,
            $normalizedUri,
        ];

        if (strpos($normalizedUri, '/admin/') === 0) {
            $candidates[] = substr($normalizedUri, 6);
        } elseif ($normalizedUri === '/admin') {
            $candidates[] = '/';
        }

        foreach ($candidates as $targetUri) {
            if (isset($this->routes[$method][$targetUri])) {
                $action = $this->routes[$method][$targetUri];

                // If the action is a closure/anonymous function
                if (is_callable($action)) {
                    return call_user_func($action);
                }

                // If the action is an array [Controller::class, 'method']
                if (is_array($action)) {
                    $controllerName = $action[0];
                    $methodName = $action[1];

                    $controller = new $controllerName();
                    return $controller->$methodName();
                }
            }
        }

        // Standard 404 handling
        http_response_code(404);
        echo "404 - Page Not Found";
        exit();
    }
}