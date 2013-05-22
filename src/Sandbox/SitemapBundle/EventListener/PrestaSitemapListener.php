<?php
/**
 * This file is part of the PrestaCms Sandbox project.
 *
 * @author David Epely <depely@prestaconcept.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Sandbox\SitemapBundle\EventListener;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Cmf\Bundle\RoutingExtraBundle\Document\Route; //depecrated in next cmf
use Symfony\Cmf\Bundle\RoutingExtraBundle\Document\RedirectRoute;

use Presta\SitemapBundle\Sitemap\Url\GoogleImage;
use Presta\SitemapBundle\Sitemap\Url\GoogleImageUrlDecorator;

use Presta\SitemapBundle\Service\SitemapListenerInterface;
use Presta\SitemapBundle\Event\SitemapPopulateEvent;
use Presta\SitemapBundle\Sitemap\Url\UrlConcrete;
use Presta\CMSCoreBundle\Model\WebsiteManager;
use Presta\CMSCoreBundle\Document\Page;
use Presta\CMSCoreBundle\Document\Block;

/**
 * Description of PrestaSitemapListener
 *
 * @author David Epely <depely@prestaconcept.net>
 */
class PrestaSitemapListener implements SitemapListenerInterface
{
    protected $container;

    /**
     * @param \Symfony\Component\Routing\RouterInterface $router
     * @param \Presta\CMSCoreBundle\Model\WebsiteManager $websiteManager
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    /**
     * @param \Presta\SitemapBundle\Event\SitemapPopulateEvent $event
     */
    public function populateSitemap(SitemapPopulateEvent $event)
    {
        $routeCollection = $this->container->get('presta_cms.route_manager')->findRoutesByWebsite($this->getWebsite());

        foreach ($routeCollection->all() as $route) {
            if ($route instanceof RedirectRoute) {
                continue;
            }
            //add homepage url to the urlset named default
            $event->getGenerator()->addUrl(
                $this->getSitemapUrlFrom($route),
                'cms'
            );
        }
    }

    /**
     *
     * @param  \Symfony\Cmf\Bundle\RoutingExtraBundle\Document\Route $route
     * @return \Presta\SitemapBundle\Sitemap\Url\UrlConcrete
     */
    protected function getSitemapUrlFrom(Route $route)
    {
        $url = new UrlConcrete($this->container->get('router')->generate($route, array(), true));

        $page = $route->getRouteContent();

        if ($this->container->has('sonata.media.manager.media')) {
            $images = array();

            foreach ($page->getZones() as $zone) {
                foreach ($zone->getBlocks() as $block) {
                    $images = array_merge($images, $this->getGoogleImagesFromBlock($block));
                }
            }

            if (count($images)) {

                $url = new GoogleImageUrlDecorator($url);

                foreach ($images as $k => $image) {
                    try {
                        $url->addImage($image);
                    } catch (\Presta\SitemapBundle\Exception\GoogleImageException $e) {
                        //1k img max
                        break;
                    }
                }
            }
        }

        return $url;
    }

    /**
     * Get an array of GoogleImage
     *
     * @param  \Presta\CMSCoreBundle\Document\Block $block
     * @return array
     */
    protected function getGoogleImagesFromBlock(Block $block)
    {
        if (!$this->container->has('sonata.media.manager.media')) {
            return array();
        }

        $images = array();

        if (in_array($block->getType(), array('presta_cms.block.media', 'presta_cms.block.media_advanced'))) {
            $settings = $block->getSettings();
            $media = $this->container->get('sonata.media.manager.media')->findOneBy(array('id' => $settings['media']));
            $provider = $this->container->get('sonata.media.pool')->getProvider($media->getProviderName());
            $src = $this->getAbsolutePath($provider->generatePublicUrl($media, $settings['format']));

            if (strpos($src, '/') === 0) {
                throw new \InvalidArgumentException('image src must be abolute for sitemap.xml');
            }

            $images[] = new GoogleImage($src, null, null, $media->getName());
        }

        if ($block->getType() == 'presta_cms.block.container') {
            //children are not linked and are hardcoded : col1..4
            $ids = array();
            for ($i = 1; $i <= 4; $i++) {
                $ids[] = $block->getId() . '/col' . $i;
            }

            $blocks = $this->container->get('doctrine_phpcr')->getManager()->getRepository('Presta\CMSCoreBundle\Document\Block')->findMany($ids);

            foreach ($blocks as $child) {
                $images = array_merge($images, $this->getGoogleImagesFromBlock($child));
            }
        }

        return $images;
    }

    protected function getAbsolutePath($url)
    {
        if (strpos($url, '/') === 0) {
            //path is relative
            $url = $this->container->get('request')->getSchemeAndHttpHost() . $url;
        }

        return $url;
    }

    /**
     * @return \Presta\CMSCoreBundle\Document\Website
     */
    protected function getWebsite()
    {
        return $this->container->get('presta_cms.website_manager')->getCurrentWebsite();
    }
}
