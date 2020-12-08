<?php


namespace Cheesecake\Routing;


Interface RouteInterface
{

    public function __construct(string $route, string $endpoint);
    public function setData(array $data);
    public function setOptions(array $options);
    public function match(string $route);
    public function where(array $rules);
    public function whereNumber(string $key);
    public function whereAlpha(string $key);
    public function whereAlphaNumeric(string $key);

}
