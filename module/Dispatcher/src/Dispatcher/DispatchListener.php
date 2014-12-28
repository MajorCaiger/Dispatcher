<?php

/**
 * Dispatch Listener
 *
 * @author Rob Caiger <rob@clocal.co.uk>
 */
namespace Dispatcher;

use Zend\Mvc\MvcEvent;
use Zend\Mvc\InjectApplicationEventInterface;
use Zend\Mvc\Exception\InvalidControllerException;
use Zend\Mvc\DispatchListener as ZendDispatchListener;

/**
 * Dispatch Listener
 *
 * @author Rob Caiger <rob@clocal.co.uk>
 */
class DispatchListener extends ZendDispatchListener
{
    protected $event;

    protected $dispatchConfig;

    protected $dispatchName = false;

    /**
     * Listen to the "dispatch" event
     *
     * @param  MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e)
    {
        $this->event = $e;

        // If we have requested a partial dispatch
        $request = $e->getRequest();

        $dispatch = $request->getPost('dispatch', $request->getQuery('dispatch'));

        if ($dispatch !== null) {
            return $this->handlePartialDispatch($dispatch);
        }

        // Grab the main content as usual
        $content = parent::onDispatch($e);

        $dispatchName = $this->getDispatchName();

        // If we have not defined a dispatch config, just return the content
        if ($dispatchName === null) {
            return $content;
        }

        // Otherwise handle the dispatch
        return $this->handleDispatch($dispatchName, $content);
    }

    /**
     * Get dispatch name for partial
     *
     * @param string $partial
     * @return string
     */
    protected function getPartialDispatchName($partial, $dispatchName = null)
    {
        $dispatch = $this->getDispatchConfigForRoute($dispatchName);

        if (isset($dispatch[$partial])) {
            return $dispatch[$partial];
        }

        foreach ($dispatch as $controllerName) {
            if ($this->isDispatch($controllerName)) {
                return $this->getPartialDispatchName($partial, $controllerName);
            }
        }

        return null;
    }

    /**
     * Grab the dispatch config
     *
     * @return array
     */
    protected function getDispatchConfig()
    {
        if ($this->dispatchConfig === null) {
            $application = $this->event->getApplication();
            $config = $application->getServiceManager()->get('Config');
            $this->dispatchConfig = $config['dispatch'];
        }

        return $this->dispatchConfig;
    }

    /**
     * Grab the dispatch config for the current route
     *
     * @return array
     */
    protected function getDispatchConfigForRoute($dispatchName = null)
    {
        $routeMatch = $this->event->getRouteMatch();
        $routeName = $routeMatch->getMatchedRouteName();
        if ($dispatchName === null) {
            $dispatchName = $this->getDispatchName();
        }
        $dispatchConfig = $this->getDispatchConfig();

        $defaults = isset($dispatchConfig['defaults'][$dispatchName])
            ? $dispatchConfig['defaults'][$dispatchName] : [];

        $routeDispatch = isset($dispatchConfig['routes'][$routeName][$dispatchName])
            ? $dispatchConfig['routes'][$routeName][$dispatchName] : [];

        return array_merge($defaults, $routeDispatch);
    }

    /**
     * Check if a controller name is a dispatch
     *
     * @param string $controllerName
     * @return boolean
     */
    protected function isDispatch($controllerName)
    {
        $dispatchConfig = $this->getDispatchConfig();

        return isset($dispatchConfig['defaults'][$controllerName]);
    }

    /**
     * Get the dispatch name from the route match
     *
     * @return string
     */
    protected function getDispatchName()
    {
        if ($this->dispatchName === false) {
            $routeMatch = $this->event->getRouteMatch();
            $this->dispatchName = $routeMatch->getParam('dispatch');
        }

        return $this->dispatchName;
    }

    /**
     * If we have just requested a partial dispatch, find and build the partial view
     *
     * @param MvcEvent $e
     * @param string $partial
     * @return mixed
     */
    protected function handlePartialDispatch($partial)
    {
        $this->event->getRouteMatch()->setParam('isPartial', true);

        // If we are requesting the main content, we can bail early
        if ($partial === 'content') {
            return $this->dispatchController();
        }

        $dispatchName = $this->getPartialDispatchName($partial);

        if ($dispatchName === null) {
            $application = $this->event->getApplication();
            return $this->marshalControllerNotFoundEvent(
                $application::ERROR_CONTROLLER_NOT_FOUND,
                $partial,
                $this->event,
                $application
            );
        }

        return $this->buildViewForDispatch($dispatchName);
    }

    /**
     * Build up the dispatch view
     *
     * @param string $dispatchName
     * @param mixed $content
     * @return mixed
     */
    protected function handleDispatch($dispatchName = null, $content = null)
    {
        $dispatch = $this->getDispatchConfigForRoute($dispatchName);

        if (empty($dispatch)) {

            if ($content === null) {
                throw new \Exception('Need to handle this');
            }

            return $content;
        }

        $dispatchView = $this->dispatchController($dispatchName);

        if ($content !== null) {
            $dispatchView->addChild($content, 'content');
        }

        foreach ($dispatch as $name => $controllerName) {

            $child = $this->buildViewForDispatch($controllerName);

            $dispatchView->addChild($child, $name);
        }

        return $dispatchView;
    }

    /**
     * Build a view for the dispatchName
     * @param string $dispatchName
     * @return mixed
     */
    protected function buildViewForDispatch($dispatchName)
    {
        if ($this->isDispatch($dispatchName)) {
            return $this->handleDispatch($dispatchName);
        }

        return $this->dispatchController($dispatchName);
    }

    /**
     * This is mainly pulled from parent, this is just a portion of the parent onDispatch method which we need to reuse
     *
     * @param string $controllerName
     * @return mixed
     */
    protected function dispatchController($controllerName = null)
    {
        if ($controllerName === null) {
            $routeMatch = $this->event->getRouteMatch();
            $controllerName = $routeMatch->getParam('controller', 'not-found');
        }

        $application      = $this->event->getApplication();
        $events           = $application->getEventManager();
        $controllerLoader = $application->getServiceManager()->get('ControllerManager');

        if (!$controllerLoader->has($controllerName)) {

            $return = $this->marshalControllerNotFoundEvent(
                $application::ERROR_CONTROLLER_NOT_FOUND,
                $controllerName,
                $this->event,
                $application
            );

            return $this->complete($return, $this->event);
        }

        try {
            $controller = $controllerLoader->get($controllerName);
        } catch (InvalidControllerException $exception) {

            $return = $this->marshalControllerNotFoundEvent(
                $application::ERROR_CONTROLLER_INVALID,
                $controllerName,
                $this->event,
                $application,
                $exception
            );

            return $this->complete($return, $this->event);
        } catch (\Exception $exception) {
            $return = $this->marshalBadControllerEvent($controllerName, $this->event, $application, $exception);
            return $this->complete($return, $this->event);
        }

        $request  = $this->event->getRequest();
        $response = $application->getResponse();

        if ($controller instanceof InjectApplicationEventInterface) {
            $controller->setEvent($this->event);
        }

        try {
            $return = $controller->dispatch($request, $response);
        } catch (\Exception $ex) {
            $this->event->setError($application::ERROR_EXCEPTION)
                  ->setController($controllerName)
                  ->setControllerClass(get_class($controller))
                  ->setParam('exception', $ex);
            $results = $events->trigger(MvcEvent::EVENT_DISPATCH_ERROR, $this->event);
            $return = $results->last();
            if (! $return) {
                $return = $this->event->getResult();
            }
        }

        return $this->complete($return, $this->event);
    }
}
