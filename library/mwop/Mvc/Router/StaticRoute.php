<?php
namespace mwop\Mvc\Router;

use mwop\Stdlib\Route,
    mwop\Mvc\Exception\InvalidRequestException,
    mwop\Mvc\Exception\MissingParameterException,
    Zend\EventManager\EventCollection,
    Zend\EventManager\EventManager,
    Fig\Request,
    Fig\Http\HttpRequest;

class StaticRoute implements Route
{
    protected $events;
    protected $path;
    protected $params = array();
    protected $request;

    public function __construct($path, $params = null)
    {
        $this->path = $path;
        if (is_object($params)) {
            $tmp = array();
            foreach ($params as $key => $value) {
                $tmp[$key] = $value;
            }
            $params = $tmp;
        }
        if (is_array($params)) {
            $this->params = $params;
        }
    }

    public function events(EventCollection $events = null)
    {
        if (null !== $events) {
            $this->events = $events;
        } elseif (null === $this->events) {
            $this->events = new EventManager(array(__CLASS__, get_called_class()));
        }
        return $this->events;
    }


    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Match a request
     * 
     * @param  Request $request 
     * @return false|array
     */
    public function match(Request $request)
    {
        if (!$request instanceof HttpRequest) {
            throw new InvalidRequestException(sprintf(
                'Expected HttpRequest; received "%s"',
                get_class($request)
            ));
        }

        $uri = $request->getPathInfo();
        if (empty($uri)) {
            // Hack for when running under FastCGI
            $uri = $request->getRequestUri();
        }

        $this->events()->trigger(__FUNCTION__ . '.pre', $this, array('uri' => $uri, 'path' => $this->path));
        if ($uri != $this->path) {
            $this->events()->trigger(__FUNCTION__ . '.post', $this, array('uri' => $uri, 'path' => $this->path, 'success' => false));
            return false;
        }
        $this->events()->trigger(__FUNCTION__ . '.post', $this, array('uri' => $uri, 'success' => true));
        return $this->params;
    }

    /**
     * Assemble a URI
     * 
     * @param  array $params 
     * @param  array $options 
     * @return string
     */
    public function assemble(array $params = array(), array $options = array())
    {
        return $this->path;
    }
}