<?php
declare(strict_types=1);

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Tests;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Service\MeinBerlinAddonRelationService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

class MeinBerlinAddonRelationServiceTest extends TestCase
{
    private MeinBerlinAddonRelationService $sut;

    protected function setUp(): void
    {
        $this->sut = new MeinBerlinAddonRelationService();
    }

    public function testReturnsOnlyProceduresInAllowedPhases(): void
    {
        $visible = $this->createProcedureMock('p-1', 'participation', 1000);
        $hidden = $this->createProcedureMock('p-2', 'configuration', 2000);
        $orga = $this->createOrgaMock([$visible, $hidden]);

        $result = $this->sut->getVisibleProcedures(['participation'], $orga);

        self::assertCount(1, $result);
        self::assertSame('p-1', array_values($result)[0]->getId());
    }

    public function testReturnsEmptyArrayWhenNoProceduresMatchPhase(): void
    {
        $procedure = $this->createProcedureMock('p-1', 'configuration', 1000);
        $orga = $this->createOrgaMock([$procedure]);

        $result = $this->sut->getVisibleProcedures(['participation'], $orga);

        self::assertSame([], $result);
    }

    public function testSortsProceduresByEndDateDescending(): void
    {
        $oldest = $this->createProcedureMock('p-oldest', 'participation', 1000);
        $middle = $this->createProcedureMock('p-middle', 'participation', 2000);
        $newest = $this->createProcedureMock('p-newest', 'participation', 3000);
        $orga = $this->createOrgaMock([$oldest, $middle, $newest]);

        $result = array_values($this->sut->getVisibleProcedures(['participation'], $orga));

        self::assertSame('p-newest', $result[0]->getId());
        self::assertSame('p-middle', $result[1]->getId());
        self::assertSame('p-oldest', $result[2]->getId());
    }

    public function testReturnsEmptyArrayForOrgaWithNoProcedures(): void
    {
        $orga = $this->createOrgaMock([]);

        self::assertSame([], $this->sut->getVisibleProcedures(['participation'], $orga));
    }

    public function testMultiplePhaseKeysAreRespected(): void
    {
        $procA = $this->createProcedureMock('p-1', 'participation', 1000);
        $procB = $this->createProcedureMock('p-2', 'evaluating', 2000);
        $procC = $this->createProcedureMock('p-3', 'configuration', 3000);
        $orga = $this->createOrgaMock([$procA, $procB, $procC]);

        $result = $this->sut->getVisibleProcedures(['participation', 'evaluating'], $orga);

        self::assertCount(2, $result);
    }

    /**
     * Simulates the controller's multi-org aggregation: calling getVisibleProcedures
     * per org and merging results, as done in RssFeedController::generateRssFeed.
     */
    public function testMultiOrgAggregationCollectsProceduresFromAllOrgs(): void
    {
        $orgaA = $this->createOrgaMock([
            $this->createProcedureMock('p-a1', 'participation', 3000),
        ]);
        $orgaB = $this->createOrgaMock([
            $this->createProcedureMock('p-b1', 'participation', 2000),
            $this->createProcedureMock('p-b2', 'participation', 1000),
        ]);

        $procedures = $this->aggregateVisibleProcedures(['participation'], [$orgaA, $orgaB]);

        self::assertCount(3, $procedures);
        self::assertSame('p-a1', $procedures[0]->getId());
        self::assertSame('p-b1', $procedures[1]->getId());
        self::assertSame('p-b2', $procedures[2]->getId());
    }

