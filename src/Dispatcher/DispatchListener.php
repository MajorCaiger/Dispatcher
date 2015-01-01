<?php

/**
 * Dispatch Listener
 *
 * @author Rob Caiger <rob@clocal.co.uk>
 */
namespace Dispatcher;

use Zend\Mvc\MvcEvent;
use Zend\Mvc\Exception\InvalidControllerException;
use Zend\Mvc\InjectApplicationEventInterface;
use Zend\Mvc\DispatchListener as ZendDispatchListener;
use Zend\View\Model\ViewModel;

/**
 * Dispatch Listener
 *
 * @author Rob Caiger <rob@clocal.co.uk>
 */
class DispatchListener extends ZendDispatchListener
{
    protected $partialDispatch;

    protected $dispatchConfig;

    protected $dispatchName;

    protected $routeAction;

    /**
     * Listen to the "dispatch" event
     *
     * @param MvcEvent $e
     * @return mixed
     */
    public function onDispatch(MvcEvent $e)
    {
        $this->setRouteAction($e);

        $dispatchConfig = $this->getDispatchConfigForRoute($e);
        if (empty($dispatchConfig)) {
            return parent::onDispatch($e);
        }

        if ($this->isPartialDispatch($e)) {

            $return = $this->partialDispatch($e);
            $return->setTerminal(true);
            $e->setViewModel($return);

        } else {
            $return = $this->handleDispatch($e, $this->getDispatchName($e), $this->contentDispatch($e));
        }

        $return = $this->complete($return, $e);

        return $return;
    }

    /**
     * Set route action
     *
     * @param MvcEvent $e
     */
    protected function setRouteAction(MvcEvent $e)
    {
        $routeMatch = $e->getRouteMatch();
        $this->routeAction = $routeMatch->getParam('action', 'not-found');
    }

    /**
     * Getter for routeAction
     *
     * @return string
     */
    protected function getRouteAction()
    {
        return $this->routeAction;
    }

    /**
     * Partial dispatch
     *
     * @param MvcEvent $e
     * @return mixed
     */
    protected function partialDispatch(MvcEvent $e)
    {
        $partialName = $this->getPartialDispatchName($e);

        if ($partialName === 'content') {
            return $this->contentDispatch($e);
        }

        $config = $this->getDispatchConfigForRoute($e);

        if (!isset($config[$partialName])) {
            // @todo handle this exception differently
            throw new \Exception('Partial not found');
        }

        $controllerName = $config[$partialName];

        return $this->handleDispatchOrController($e, $controllerName);
    }

    /**
     * Build up the dispatch view
     *
     * @param MvcEvent $e
     * @param string $dispatchName
     * @param mixed $content
     * @return mixed
     */
    protected function handleDispatch(MvcEvent $e, $dispatchName = null, $content = null)
    {
        if ($content !== null && !($content instanceof ViewModel)) {
            return $content;
        }

        $dispatch = $this->getDispatchConfigForRoute($e, $dispatchName);

        if (empty($dispatch)) {

            if ($content === null) {
                throw new \Exception('Need to handle this');
            }

            return $content;
        }

        $dispatchView = $this->dispatchController($e, $dispatchName);

        if ($dispatchView instanceof ViewModel) {
            if ($content !== null) {
                $dispatchView->addChild($content, 'content');
            }

            foreach ($dispatch as $name => $controllerName) {

                $child = $this->handleDispatchOrController($e, $controllerName);

                if ( !($child instanceof ViewModel)) {

                    return $child;
                }

                $dispatchView->addChild($child, $name);
            }
        }

        return $dispatchView;
    }

    /**
     * Dispatch controller, or handle recursive dispatch
     *
     * @param MvcEvent $e
     * @param string $controllerName
     * @return mixed
     */
    protected function handleDispatchOrController(MvcEvent $e, $controllerName)
    {
        if ($this->isDispatch($e, $controllerName)) {
            return $this->handleDispatch($e, $controllerName);
        }

        return $this->dispatchController($e, $controllerName);
    }

    /**
     * Check if a controller name is a dispatch
     *
     * @param MvcEvent $e
     * @param string $controllerName
     * @return boolean
     */
    protected function isDispatch($e, $controllerName)
    {
        $dispatchConfig = $this->getDispatchConfig($e);

        return isset($dispatchConfig['defaults'][$controllerName]);
    }

