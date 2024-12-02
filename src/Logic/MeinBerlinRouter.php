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

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
class MeinBerlinRouter
{
    private const RSS_FEED = 'rss_feed';

    private UrlGeneratorInterface $generator;
    private RouterInterface $router;
    private ParameterBagInterface $parameterBag;

    public function __construct(UrlGeneratorInterface $generator, RouterInterface $router, ParameterBagInterface $parameterBag)
    {
        $this->router = $router;
        $this->generator = $generator;
        $this->parameterBag = $parameterBag;
    }

    public function rssFeed(string $organisationId): string
    {
        $baseUrl = $this->parameterBag->get('mein_berlin_api_baseurl');
        return $baseUrl . $this->generator->generate(
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
