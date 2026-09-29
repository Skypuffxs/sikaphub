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
        // Strip query strings from the URI (e.g., ?id=1)
        $uri = strtok($uri, '?');

        if (array_key_exists($uri, $this->routes[$method])) {
            $action = $this->routes[$method][$uri];

            // If the action is a closure/anonymous function (for testing)
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

        // Standard 404 handling
        http_response_code(404);
        echo "404 - Page Not Found";
        exit();
    }
}