    /**
     * Dispatch content
     *
     * @param MvcEvent $e
     * @return mixed
     */
    protected function contentDispatch(MvcEvent $e)
    {
        $routeMatch = $e->getRouteMatch();
        $controllerName = $routeMatch->getParam('controller', 'not-found');

        return $this->dispatchController($e, $controllerName, $this->getRouteAction());
    }

    /**
     * Dispatch controller
     *
     * @param MvcEvent $e
     * @param string $controllerName
     * @return mixed
     */
    protected function dispatchController(MvcEvent $e, $controllerName, $action = 'dispatch')
    {
        $routeMatch = $e->getRouteMatch();
        $routeMatch->setParam('action', $action);
        $application = $e->getApplication();
        $events = $application->getEventManager();
        $controllerLoader = $application->getServiceManager()->get('ControllerManager');

        if (!$controllerLoader->has($controllerName)) {

            return $this->marshalControllerNotFoundEvent(
                $application::ERROR_CONTROLLER_NOT_FOUND,
                $controllerName,
                $e,
                $application
            );
        }

        try {
            $controller = $controllerLoader->get($controllerName);
        } catch (InvalidControllerException $ex) {
            $type = $application::ERROR_CONTROLLER_INVALID;
            return $this->marshalControllerNotFoundEvent($type, $controllerName, $e, $application, $ex);
        } catch (\Exception $ex) {
            return $this->marshalBadControllerEvent($controllerName, $e, $application, $ex);
        }

        $request = $e->getRequest();
        $response = $application->getResponse();

        if ($controller instanceof InjectApplicationEventInterface) {
            $controller->setEvent($e);
        }

        try {
            $return = $controller->dispatch($request, $response);
        } catch (\Exception $ex) {

            $e->setError($application::ERROR_EXCEPTION)
                  ->setController($controllerName)
                  ->setControllerClass(get_class($controller))
                  ->setParam('exception', $ex);

            $results = $events->trigger(MvcEvent::EVENT_DISPATCH_ERROR, $e);
            $return = $results->last();

            if (!$return) {
                $return = $e->getResult();
            }
        }

        return $return;
    }

    /**
     * Grab the dispatch config
     *
     * @param MvcEvent $e
     * @return array
     */
    protected function getDispatchConfig(MvcEvent $e)
    {
        if ($this->dispatchConfig === null) {
            $application = $e->getApplication();
            $config = $application->getServiceManager()->get('Config');
            $this->dispatchConfig = $config['dispatch'];
        }

        return $this->dispatchConfig;
    }

    /**
     * Grab the dispatch config for the current route
     *
     * @todo also merge recursive dispatch partials
     *
     * @return array
     */
    protected function getDispatchConfigForRoute(MvcEvent $e, $dispatchName = null)
    {
        $routeMatch = $e->getRouteMatch();
        $routeName = $routeMatch->getMatchedRouteName();

        if ($dispatchName === null) {
            $dispatchName = $this->getDispatchName($e);
        }

        $dispatchConfig = $this->getDispatchConfig($e);

        $defaults = isset($dispatchConfig['defaults'][$dispatchName])
            ? $dispatchConfig['defaults'][$dispatchName] : [];

        $routeDispatch = isset($dispatchConfig['routes'][$routeName][$dispatchName])
            ? $dispatchConfig['routes'][$routeName][$dispatchName] : [];

        return array_merge($defaults, $routeDispatch);
    }

    /**
     * Get the dispatch name from the route match
     *
     * @return string
     */
    protected function getDispatchName(MvcEvent $e)
    {
        if ($this->dispatchName === null) {
            $routeMatch = $e->getRouteMatch();
            $this->dispatchName = $routeMatch->getParam('dispatch', false);
        }

        return $this->dispatchName;
    }

    /**
     * Check if the event carries a request for a partial dispatch
     *
     * @param MvcEvent $e
     * @return boolean
     */
    protected function isPartialDispatch(MvcEvent $e)
    {
        return $this->getPartialDispatchName($e) !== false;
    }

    /**
     * Get partial dispatch name
     *
     * @param MvcEvent $e
     * @return string|boolean
     */
    protected function getPartialDispatchName(MvcEvent $e)
    {
        if ($this->partialDispatch === null) {
            $request = $e->getRequest();
            $this->partialDispatch = $request->getPost('dispatch', $request->getQuery('dispatch', false));
        }

        return $this->partialDispatch;
    }
}
