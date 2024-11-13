<?php

declare(strict_types=1);

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic;

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinRssFeedException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Webmozart\Assert\Assert;

class MeinBerlinRouter
{
    private const RSS_FEED = 'rss_feed';
    /**
     * @var UrlGeneratorInterface
     */
    private UrlGeneratorInterface $generator;
    public function __construct(ParameterBagInterface $parameterBag)
    {
        if (!$parameterBag->has('mein_berlin_api_baseurl')) {
            throw MeinBerlinRssFeedException::missingParameter('mein_berlin_api_baseurl');
        }

        $routes = new RouteCollection();
        $routes->add(self::RSS_FEED, new Route('/api/{organisationId}/rss-feed'));

        $meinBerlinApiBaseurl = $parameterBag->get('mein_berlin_api_baseurl');
        Assert::string($meinBerlinApiBaseurl);
        $requestContext = RequestContext::fromUri($meinBerlinApiBaseurl);

        $this->generator = new UrlGenerator($routes, $requestContext);
    }

    public function rssFeed(string $organisationId): string
    {
        return $this->generator->generate(self::RSS_FEED, ['organisationId' => $organisationId], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