    /**
     * Real-world scenario: multiple orgs share the same external ID,
     * but only one has procedures in a visible phase. Others are either
     * empty or only have procedures in non-visible phases like 'configuration'.
     */
    public function testMultiOrgAggregationWithMixedVisibility(): void
    {
        $orgaWithVisible = $this->createOrgaMock([
            $this->createProcedureMock('p-1', 'participation', 2000),
        ], 'Org A');
        $orgaAllHidden = $this->createOrgaMock([
            $this->createProcedureMock('p-2', 'configuration', 3000),
        ], 'Org B');
        $orgaEmpty = $this->createOrgaMock([], 'Org C');

        $orgas = [$orgaWithVisible, $orgaAllHidden, $orgaEmpty];
        $phaseKeys = ['participation', 'evaluating'];

        $procedures = $this->aggregateVisibleProcedures($phaseKeys, $orgas);
        $orgaNames = $this->collectOrgaNamesWithVisibleProcedures($phaseKeys, $orgas);

        self::assertCount(1, $procedures);
        self::assertSame('p-1', $procedures[0]->getId());
        self::assertCount(1, $orgaNames);
        self::assertSame('Org A', $orgaNames[0]);
    }

    /**
     * When multiple orgs contribute visible procedures, all their names
     * should be collected for the feed description.
     */
    public function testMultiOrgAggregationShowsAllContributingOrgNames(): void
    {
        $orgaA = $this->createOrgaMock([
            $this->createProcedureMock('p-a1', 'participation', 3000),
            $this->createProcedureMock('p-a2', 'evaluating', 1000),
        ], 'Org A');
        $orgaB = $this->createOrgaMock([
            $this->createProcedureMock('p-b1', 'participation', 2000),
        ], 'Org B');
        $orgaNonContributing = $this->createOrgaMock([
            $this->createProcedureMock('p-c1', 'configuration', 4000),
        ], 'Org C');

        $orgas = [$orgaA, $orgaB, $orgaNonContributing];
        $phaseKeys = ['participation', 'evaluating'];

        $procedures = $this->aggregateVisibleProcedures($phaseKeys, $orgas);
        $orgaNames = $this->collectOrgaNamesWithVisibleProcedures($phaseKeys, $orgas);

        self::assertCount(3, $procedures);
        self::assertSame('Org A, Org B', implode(', ', $orgaNames));
    }

    /**
     * Replicates the aggregation logic from RssFeedController::generateRssFeed.
     *
     * @param string[]        $phaseKeys
     * @param OrgaInterface[] $orgas
     *
     * @return ProcedureInterface[]
     */
    private function aggregateVisibleProcedures(array $phaseKeys, array $orgas): array
    {
        $proceduresByOrga = [];
        foreach ($orgas as $orga) {
            $proceduresByOrga[] = $this->sut->getVisibleProcedures($phaseKeys, $orga);
        }

        $procedures = array_merge(...$proceduresByOrga);
        usort($procedures, static fn (ProcedureInterface $a, ProcedureInterface $b): int =>
            $b->getPublicParticipationEndDateTimestamp() <=> $a->getPublicParticipationEndDateTimestamp()
        );

        return $procedures;
    }

    /**
     * Replicates the org name collection from RssFeedController::generateRssFeed.
     *
     * @param string[]        $phaseKeys
     * @param OrgaInterface[] $orgas
     *
     * @return string[]
     */
    private function collectOrgaNamesWithVisibleProcedures(array $phaseKeys, array $orgas): array
    {
        $names = [];
        foreach ($orgas as $orga) {
            $visibleProcedures = $this->sut->getVisibleProcedures($phaseKeys, $orga);
            if ([] !== $visibleProcedures) {
                $names[] = $orga->getName();
            }
        }

        return $names;
    }

    private function createProcedureMock(string $id, string $phase, int $endTimestamp): ProcedureInterface
    {
        $procedure = $this->createMock(ProcedureInterface::class);
        $procedure->method('getId')->willReturn($id);
        $procedure->method('getPublicParticipationPhase')->willReturn($phase);
        $procedure->method('getPublicParticipationEndDateTimestamp')->willReturn($endTimestamp);

        return $procedure;
    }

    private function createOrgaMock(array $procedures, string $name = 'Test Org'): OrgaInterface
    {
        $orga = $this->createMock(OrgaInterface::class);
        $orga->method('getName')->willReturn($name);
        $orga->method('getProcedures')->willReturn(new ArrayCollection($procedures));

        return $orga;
    }
}
