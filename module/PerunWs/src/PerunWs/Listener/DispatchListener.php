<?php

namespace PerunWs\Listener;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;
use PerunWs\Authentication;
use PerunWs\Exception\MissingDependencyException;
use PhlyRestfully\ApiProblem;


class DispatchListener extends AbstractListenerAggregate
{

    /**
     * @var Authentication\Adapter\AdapterInterface
     */
    protected $authenticationAdapter;


    /**
     * @return Authentication\Adapter\AdapterInterface
     */
    public function getAuthenticationAdapter()
    {
        return $this->authenticationAdapter;
    }


    /**
     * @param Authentication\Adapter\AdapterInterface $authenticationAdapter
     */
    public function setAuthenticationAdapter(Authentication\Adapter\AdapterInterface $authenticationAdapter)
    {
        $this->authenticationAdapter = $authenticationAdapter;
    }


    /**
     * {@inheritdoc}
     * @see \Zend\EventManager\ListenerAggregateInterface::attach()
     */
    public function attach(EventManagerInterface $events)
    {
        $this->listeners[] = $events->attach('dispatch', array(
            $this,
            'onDispatch'
        ), 1000);
        
        $this->listeners[] = $events->attach('dispatch.error', array(
            $this,
            'onDispatchError'
        ), 1000);
    }


    public function onDispatch(MvcEvent $event)
    {
        /* @var $request \Zend\Http\PhpEnvironment\Request */
        $request = $event->getRequest();
        
        /* @var $response \Zend\Http\PhpEnvironment\Response */
        $response = $event->getResponse();
        
        $authenticationAdapter = $this->getAuthenticationAdapter();
        if (null === $authenticationAdapter) {
            throw new MissingDependencyException('authentication adapter');
        }
        
        try {
            $clientInfo = $authenticationAdapter->authenticate($request);
        } catch (Authentication\Exception\AuthenticationException $e) {
            _dump('auth exception');
            _dump("$e");
            $event->stopPropagation(true);
            $response->setStatusCode(401);
            return $response;
        } catch (\Exception $e) {
            _dump("$e");
            $event->stopPropagation(true);
            $response->setStatusCode(500);
            return $response;
        }
    }


    public function onDispatchError(MvcEvent $e)
    {
        // FIXME - move to separate class
        $e->stopPropagation(true);
        
        /* @var $response \Zend\Http\PhpEnvironment\Response */
        $response = $e->getResponse();
        
        $error = $e->getError();
        
        if ($error) {
            _dump('DISPATCH ERROR: ' . $error);
        }
        
        $result = $e->getResult();
        if ($result) {
            $exception = $result->exception;
            if ($exception) {
                _dump(sprintf("EXCEPTION [%s]: %s", get_class($exception), $exception->getMessage()));
                _dump("$exception");
            }
        }
        
        $response->setStatusCode(500);
        
        return $response;
    }
}