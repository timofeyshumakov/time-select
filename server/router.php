<?php
class Router {
    private $routes = [];
    
    public function addRoute($method, $path, $handler) {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }
    
    public function dispatch($method, $path, $data) {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                return call_user_func($route['handler'], $data);
            }
        }
        
        throw new Exception('Route not found');
    }
}