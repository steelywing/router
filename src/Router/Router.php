<?php
/**
 * @author      Wing Leong <steely.wing@gmail.com>
 * @copyright   2013 Wing Leong
 * @license     MIT public license
 */

/**
 *
 * A lightweight PHP router
 * 
 * Base on Bramus Router class
 * https://github.com/bramus/router
 * 
 */

class Router
{
    /**
     * @var self Instance of self
     */
    private static $instance = null;
    
    
    /**
     * @var array Callable instance list, every class will only create 1 instance
     */
    private $callableInstance = array();
    
    
    /**
     * @var array Route pattern replace list
     */
    private $patternTokens = array(
        ':string'   => '[a-zA-Z]+',
        ':number'   => '[0-9]+',
        ':alpha'    => '[a-zA-Z0-9-_]+',
    );
    
    
    /**
     * @var array Environment
     */
    private $env = array();
    
    
    /**
     * @var array The route patterns and their handling functions
     */
    private $routes = array();


    /**
     * @var array The before and after middleware route patterns and
     *      their handling functions
     */
    private $befores = array();
    private $afters = array();


    /**
     * @var object The function to be executed when no route has been matched
     */
    private $notFound = null;
    
    
    private function __construct()
    {
        // script directory URI
        $this->env['SCRIPT_DIR'] = str_replace('\\', '/',
            dirname($_SERVER['SCRIPT_NAME'])
        );

        // script URI path
        if (strpos($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']) === 0) {
            // without URL rewrite
            $this->env['SCRIPT_NAME'] = $_SERVER['SCRIPT_NAME'];
        } else {
            // with URL rewrite
            $this->env['SCRIPT_NAME'] = $this->env['SCRIPT_DIR'];
        }
        
        // path URI trailing the script URI
        $path = substr_replace(
            $_SERVER['REQUEST_URI'], '', 0,
            strlen($this->env['SCRIPT_NAME'])
        );
        
        // remove query string
        if (strstr($path, '?')) {
            $path = substr_replace( $path, '', strpos($path, '?') );
        }
        
        // enforce leading slash
        $this->env['PATH_INFO'] = '/' . ltrim($path, '/');
        
        // save instance
        if (is_null(self::$instance)) {
            self::$instance = $this;
        }
    }

    /**
     * Get a instance of self
     * 
     * @return string
     */
    public static function getInstance()
    {
        return is_null(self::$instance) ? new self() : self::$instance;
    }
    
    
    /**
     * Create callable object if callable is using syntax 'Class->method'
     * every class will only create 1 instance
     * 
     * @param string Callable string
     * @return callable Callable string or array
     */
    private function callableInstance($callable)
    {
        if (is_string($callable) && strstr($callable, '->')) {
            
            list($class, $method) = explode('->', $callable, 2);
            
            if (! class_exists($class)) {
                throw new Exception("Class '{$class}' not exist");
            }
            
            // has callable instance been created ?
            if (array_key_exists($class, $this->callableInstance)) {
                // use exist instance
                $callable = array($this->callableInstance[$class], $method);
            } else {
                // create new callable instance
                $instance = new $class();
                $this->callableInstance[$class] = $instance;
                $callable = array($instance, $method);
            }
        }
        
        return $callable;
    }
    
    
    /**
     * Get the script URL path
     * 
     * @return string
     */
    public function getScriptName()
    {
        return $this->env['SCRIPT_NAME'];
    }
    
    
    /**
     * Get the script directory URL path, without trailing slash '/'
     * 
     * @return string
     */
    public function getScriptDir()
    {
        return $this->env['SCRIPT_DIR'];
    }


    /**
     * Get the script directory URL path, without trailing slash '/'
     * 
     * @return string
     */
    public function getPathInfo()
    {
        return $this->env['PATH_INFO'];
    }


    /**
     * Append a route and a handling function to route list
     *
     * @param string $routes Append route to this array
     * @param string $methods Allowed methods, | delimited
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     */
    private function addRoute(&$routes, $methods, $pattern, $callable)
    {
        $pattern = '/' . trim($pattern, '/');
        
        foreach (explode('|', $methods) as $method) {
            $routes[$method][$pattern] = $callable;
        }
    }

    
    /**
     * Store a before middleware route and a handling function to be executed
     * when accessed using one of the specified methods
     *
     * @param string $methods Allowed methods, | delimited
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     * @return self For chaining
     */
    public function before($methods, $pattern, $callable)
    {
        $this->addRoute($this->befores, $methods, $pattern, $callable);
        
        // chaining
        return $this;
    }


    /**
     * Store a after middleware route and a handling function to be executed
     * when accessed using one of the specified methods
     *
     * @param string $methods Allowed methods, | delimited
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     * @return self For chaining
     */
    public function after($methods, $pattern, $callable)
    {
        $this->addRoute($this->afters, $methods, $pattern, $callable);
        
        // chaining
        return $this;
    }


    /**
     * Store a route and a handling function to be executed
     * when accessed using one of the specified methods
     *
     * @param string $methods Allowed methods, | delimited
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     * @return self For chaining
     */
    public function map($methods, $pattern, $callable)
    {
        $this->addRoute($this->routes, $methods, $pattern, $callable);
        
        // chaining
        return $this;
    }


    /**
     * Route accessed using GET or POST
     *
     * @param string $methods Allowed methods, | delimited
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     * @return self For chaining
     */
    public function match($pattern, $callable)
    {
        $this->addRoute($this->routes, 'GET|POST', $pattern, $callable);
        
        // chaining
        return $this;
    }


    /**
     * Route accessed using GET
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     * @return self For chaining
     */
    public function get($pattern, $callable)
    {
        $this->addRoute($this->routes, 'GET', $pattern, $callable);
        
        // chaining
        return $this;
    }


    /**
     * Route accessed using POST
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     * @return self For chaining
     */
    public function post($pattern, $callable)
    {
        $this->addRoute($this->routes, 'POST', $pattern, $callable);
        
        // chaining
        return $this;
    }


    /**
     * Shorthand for a route accessed using DELETE
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     * @return self For chaining
     */
    public function delete($pattern, $callable)
    {
        $this->addRoute($this->routes, 'DELETE', $pattern, $callable);
        
        // chaining
        return $this;
    }


    /**
     * Shorthand for a route accessed using PUT
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     * @return self For chaining
     */
    public function put($pattern, $callable)
    {
        $this->addRoute($this->routes, 'PUT', $pattern, $callable);
        
        // chaining
        return $this;
    }


    /**
     * Shorthand for a route accessed using OPTIONS
     *
     * @param string $pattern A route pattern such as /about/system
     * @param object $callable The handling function to be executed
     * @return self For chaining
     */
    public function options($pattern, $callable)
    {
        $this->addRoute($this->routes, 'OPTIONS', $pattern, $callable);
        
        // chaining
        return $this;
    }


    /**
     * Execute the router: Loop all defined before middlewares and routes, and execute the handling function if a mactch was found
     *
     * @param object $callback Function to be executed after a matching route was handled (= after router middleware)
     * @return int Matched route count
     */
    public function run()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // run before middlewares
        if (isset( $this->befores[$method] )) {
            $this->handle( $this->befores[$method] );
        }

        // run route
        $handled = 0;
        if (isset( $this->routes[$method] )) {
            $handled = $this->handle( $this->routes[$method], true );
        }

        // run after middlewares
        if (isset( $this->afters[$method] )) {
            $this->handle( $this->afters[$method] );
        }

        // if no route was run, call not found
        if ($handled == 0) {
            
            // create callable object if need
            $callable = self::callableInstance($this->notFound);
            
            if (is_callable($callable)) {
                call_user_func($callable);
            } else {
                header('HTTP/1.1 404 Not Found');
            }
        }
        
        return $handled;
    }


