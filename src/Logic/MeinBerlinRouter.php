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

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class MeinBerlinRouter
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function rssFeed(string $organisationId): string
    {
        return $this->router->generate(
            'rss_feed',
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
