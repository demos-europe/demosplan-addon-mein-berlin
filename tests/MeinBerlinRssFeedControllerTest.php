<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Tests;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseDefinitionInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Controller\RssFeedController;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Service\MeinBerlinAddonRelationService;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Service\MeinBerlinRssPhaseFilter;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MeinBerlinRssFeedControllerTest extends TestCase
{
    private const EARLY = 'Frühzeitige Beteiligung Öffentlichkeit - § 3 (1) BauGB';
    private const RUNNING = 'Beteiligung Öffentlichkeit';
    private const FINISHED = 'Auswertung Öffentlichkeit';

    private RssFeedController|null $sut = null;
    private MeinBerlinAddonOrgaRelationRepository|null $relationRepository = null;
    private MeinBerlinAddonRelationService|null $relationService = null;
    private MeinBerlinRssPhaseFilter|null $phaseFilter = null;

    protected function setUp(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name, array $parameters = []): string => 'https://example.test/'.$name.'?'.http_build_query($parameters)
        );
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $this->sut = new RssFeedController($router, $translator, $this->createMock(LoggerInterface::class));
        $this->phaseFilter = new MeinBerlinRssPhaseFilter();
        $this->relationService = new MeinBerlinAddonRelationService($this->phaseFilter);

        $orga = $this->createMock(OrgaInterface::class);
        $orga->method('getName')->willReturn('Bezirk');
        $orga->method('getProcedures')->willReturn(new ArrayCollection([
            $this->createProcedure('running', 'write', 3000, self::RUNNING),
            $this->createProcedure('early', 'write', 2000, self::EARLY),
            $this->createProcedure('finished', 'read', 1000, self::FINISHED),
            $this->createProcedure('hidden', 'hidden', 4000, 'Konfiguration'),
        ]));
        $relation = $this->createMock(MeinBerlinAddonOrgaRelation::class);
        $relation->method('getOrga')->willReturn($orga);
        $this->relationRepository = $this->createMock(MeinBerlinAddonOrgaRelationRepository::class);
        $this->relationRepository->method('findBy')->willReturn([$relation]);
    }

    public function testWithoutFilterAllVisibleProceduresAreListed(): void
    {
        $items = $this->getItemTitles($this->requestFeed([]));

        // unchanged behaviour: everything that is not hidden, newest end of participation first
        self::assertSame(['running', 'early', 'finished'], $items);
    }

    public function testEmptyParameterIsNoFilter(): void
    {
        self::assertCount(3, $this->getItemTitles($this->requestFeed(['VERFAHRENSSCHRITT' => ''])));
    }

    public function testFiltersBySingleName(): void
    {
        $items = $this->getItemTitles($this->requestFeed(['VERFAHRENSSCHRITT' => '"Auswertung Öffentlichkeit"']));

        self::assertSame(['finished'], $items);
    }

    public function testFiltersByListOfTheTicket(): void
    {
        $items = $this->getItemTitles($this->requestFeed([
            'VERFAHRENSSCHRITT' => '("Beteiligung Öffentlichkeit","Frühzeitige Beteiligung Öffentlichkeit - § 3 (1) BauGB")',
        ]));

        self::assertSame(['running', 'early'], $items);
    }

    public function testFiltersByArrayNotation(): void
    {
        $items = $this->getItemTitles($this->requestFeed([
            'VERFAHRENSSCHRITT' => ['Auswertung Öffentlichkeit', 'Beteiligung Öffentlichkeit'],
        ]));

        self::assertSame(['running', 'finished'], $items);
    }

    public function testParameterNameAndNamesIgnoreCase(): void
    {
        $items = $this->getItemTitles($this->requestFeed(['verfahrensschritt' => 'AUSWERTUNG öffentlichkeit']));

        self::assertSame(['finished'], $items);
    }

    public function testUnknownNameResultsInAnEmptyButValidFeed(): void
    {
        $response = $this->requestFeed(['VERFAHRENSSCHRITT' => 'Gibt es nicht']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->getItemTitles($response));
    }

    public function testHiddenProceduresStayHiddenEvenIfTheirPhaseIsNamed(): void
    {
        self::assertSame([], $this->getItemTitles($this->requestFeed(['VERFAHRENSSCHRITT' => 'Konfiguration'])));
    }

    public function testFeedLinkContainsTheFilterOnlyIfOneIsSet(): void
    {
        $filtered = (string) $this->requestFeed(['VERFAHRENSSCHRITT' => 'Auswertung Öffentlichkeit'])->getContent();
        $unfiltered = (string) $this->requestFeed([])->getContent();

        self::assertStringContainsString('VERFAHRENSSCHRITT=', $filtered);
        self::assertStringNotContainsString('VERFAHRENSSCHRITT', $unfiltered);
    }

    public function testResponseIsAPubliclyCachedRssFeed(): void
    {
        $response = $this->requestFeed(['VERFAHRENSSCHRITT' => 'Auswertung Öffentlichkeit']);

        self::assertSame('application/rss+xml', $response->headers->get('Content-Type'));
        self::assertTrue($response->headers->hasCacheControlDirective('public'));
        self::assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function requestFeed(array $query): Response
    {
        return $this->sut->generateRssFeed(
            $this->relationRepository,
            $this->relationService,
            $this->phaseFilter,
            Request::create('/mein_berlin/rss/29', 'GET', $query),
            '29'
        );
    }

    /**
     * @return list<string>
     */
    private function getItemTitles(Response $response): array
    {
        $xml = new SimpleXMLElement((string) $response->getContent());
        $titles = [];
        foreach ($xml->channel->item as $item) {
            $titles[] = (string) $item->title;
        }

        return $titles;
    }

    private function createProcedure(string $name, string $permissionSet, int $endTimestamp, string $phaseName): ProcedureInterface
    {
        $phaseDefinition = $this->createMock(ProcedurePhaseDefinitionInterface::class);
        $phaseDefinition->method('getName')->willReturn($phaseName);
        $phase = $this->createMock(ProcedurePhaseInterface::class);
        $phase->method('getPhaseDefinition')->willReturn($phaseDefinition);

        $procedure = $this->createMock(ProcedureInterface::class);
        $procedure->method('getId')->willReturn('id-'.$name);
        $procedure->method('getExternalName')->willReturn($name);
        $procedure->method('getExternalDesc')->willReturn('');
        $procedure->method('getPublicParticipationPhasePermissionset')->willReturn($permissionSet);
        $procedure->method('getPublicParticipationEndDateTimestamp')->willReturn($endTimestamp);
        $procedure->method('getPublicParticipationStartDate')->willReturn(new DateTime('2026-01-01'));
        $procedure->method('getPublicParticipationEndDate')->willReturn(new DateTime('2026-02-01'));
        $procedure->method('getPublicParticipationPhaseObject')->willReturn($phase);

        return $procedure;
    }
}
