<?php


namespace Cheesecake\Routing\Exception;


use Cheesecake\Http\Exception\Error_404;

class RouteNotDefinedException extends Error_404
{

    /**
     * RouteNotDefinedException constructor.
     */
    public function __construct(string $message)
    {
        throw new Error_404('Route "'. $message .'" not defined');
    }

}