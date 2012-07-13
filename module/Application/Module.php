<?php

namespace Application;

use Traversable;
use Zend\Config\Config;
use Zend\Stdlib\ArrayUtils;
use Zend\View\Model;

class Module
{
    protected static $layout;
    protected $config;

    public function getAutoloaderConfig()
    {
        return array(
            'Zend\Loader\ClassMapAutoloader' => array(
                __DIR__ . '/autoload_classmap.php'
            ),
        );
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap($e)
    {
        $app          = $e->getApplication();
        $services     = $app->getServiceManager();
        $this->config = $services->get('config');
        $this->initAcls($e);
        $this->initView($e);
    }

    public function initAcls($e)
    {
        $app = $e->getParam('application');
        $app->getEventManager()->attach('route', array($this, 'checkAcls'), -100);
    }

    public function initView($e)
    {
        $app      = $e->getApplication();
        $services = $app->getServiceManager();
        $config   = $this->config;
        $view     = $services->get('ViewRenderer');

        $persistent = $view->placeholder('layout');
        foreach ($config['view'] as $var => $value) {
            if (is_array($value)) {
                $value = new Config($value, true);
            }
            $persistent->{$var} = $value;
        }

        $view->headTitle()->setSeparator(' :: ')
                          ->setAutoEscape(false)
                          ->append('phly, boy, phly');
        $view->headLink(array(
            'rel'  => 'shortcut icon',
            'type' => 'image/vnd.microsoft.icon',
            'href' => '/images/Application/favicon.ico',
        ));
    }

    public static function prepareCompilerView($view, $config, $services)
    {
        $renderer = $services->get('ViewRenderer');
        $view->addRenderingStrategy(function($e) use ($renderer) {
            return $renderer;
        }, 100);

        self::$layout = $layout   = new Model\ViewModel();
        $layout->setTemplate('layout');
        $view->addResponseStrategy(function($e) use ($layout, $renderer) {
            $result = $e->getResult();
            $layout->setVariable('content', $result);
            $page   = $renderer->render($layout);
            $e->setResult($page);

            // Cleanup
            $headTitle = $renderer->plugin('headtitle');
            $headTitle->getContainer()->exchangeArray(array());
            $headTitle->append('phly, boy, phly');

            $headLink = $renderer->plugin('headLink');
            $headLink->getContainer()->exchangeArray(array());
            $headLink(array(
                'rel' => 'shortcut icon',
                'type' => 'image/vnd.microsoft.icon',
                'href' => '/images/Application/favicon.ico',
            ));

            $headScript = $renderer->plugin('headScript');
            $headScript->getContainer()->exchangeArray(array());
        }, 100);
    }

    public static function handleTagCloud($cloud, $view, $config, $locator)
    {
        if (!self::$layout) {
            return;
        }

        self::$layout->setVariable('footer', sprintf(
            "<h4>Tag Cloud</h4>\n<div class=\"cloud\">\n%s</div>\n",
            $cloud->render()
        ));
    }

    public function checkAcls($e)
    {
        $routeMatch = $e->getRouteMatch();
        if (!$routeMatch) {
            return;
        }

        $controller = $routeMatch->getParam('controller', false);
        if ($controller != 'Application\Controller\PageController') {
            return;
        }

        $protectedActions = $this->config['authentication']['Application\Controller\PageController'];
        $action           = $routeMatch->getParam('action');
        if (!in_array($action, $protectedActions)) {
            // does not require authorization
            return;
        }

        $app     = $e->getApplication();
        $locator = $app->getServiceManager();
        $auth    = $locator->get('zfcuser_auth_service');
        if ($auth->hasIdentity()) {
            // authorized
            return;
        }
        $baseModel = $e->getViewModel();
        return $this->createUnauthorizedResponse($app, $baseModel);
    }

    public function createUnauthorizedResponse($app, $baseModel)
    {
        $request  = $app->getRequest();
        $response = $app->getResponse();
        $locator  = $app->getServiceManager();
        $view     = $locator->get('View');
        $view->setRequest($request);
        $view->setResponse($response);

        $model = new Model\ViewModel();
        $model->setTemplate('pages/401');

        if ($baseModel instanceof Model\ModelInterface) {
            $baseModel->addChild($model);
            $model = $baseModel;
        }

        $view->render($model);
        return $response;
    }
}
