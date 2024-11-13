<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Controller;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinRouter;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Service\MeinBerlinAddonRelationSerivce;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Laminas\Feed\Writer\Feed;
use Symfony\Contracts\Translation\TranslatorInterface;
use DateTime;

class RssFeedController extends AbstractController
{
    public function __construct(
        protected readonly MeinBerlinRouter $meinBerlinRouter,
        protected readonly GlobalConfigInterface $demosplanConfig,
        protected readonly TranslatorInterface $translator,
    )
    {
    }

    /**
     * @Route("/api/{organisationId}/rss-feed/", name="rss_feed")
     * @throws Exception
     */
    public function generateRssFeed(
        MeinBerlinAddonOrgaRelation $correspondingAddonOrgaRelation,
        MeinBerlinAddonRelationSerivce $orgaRelationService
    ): Response
    {
        $externalWritePhaseKeys = $this->demosplanConfig->getExternalPhaseKeys('write');
        // Fetch procedures from the service
        $procedures = $orgaRelationService->getProceduresWithEndedParticipation($externalWritePhaseKeys);
        //base url : https://mein.berlin.de
        $url = $this->meinBerlinRouter->rssFeed($correspondingAddonOrgaRelation->getMeinBerlinOrganisationId());
        // Create the RSS feed
        $feed = new Feed();
        $feed->setTitle($this->translator->trans('mein.berlin.rss.feed.title'));
        $feed->setLink($url);
        $feed->setFeedLink($url, 'rss');
        $feed->setDateModified(new DateTime());

        // Add items to the feed with numbering
        $procedureCount = 1;
        // Add items to the feed
        /**
         * @var ProcedureInterface $procedure
         */
        foreach ($procedures as $procedure) {
            $entry = $feed->createEntry();
            $entry->setTitle("{$this->translator->trans('mein.berlin.building.plan.number')} {$procedureCount}: \"{$procedure->getExternalName()}\"");
            $entry->setDescription($this->formatDescription($procedure));
            $feed->addEntry($entry);

            // Increment the counter for the next procedure
            $procedureCount++;
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
        $desc = <<<EOD
                {$startDate} - {$endDate}\n
                {$procedure->getPublicParticipationPhaseName()}\n
                {$procedure->getDesc()}\n
                <a href="{$this->redirectToRoute('DemosPlan_procedure_public_detail')}">{$this->translator->trans('mein.berlin.more.informations')}</a>
                EOD;
        return nl2br($desc); // Convert newlines to <br> for proper HTML display
    }
}
