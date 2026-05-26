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
        $visible = $this->createProcedureMock('p-1', 'read', 1000);
        $hidden = $this->createProcedureMock('p-2', 'preparation', 2000);
        $orga = $this->createOrgaMock([$visible, $hidden]);

        $result = $this->sut->getVisibleProcedures($orga);

        self::assertCount(1, $result);
        self::assertSame('p-1', array_values($result)[0]->getId());
    }

    public function testReturnsEmptyArrayWhenNoProceduresMatchPhase(): void
    {
        $procedure = $this->createProcedureMock('p-1', 'preparation', 1000);
        $orga = $this->createOrgaMock([$procedure]);

        $result = $this->sut->getVisibleProcedures($orga);

        self::assertSame([], $result);
    }

    public function testSortsProceduresByEndDateDescending(): void
    {
        $oldest = $this->createProcedureMock('p-oldest', 'read', 1000);
        $middle = $this->createProcedureMock('p-middle', 'read', 2000);
        $newest = $this->createProcedureMock('p-newest', 'read', 3000);
        $orga = $this->createOrgaMock([$oldest, $middle, $newest]);

        $result = array_values($this->sut->getVisibleProcedures($orga));

        self::assertSame('p-newest', $result[0]->getId());
        self::assertSame('p-middle', $result[1]->getId());
        self::assertSame('p-oldest', $result[2]->getId());
    }

    public function testReturnsEmptyArrayForOrgaWithNoProcedures(): void
    {
        $orga = $this->createOrgaMock([]);

        self::assertSame([], $this->sut->getVisibleProcedures($orga));
    }

    public function testBothReadAndWritePermissionSetsAreVisible(): void
    {
        $procRead = $this->createProcedureMock('p-1', 'read', 1000);
        $procWrite = $this->createProcedureMock('p-2', 'write', 2000);
        $procHidden = $this->createProcedureMock('p-3', 'preparation', 3000);
        $orga = $this->createOrgaMock([$procRead, $procWrite, $procHidden]);

        $result = $this->sut->getVisibleProcedures($orga);

        self::assertCount(2, $result);
    }

    /**
     * Simulates the controller's multi-org aggregation: calling getVisibleProcedures
     * per org and merging results, as done in RssFeedController::generateRssFeed.
     */
    public function testMultiOrgAggregationCollectsProceduresFromAllOrgs(): void
    {
        $orgaA = $this->createOrgaMock([
            $this->createProcedureMock('p-a1', 'read', 3000),
        ]);
        $orgaB = $this->createOrgaMock([
            $this->createProcedureMock('p-b1', 'read', 2000),
            $this->createProcedureMock('p-b2', 'read', 1000),
        ]);

        $procedures = $this->aggregateVisibleProcedures([$orgaA, $orgaB]);

        self::assertCount(3, $procedures);
        self::assertSame('p-a1', $procedures[0]->getId());
        self::assertSame('p-b1', $procedures[1]->getId());
        self::assertSame('p-b2', $procedures[2]->getId());
    }

    /**
     * Real-world scenario: multiple orgs share the same external ID,
     * but only one has procedures in a visible phase. Others are either
     * empty or only have procedures in non-visible phases.
     */
    public function testMultiOrgAggregationWithMixedVisibility(): void
    {
        $orgaWithVisible = $this->createOrgaMock([
            $this->createProcedureMock('p-1', 'read', 2000),
        ], 'Org A');
        $orgaAllHidden = $this->createOrgaMock([
            $this->createProcedureMock('p-2', 'preparation', 3000),
        ], 'Org B');
        $orgaEmpty = $this->createOrgaMock([], 'Org C');

        $orgas = [$orgaWithVisible, $orgaAllHidden, $orgaEmpty];

        $procedures = $this->aggregateVisibleProcedures($orgas);
        $orgaNames = $this->collectOrgaNamesWithVisibleProcedures($orgas);

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
            $this->createProcedureMock('p-a1', 'read', 3000),
            $this->createProcedureMock('p-a2', 'write', 1000),
        ], 'Org A');
        $orgaB = $this->createOrgaMock([
            $this->createProcedureMock('p-b1', 'read', 2000),
        ], 'Org B');
        $orgaNonContributing = $this->createOrgaMock([
            $this->createProcedureMock('p-c1', 'preparation', 4000),
        ], 'Org C');

        $orgas = [$orgaA, $orgaB, $orgaNonContributing];

        $procedures = $this->aggregateVisibleProcedures($orgas);
        $orgaNames = $this->collectOrgaNamesWithVisibleProcedures($orgas);

        self::assertCount(3, $procedures);
        self::assertSame('Org A, Org B', implode(', ', $orgaNames));
    }

    /**
     * Replicates the aggregation logic from RssFeedController::generateRssFeed.
     *
     * @param OrgaInterface[] $orgas
     * @return ProcedureInterface[]
     */
    private function aggregateVisibleProcedures(array $orgas): array
    {
        $proceduresByOrga = [];
        foreach ($orgas as $orga) {
            $proceduresByOrga[] = $this->sut->getVisibleProcedures($orga);
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
     * @param OrgaInterface[] $orgas
     * @return string[]
     */
    private function collectOrgaNamesWithVisibleProcedures(array $orgas): array
    {
        $names = [];
        foreach ($orgas as $orga) {
            $visibleProcedures = $this->sut->getVisibleProcedures($orga);
            if ([] !== $visibleProcedures) {
                $names[] = $orga->getName();
            }
        }

        return $names;
    }

    private function createProcedureMock(string $id, string $permissionset, int $endTimestamp): ProcedureInterface
    {
        $procedure = $this->createMock(ProcedureInterface::class);
        $procedure->method('getId')->willReturn($id);
        $procedure->method('getPublicParticipationPhasePermissionset')->willReturn($permissionset);
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
