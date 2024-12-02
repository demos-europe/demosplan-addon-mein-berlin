<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

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
    private RouterInterface $router;

    public function __construct(ParameterBagInterface $parameterBag, RouterInterface $router)
    {
        $baseUrl = $parameterBag->get('mein_berlin_api_baseurl') ??
            throw MeinBerlinRssFeedException::missingParameter('mein_berlin_api_baseurl');
        Assert::string($baseUrl);

        $routes = new RouteCollection();
        $routes->add(self::RSS_FEED, new Route('/api/{organisationId}/rss-feed'));

        $this->generator = new UrlGenerator($routes, RequestContext::fromUri($baseUrl));
        $this->router = $router;
    }

    public function rssFeed(string $organisationId): string
    {
        return $this->generator->generate(
            self::RSS_FEED,
            ['organisationId' => $organisationId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    public function publicDetail(string $procedureId): string
    {
        return $this->router->generate(
            'DemosPlan_procedure_public_detail',
            ['procedure' => $procedureId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
