<?php
declare(strict_types=1);
namespace McRelish\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void    { $this->add('GET', $path, $handler); }
    public function post(string $path, array $handler): void   { $this->add('POST', $path, $handler); }
    public function patch(string $path, array $handler): void  { $this->add('PATCH', $path, $handler); }
    public function delete(string $path, array $handler): void { $this->add('DELETE', $path, $handler); }

    private function add(string $method, string $path, array $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($path, '/')) . '/?$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        foreach ($this->routes as [$m, $regex, [$class, $action]]) {
            if ($m === $method && preg_match($regex, $uri, $match)) {
                $params = array_map('rawurldecode', array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY));
                (new $class())->$action(...array_values($params));
                return;
            }
        }
        throw new HttpException('Not found', 404);
    }
}
