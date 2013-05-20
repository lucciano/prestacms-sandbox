<?php

namespace Sandbox\SitemapBundle\EventListener;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Cmf\Bundle\RoutingExtraBundle\Document\Route; //depecrated in next cmf

use Presta\SitemapBundle\Service\SitemapListenerInterface;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Presta\CMSCoreBundle\Model\WebsiteManager;
use Presta\CMSCoreBundle\Model\RouteManager;

/**
 * Description of PrestaSitemapListener
 *
 * @author David Epely <depely@prestaconcept.net>
 */
class PrestaSitemapListener implements SitemapListenerInterface
{
    protected $router;
    protected $websiteManager;
    protected $routeManager;

    /**
     * @param \Symfony\Component\Routing\RouterInterface $router
     * @param \Presta\CMSCoreBundle\Model\WebsiteManager $websiteManager
     */
    public function __construct(RouterInterface $router, WebsiteManager $websiteManager,  RouteManager $routeManager)
    {
        $this->router = $router;
        $this->websiteManager = $websiteManager;
        $this->routeManager = $routeManager;
    }

    /**
     * @param \Presta\SitemapBundle\Event\SitemapPopulateEvent $event
     */
    public function populateSitemap(SitemapPopulateEvent $event)
    {
        $routeCollection = $this->routeManager->findRoutesByWebsite($this->getWebsite());

        foreach ($routeCollection->all() as $route) {

            $url = $this->router->generate($route, array(), true);
            //add homepage url to the urlset named default
            $event->getGenerator()->addUrl(
                new UrlConcrete($url),
                'cms'
            );
        }
    }

    /**
     * @return \Presta\CMSCoreBundle\Document\Website
     */
    protected function getWebsite()
    {
        return $this->websiteManager->getCurrentWebsite();
    }
}
