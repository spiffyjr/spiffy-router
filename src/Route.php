<?php

namespace Spiffy\Route;

class Route
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $path;

    /**
     * @var string
     */
    protected $regex;

    /**
     * @var null|\SplFixedArray
     */
    protected $tokens;

    /**
     * @var array
     */
    protected $defaults = [];

    /**
     * @var array
     */
    protected $methods = [];

    /**
     * @param string $name
     * @param string $path
     */
    public function __construct($name, $path)
    {
        $this->name = $name;
        $this->path = $path;
    }

    /**
     * @param string $uri
     * @param null $server
     * @return bool|RouteMatch
     */
    public function match($uri, $server = null)
    {
        $this->init();
        $path = parse_url($uri, PHP_URL_PATH);

        if (!empty($this->methods)) {
            $method = isset($server['REQUEST_METHOD']) ? strtolower($server['REQUEST_METHOD']) : 'get';

            if (!in_array($method, $this->methods)) {
                return false;
            }
        }

        if (preg_match('@^' . $this->regex . '$@', $path, $matches)) {
            foreach ($matches as $index => $match) {
                if (is_numeric($index)) {
                    unset($matches[$index]);
                }
            }

            return new RouteMatch($this, array_merge($this->defaults, $matches));
        }
        return false;
    }

    /**
     * @param array $params
     * @return string
     * @throws Exception\MissingParameterException
     */
    public function assemble(array $params = [])
    {
        $this->init();

        if ($this->tokens) {
            foreach ($this->tokens as $token) {
                list($name, $optional) = $token;

                if ($optional || isset($params[$name])) {
                    continue;
                }

                throw new Exception\MissingParameterException($this->getName(), $name);
            }
        }

        $replace = function ($matches) use ($params) {
            if (isset($params[$matches[2]])) {
                return $matches[1] . $params[$matches[2]];
            }
            return '';
        };

        return preg_replace_callback('@{([^A-Za-z]*)([A-Za-z]+)[?]?(?::[^}]+)?}@', $replace, $this->path);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param array $defaults
     */
    public function setDefaults(array $defaults)
    {
        $this->defaults = $defaults;
    }

    /**
     * @return array
     */
    public function getDefaults()
    {
        return $this->defaults;
    }

    /**
     * @param array|null $methods
     */
    public function setMethods(array $methods)
    {
        foreach ($methods as &$method) {
            $method = strtolower($method);
        }
        $this->methods = $methods;
    }

    /**
     * @return array|null
     */
    public function getMethods()
    {
        return $this->methods;
    }

    /**
     * Initializes the route which is required for match/assemble.
     */
    protected function init()
    {
        if ($this->regex) {
            return;
        }

        $this->regex = $this->path;
        $matches = [];

        if ($count = preg_match_all('@{([^A-Za-z]*([A-Za-z]+))([?]?)(?::([^}]+))?}@', $this->regex, $matches)) {
            $this->tokens = new \SplFixedArray($count);

            foreach ($matches[1] as $index => $token) {
                $fullString = $matches[1][$index];
                $name = $matches[2][$index];
                $optional = !empty($matches[3][$index]);
                $constraint = empty($matches[4][$index]) ? '.*' : $matches[4][$index];

                if ($optional) {
                    $replace = sprintf('(?:%s(?<%s>%s))?', str_replace($name, '', $fullString), $name, $constraint);
                } else {
                    $replace = sprintf('(?<%s>%s)', $name, $constraint);
                }

                $this->regex = str_replace($matches[0][$index], $replace, $this->regex);

                $this->tokens[$index] = [$name, $optional];
            }
        }
    }
}
