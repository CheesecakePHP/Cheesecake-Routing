<?php


namespace Cheesecake\Routing;


Interface RouteInterface
{

    public function __construct(string $route, string $endpoint);
    public function setData(array $data);
    public function setOptions(array $options);
    public function try(string $route);
    public function match(array $rules);

}