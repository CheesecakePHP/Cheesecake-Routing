<?php


namespace Cheesecake\Routing;


/**
 * The Route represents a single route and their settings and options.
 * Each route will be resolved to an action which will separate the
 * controller and method by "@", e.g. Controller@Method
 * Placeholders in the route will be stored in an array
 *
 * @package Cheesecake\Routing
 * @author Christian Meyer <ak56Lk@gmx.net>
 * @version 1.1
 * @since 0.1
 */
class Route implements RouteInterface
{

    /**
     * The route to match against.
     * @var string
     */
    private string $route;

    /**
     * The action the route will be resolved to.
     * @var string
     */
    private string $action;

    /**
     * Placeholders in the route will be stored in this array.
     * @var array
     */
    private array $data = [];

    /**
     * Options like middleware(s) will be stored in this array.
     * @var array
     */
    private array $options = [];

    private array $rules = [];

    /**
     * A route and an action will be needed to instantiate
     * the Route object.
     *
     * @param string $route
     * @param string $action
     */
    public function __construct(string $route, string $action)
    {
        $this->route = $route;
        $this->action = $action;
    }

    /**
     * Returns the action
     *
     * @return string
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Returnes the data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Returns the options
     *
     * @param string $key
     * @return array|mixed
     */
    public function getOptions(string $key = null)
    {
        $return = $this->options;

        if ($key !== null) {
            if (!isset($this->options[$key])) {
                $this->options[$key] = null;
            }

            $return = $this->options[$key];
        }

        return $return;
    }

    /**
     * Sets the data
     *
     * @param array $data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * Sets the options
     *
     * @param array $options
     */
    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    /**
     * Tries to match against a given URI. It considers placeholders and found placeholders
     * will be stored in the data array.
     * If the URI matches the route it returns TRUE otherwise FALSE.
     *
     * @param string $uri The URI represents the called endpoint
     * @return bool TRUE if the URI matches the route otherwise FALSE
     */
    public function match(string $uri)
    {
        $uri = trim($uri, '/');
        $route = preg_replace('~\{(\w+)\}~', '(\w+)', $this->route);

        $matched = (bool)preg_match_all('~^'. $route .'$~', $uri, $matches, PREG_SET_ORDER);

        if ($matched) {
            preg_match_all('~\{(\w+)\}~', $this->route, $placeholders);

            $data = [];

            foreach ($placeholders[1] as $k => $placeholder) {
                if(isset($this->rules[$placeholder])) {
                    if(class_exists($this->rules[$placeholder])) {
                        if(method_exists($this->rules[$placeholder], 'validate')) {
                            if (!$this->rules[$placeholder]::validate($matches[0][($k + 1)])) {
                                $matched = false;
                                break;
                            }
                        }
                    }
                    elseif(!preg_match($this->rules[$placeholder], $matches[0][($k + 1)])) {
                        $matched = false;
                        break;
                    }
                }

                $data[$placeholder] = $matches[0][($k + 1)];
            }

            $this->setData($data);
        }

        return $matched;
    }

    /**
     * @param array $rules
     */
    public function where(array $rules)
    {
        $this->rules = $rules;
        return $this;
    }

    public function whereNumber(string $key)
    {
        $this->where([
            $key => '([0-9]+)'
        ]);

        return $this;
    }

    public function whereAlpha(string $key)
    {
        $this->where([
            $key => '([^0-9]+)'
        ]);

        return $this;
    }

    public function whereAlphaNumeric(string $key)
    {
        $this->where([
            $key => '([\d]+)'
        ]);

        return $this;
    }

}
