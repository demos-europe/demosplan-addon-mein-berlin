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

class RssFeedController extends AbstractController
{
    public function __construct(
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
        $correspondingAddonOrgaRelations = $correspondingAddonOrgaRelationRepository->findBy(['meinBerlinOrganisationId' => $organisationId]);
        if ([] === $correspondingAddonOrgaRelations) {
            $this->logger->error('No corresponding addon organization relation found for organisationId: ' . $organisationId);
            return new Response('', 200);
        }

        // Aggregate procedures from all organizations sharing this meinBerlinId
        $proceduresByOrga = [];
        $feedOrgaNames = [];
        foreach ($correspondingAddonOrgaRelations as $orgaRelation) {
            $orga = $orgaRelation->getOrga();
            if (null === $orga) {
                continue;
            }
            $visibleProcedures = $orgaRelationService->getVisibleProcedures($orga);
            if ([] !== $visibleProcedures) {
                $feedOrgaNames[] = $orga->getName();
            }
            $proceduresByOrga[] = $visibleProcedures;
        }
        $procedures = array_merge(...$proceduresByOrga);

        // Re-sort aggregated procedures by end date descending
        usort($procedures, static fn (ProcedureInterface $a, ProcedureInterface $b): int =>
            $b->getPublicParticipationEndDateTimestamp() <=> $a->getPublicParticipationEndDateTimestamp()
        );
        // Create the RSS feed
        $feed = new Feed();
        $feed->setTitle($this->translator->trans('mein.berlin.rss.feed.title'));
        $feed->setDescription($this->translator->trans('mein.berlin.rss.feed.description', ['organisation' => implode(', ', $feedOrgaNames)]));
        $feed->setLink($this->router->generate('core_home', [], UrlGeneratorInterface::ABSOLUTE_URL));
        $feed->setFeedLink($this->router->generate('addon_mein_berlin_rss_feed', ['organisationId' => $organisationId], UrlGeneratorInterface::ABSOLUTE_URL), 'rss');
        $feed->setGenerator('demosplan');
        $feed->setDateModified(new DateTime());
        $feed->setEncoding('UTF-8');

        // Add items to the feed
        foreach ($procedures as $procedure) {
            $entry = $feed->createEntry();
            $entry->setTitle($procedure->getExternalName());
            $url = $this->router->generate('DemosPlan_procedure_public_detail', ['procedure' => $procedure->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
            $entry->setLink($url);
            $entry->setDescription($this->formatDescription($procedure, $url));
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

    private function formatDescription(ProcedureInterface $procedure, string $url): string
    {
        // Format the description to match the structure seen in the image
        $startDate = $procedure->getPublicParticipationStartDate()->format('d.m.Y');
        $endDate = $procedure->getPublicParticipationEndDate()->format('d.m.Y');
        $descParts = [];
        $descParts[] = "{$startDate} - {$endDate}";
        $descParts[] = $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getName();
        if ('' !== $procedure->getExternalDesc()) {
            $descParts[] = $procedure->getExternalDesc();
        }
        return implode("<br/>", $descParts);

    }
}
