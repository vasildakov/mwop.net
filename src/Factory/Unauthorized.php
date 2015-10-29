<?php
namespace Mwop\Factory;

use Mwop\Unauthorized as Middleware;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

class Unauthorized
{
    public function __invoke($services)
    {
        return new Middleware(
            $services->get(TemplateRendererInterface::class),
            $services->get(RouterInterface::class)
        );
    }
}
