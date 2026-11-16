<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{method:string,path:string,handler:callable|string}> */
    private static array $routes = [];

    public static function get(string $path, callable|string $handler): void
    {
        self::add('GET', $path, $handler);
    }

    public static function post(string $path, callable|string $handler): void
    {
        self::add('POST', $path, $handler);
    }

    public static function add(string $method, string $path, callable|string $handler): void
    {
        self::$routes[] = ['method' => strtoupper($method), 'path' => self::normalize($path), 'handler' => $handler];
    }

    public static function dispatch(): void
    {
        $method = Request::method();
        $path = self::normalize(Request::path());
        foreach (self::$routes as $route) {
            if ($route['method'] !== $method || $route['path'] !== $path) {
                continue;
            }
            $handler = $route['handler'];
            if (is_string($handler)) {
                [$class, $action] = explode('@', $handler, 2);
                $handler = [new $class(), $action];
            }
            $handler();
            return;
        }
        throw new HttpException(404, 'The page you requested could not be found.');
    }

    private static function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
