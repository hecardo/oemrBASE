<?php

/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  All Rights Reserved
 */

namespace Juggernaut\App;

use Juggernaut\App\Exceptions\RouteNotFoundException;

class Router
{
    private array $routes;
// remember to return the type hint callable|array when updating to php 8
// removing for right now to keep moving forward
// callable|array $action  <--- put this type hint back when we go to php8

    /**
     * @param string $requestMethod
     * @param string $route
     * @param callable|array $action
     * @return $this
     */
    public function register(string $requestMethod, string $route, $action): self
    {
        $this->routes[$requestMethod][$route] = $action;
        return $this;
    }

    public function get(string $route, $action): self
    {
        return $this->register('get', $route, $action);
    }

    public function post(string $route, $action): self
    {
        return $this->register('post', $route, $action);
    }

    public function routes(): array
    {
        return $this->routes;
    }
    /**
     * @throws RouteNotFoundException
     */
    public function resolve(string $requestUri, string $requestMethod)
    {
        $route = explode("?", $requestUri)[0];
        $action = $this->routes[$requestMethod][$route] ?? null;

        if (! $action) {
            throw new RouteNotFoundException();
        }

        if (is_callable($action)) {
            return call_user_func($action);
        }

        if (is_array($action)) {
            [$class, $method] = $action;

            if (class_exists($class)) {
                $class = new $class();

                if (method_exists($class, $method)) {
                    return call_user_func_array([$class, $method], []);
                }
            }
        }

        throw new RouteNotFoundException();
    }
}
