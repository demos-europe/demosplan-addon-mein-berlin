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
use Symfony\Component\Routing\RouterInterface;
use Webmozart\Assert\Assert;

class MeinBerlinRouter
{
    private const RSS_FEED = 'rss_feed';

    private UrlGeneratorInterface $generator;
    public function __construct(ParameterBagInterface $parameterBag, RouterInterface $router)
    {
        $baseUrl = $parameterBag->get('mein_berlin_api_baseurl') ??
            throw MeinBerlinRssFeedException::missingParameter('mein_berlin_api_baseurl');
        Assert::string($baseUrl);

        $routes = new RouteCollection();
        $routes->add(self::RSS_FEED, new Route('/api/{organisationId}/rss-feed'));

        $this->generator = new UrlGenerator($routes, RequestContext::fromUri($baseUrl));
    }

    public function rssFeed(string $organisationId): string
    {
        return $this->generator->generate(
            self::RSS_FEED,
            ['organisationId' => $organisationId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
