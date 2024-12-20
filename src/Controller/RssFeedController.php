<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Controller;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Service\MeinBerlinAddonRelationService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Laminas\Feed\Writer\Feed;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use DateTime;
use Webmozart\Assert\Assert;

class RssFeedController extends AbstractController
{
    public function __construct(
        protected readonly GlobalConfigInterface $demosplanConfig,
        private readonly RouterInterface $router,
        protected readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    )
    {
    }

    /**
     * @Route("mein_berlin/rss/{organisationId}", name="addon_mein_berlin_rss_feed")
     * @throws Exception
     */
    public function generateRssFeed(
        MeinBerlinAddonOrgaRelationRepository $correspondingAddonOrgaRelationRepository,
        MeinBerlinAddonRelationService $orgaRelationService,
        string $organisationId
    ): Response
    {
        $correspondingAddonOrgaRelation = $correspondingAddonOrgaRelationRepository->findOneBy(['meinBerlinOrganisationId' => $organisationId]);
        if ($correspondingAddonOrgaRelation === null) {
            $this->logger->error('No corresponding addon organization relation found for organisationId: ' . $organisationId);
            return new Response('', 200);
        }
        Assert::notNull($correspondingAddonOrgaRelation);
        $demosplanOrga = $correspondingAddonOrgaRelation->getOrga();
        Assert::notNull($demosplanOrga);
        $externalPhaseKeys = $this->demosplanConfig->getExternalPhaseKeys('read||write');
        // Fetch procedures from the service
        $procedures = $orgaRelationService->getVisibleProcedures(
            $externalPhaseKeys,
            $demosplanOrga
        );
        // Create the RSS feed
        $feed = new Feed();
        $feed->setTitle($this->translator->trans('mein.berlin.rss.feed.title'));
        $feed->setDescription($this->translator->trans('mein.berlin.rss.feed.description', ['organisation' => $demosplanOrga->getName()]));
        $feed->setLink($this->router->generate('core_home', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $feed->setFeedLink($this->router->generate('addon_mein_berlin_rss_feed', ['organisationId' => $organisationId], UrlGeneratorInterface::ABSOLUTE_URL), 'rss');
        $feed->setGenerator('demosplan');
        $feed->setDateModified(new DateTime());

        // Add items to the feed
        foreach ($procedures as $procedure) {
            $entry = $feed->createEntry();
            $entry->setTitle(sprintf('%s: %s',
                $this->translator->trans('mein.berlin.rss.feed.plan.prefix'),
                $procedure->getExternalName()
            ));
            $entry->setDescription($this->formatDescription($procedure));
            $feed->addEntry($entry);
        }

        // Generate RSS XML
        $rssFeed = $feed->export('rss');

        // Return response with XML content
        $response = new Response($rssFeed, 200);
        $response->headers->set('Content-Type', 'application/rss+xml');

        // Cache for 1 hour
        $response->setPublic();
        $response->setMaxAge(3600); // Cache for 1 hour

        return $response;
    }

    private function formatDescription(ProcedureInterface $procedure): string
    {
        // Format the description to match the structure seen in the image
        $startDate = $procedure->getPublicParticipationStartDate()->format('d.m.Y');
        $endDate = $procedure->getPublicParticipationEndDate()->format('d.m.Y');
        $descParts = [];
        $descParts[] = "{$startDate} - {$endDate}";
        if ('' !== $procedure->getPublicParticipationPhaseName()) {
            $descParts[] = $procedure->getPublicParticipationPhaseName();
        }
        if ('' !== $procedure->getExternalDesc()) {
            $descParts[] = $procedure->getExternalDesc();
        }
        $descParts[] = sprintf('<a href="%s">%s</a>',
            $this->router->generate('DemosPlan_procedure_public_detail', ['procedure' => $procedure->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            $this->translator->trans('mein.berlin.more.information'));
        return implode("<br/>", $descParts);

    }
}