    /**
     * Set the 404 handling function
     * 
     * @param object $callable The function to be executed
     */
    public function setNotFound($callable) {
        // check callable
        $this->notFound = $callable;
        
        return $this;
    }


    /**
     * Handle a a set of routes: if a match is found, execute
     * the relatinghandling function
     * 
     * @param array $routes Collection of route patterns and their handling functions
     * @param boolean $quitAfterRun Does the handle function need to quit after one route was matched?
     * @return int The number of routes handled
     */
    private function handle($routes, $runOnce = false)
    {
        // Counter to keep track of the number of routes we've handled
        $handled = 0;

        // Loop all routes
        foreach ($routes as $pattern => $callable) {

            // Convert pattern tokens
            $pattern = strtr($pattern, $this->patternTokens);

            // find matching route
            if ( preg_match('#^' . $pattern . '$#', $this->env['PATH_INFO'], $matches) ) {
                
                // remove the text that matched the full pattern
                array_shift($matches);
                
                /*
                 * lazy create callable instance here
                 */
                $callable = $this->callableInstance( $callable );
                
                // check callable
                if (! is_callable($callable) ) {
                    $message = is_array($callable) ? print_r($callable, true) : $callable;
                    throw new BadFunctionCallException("'{$message}' is not callable");
                }

                // call the handling function with the URL parameters
                call_user_func_array($callable, $matches);

                $handled++;

                // run only one matching
                if ($runOnce) {
                    break;
                }
            }
        }

        // Return the number of routes handled
        return $handled;
    }


    /**
     * Redirect header
     * 
     * @param string $uri Target URI
     * @param boolean $relative Append to script path ?
     * @param boolean $exit Exit ?
     */
    function redirect($uri, $relative = false, $exit = true)
    {
        if ($relative) {
            $uri = $this->path($uri);
        }
        
        header('Location: ' . $uri);
        
        if ($exit) { exit(); }
    }


    /**
     * Return specify URI append to the script URI
     * Example: '/login' => '/app/index.php/login' (without URL rewrite)
     *          '/login' => '/app/login' (with URL rewrite)
     * 
     * @param string $uri URI relate to script path
     * @return string Absolute URI path
     */
    function path($uri)
    {
        return $this->getScriptName() . '/' . ltrim($uri, '/');
    }


    /**
     * Return specify URI append to the script directory URI
     * Example: '/img/logo.jpg' => '/app/img/logo.jpg'
     * 
     * @param string $uri URI relate to script directory
     * @return string Absolute URI path
     */
    function asset($uri)
    {
        return $this->getScriptDir() . '/' . ltrim($uri, '/'); 
    }
}
