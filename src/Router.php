<?php

namespace Cheesecake\Routing;

use Cheesecake\Routing\Exception\ControllerNotExistsException;
use Cheesecake\Routing\Exception\MalformedActionException;
use Cheesecake\Routing\Exception\MethodNotExistsException;
use Cheesecake\Routing\Exception\RouteIsEmptyException;
use Cheesecake\Routing\Exception\RouteNotDefinedException;

/**
 * The Router registers the routes for each HTTP verb (GET, POST, PUT, PATCH & DELETE).
 * Routes sharing the same options can be grouped and each option will be applied to
 * the containing routes.
 *
 * @package Cheesecake
 * @author Christian Meyer <ak56Lk@gmx.net>
 * @version 1.1
 * @since 0.1
 *
 * @method static \Cheesecake\Routing\Route|void get(string $route, string $action = null, array $options = [])
 * @method static \Cheesecake\Routing\Route|void post(string $route, string $action = null, array $options = [])
 * @method static \Cheesecake\Routing\Route|void put(string $route, string $action = null, array $options = [])
 * @method static \Cheesecake\Routing\Route|void patch(string $route, string $action = null, array $options = [])
 * @method static \Cheesecake\Routing\Route|void delete(string $route, string $action = null, array $options = [])
 * @method static \Cheesecake\Routing\Route|void any(string $route, string $action = null, array $options = [])
 */
class Router
{

    /**
     * This array contains all registered routes for each HTTP verb.
     *
     * @var array[]
     */
    public static array $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
        'ANY' => []
    ];

    /**
     * The prefix which will be applied to all routes contained in a group.
     *
     * @var string
     */
    private static ?string $prefix = null;

    /**
     * All options for each route contained in a group
     *
     * @var array
     */
    private static array $routeOptions = [];

    /**
     * The arguments are:
     * <pre>
     * 0: route
     * 1: action
     * 3: options
     * </pre>
     *
     * If argument 1 is null then it will try to match a registered route.
     * If argument 1 is not null then a new route will be registered
     *
     * @param string $name Name of the called method (get, post, put, patch, delete)
     * @param array $arguments <pre>
     * 0: route
     * 1: action
     * 2: options
     * </pre>
     * @return mixed
     * @throws RouteIsEmptyException
     * @throws RouteNotDefinedException
     */
    public static function __callStatic(string $name, array $arguments)
    {
        $route = self::$prefix . $arguments[0];
        $route = trim($route, '/');

        if(!isset($arguments[1]) || $arguments[1] === null) {
            $MatchedRoute = null;

            foreach(self::$routes[strtoupper($name)] as $Route) {
                if ($Route->match($route)) {
                    $MatchedRoute = $Route;
                    break;
                }
            }

            if($MatchedRoute === null) {
                throw new RouteNotDefinedException($route);
            }

            return $MatchedRoute;
        }
        else {
            if(empty($route)) {
                throw new RouteIsEmptyException();
            }

            $Route = new Route($route, $arguments[1]);

            if (!isset($arguments[2]) || !is_array($arguments[2])) {
                $arguments[2] = [];
            }

            $Route->setOptions(array_merge(self::$routeOptions, $arguments[2]));

            self::$routes[strtoupper($name)][$route] = $Route;

            return $Route;
        }
    }

    /**
     * A group applies shared options to all containing routes. The routes will be
     * registered via a callable like a function.
     * Currently supported is a prefix and middleware(s)
     *
     * @param array $sharedOptions define shared options (currently prefix and middleware)
     * @param callable $callback In the callable the routes will be registered
     */
    public static function group(array $sharedOptions, callable $callback): void
    {
        if(isset($sharedOptions['prefix'])) {
            self::$prefix = $sharedOptions['prefix'];
        }

        if(isset($sharedOptions['middleware'])) {
            self::$routeOptions['middleware'] = $sharedOptions['middleware'];
        }

        $callback();

        self::$prefix = null;
        self::$routeOptions = [];
    }

    /**
     * Tries to match a route of the given HTTP verb for a given route.
     * Middlewares will be applied and then it will try to find a controller
     * and try to call a method.
     * If the controller does not exist a ControllerNotExistsException will be
     * thrown.
     * If the method in the controller does not exist a MethodNotExistsException
     * will be thrown.
     *
     * @param string $requestMethod
     * @param string $route
     * @return array
     * @throws ControllerNotExistsException
     * @throws MethodNotExistsException
     */
    public static function route(string $requestMethod, string $route): array
    {
        switch($requestMethod) {
            case 'GET': $Route = self::get($route); break;
            case 'POST': $Route = self::post($route); break;
            case 'PUT': $Route = self::put($route); break;
            case 'PATCH': $Route = self::patch($route); break;
            case 'DELETE': $Route = self::delete($route); break;
            default: $Route = self::any($route); break;
        }

        $middlewares = $Route->getOptions('middleware');

        if($middlewares !== null) {
            if(!is_array($middlewares)) {
                $middlewares = [$middlewares];
            }

            foreach($middlewares as $middleware) {
                $Middleware = new $middleware();
                $result = $Middleware->handle();

                if ($result === false) {
                    return [
                        'error' => [
                            'code' => 403,
                            'message' => 'forbidden'
                        ]
                    ];
                    break;
                }
            }
        }

        $controllerName = '\App\Components\\'. self::getController($Route->getAction()) .'\Controller';
        $methodName = self::getMethod($Route->getAction());

        if (!class_exists($controllerName)) {
            throw new ControllerNotExistsException('Controller '. $controllerName .' does not exist');
        }

        $Controller = new $controllerName();

        if (!method_exists($Controller, $methodName)) {
            throw new MethodNotExistsException('Method "'. $methodName .'" does not exist in Controller '. $controllerName);
        }

        return [
            'controller' => $Controller,
            'method' => $methodName,
            'data' => $Route->getData()
        ];
    }

    /**
     * Splits the action at the "@". If no @ is in the action or begins with an @ then
     * a MalformedActionException will be thrown.
     * If the string can be splitted successful then an array with the keys "controller"
     * and "method" will be returned.
     *
     * @param string $action
     * @return array
     * @throws MalformedActionException
     */
    private static function splitAction(string $action): array
    {
        if (
            strpos($action, '@') === false
            || strpos($action, '@') === 0
        ) {
            throw new MalformedActionException('Action is malformed');
        }

        [$controller, $method] = explode('@', $action);

        return [
            'controller' => $controller,
            'method' => $method
        ];
    }

    /**
     * Gets the extracted controller of the action
     *
     * @param string $action The action string of the route
     * @return string
     * @uses \Cheesecake\Routing\Router::splitAction()
     */
    private static function getController(string $action): string
    {
        $splits = self::splitAction($action);
        return $splits['controller'];
    }

    /**
     * Gets the extracted method of the action
     *
     * @param string $action
     * @return string
     * @uses \Cheesecake\Routing\Router::splitAction()
     */
    private static function getMethod(string $action): string
    {
        $splits = self::splitAction($action);
        return $splits['method'];
    }

}
