<?php
declare(strict_types=1);

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Tests;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinOrganisationIdChangeHandler;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MeinBerlinOrganisationIdChangeHandlerTest extends TestCase
{
    private MeinBerlinOrganisationIdChangeHandler $sut;
    private MockObject&MeinBerlinAddonOrgaRelationRepository $repository;
    private MockObject&LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(MeinBerlinAddonOrgaRelationRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sut = new MeinBerlinOrganisationIdChangeHandler($this->logger, $this->repository);
    }

    public function testReleaseClearsBplanIdOfCommunicatedProceduresWhenIdChanges(): void
    {
        $relation = $this->createRelation('25');
        $first = $this->createAddonEntity('procedure-1', 'bplan-1', 'First');
        $second = $this->createAddonEntity('procedure-2', 'bplan-2', 'Second');
        $this->repository->method('getProceduresOfOrgaWithExistingBplanId')->with($relation)
            ->willReturn([$first, $second]);
        $this->logger->expects(self::exactly(2))->method('info')->with(
            self::anything(),
            self::callback(static fn (array $context): bool => '25' === $context['oldMeinBerlinOrganisationId']
                && '29' === $context['newMeinBerlinOrganisationId']
                && str_starts_with($context['oldBplanId'], 'bplan-'))
        );

        $released = $this->sut->releaseCommunicatedProcedures($relation, '29');

        self::assertSame(2, $released);
        self::assertSame('', $first->getBplanId());
        self::assertSame('', $second->getBplanId());
    }

    public function testReleaseClearsBplanIdWhenIdIsReset(): void
    {
        $relation = $this->createRelation('25');
        $addonEntity = $this->createAddonEntity('procedure-1', 'bplan-1', 'First');
        $this->repository->method('getProceduresOfOrgaWithExistingBplanId')->willReturn([$addonEntity]);

        $released = $this->sut->releaseCommunicatedProcedures($relation, '');

        self::assertSame(1, $released);
        self::assertSame('', $addonEntity->getBplanId());
    }

    public function testReleaseChangesNothingWhenIdStaysTheSame(): void
    {
        $relation = $this->createRelation('25');
        $this->repository->expects(self::never())->method('getProceduresOfOrgaWithExistingBplanId');

        $released = $this->sut->releaseCommunicatedProcedures($relation, '25');

        self::assertSame(0, $released);
    }

    public function testReleaseReturnsZeroWithoutCommunicatedProcedures(): void
    {
        $relation = $this->createRelation('25');
        $this->repository->method('getProceduresOfOrgaWithExistingBplanId')->willReturn([]);
        $this->logger->expects(self::never())->method('info');

        self::assertSame(0, $this->sut->releaseCommunicatedProcedures($relation, '29'));
    }

    public function testInfoSkipsNamesQueryWithoutCommunicatedProcedures(): void
    {
        $relation = $this->createRelation('25');
        $this->repository->method('countProceduresOfOrgaWithExistingBplanId')->with($relation)->willReturn(0);
        $this->repository->expects(self::never())->method('getProceduresOfOrgaWithExistingBplanId');

        self::assertSame(['count' => 0, 'names' => []], $this->sut->getCommunicatedProceduresInfo($relation));
    }

    public function testInfoReturnsCountAndLimitedNames(): void
    {
        $relation = $this->createRelation('25');
        $this->repository->method('countProceduresOfOrgaWithExistingBplanId')->with($relation)->willReturn(42);
        $this->repository->expects(self::once())->method('getProceduresOfOrgaWithExistingBplanId')
            ->with($relation, 10)
            ->willReturn([
                $this->createAddonEntity('procedure-1', 'bplan-1', 'First'),
                $this->createAddonEntity('procedure-2', 'bplan-2', 'Second'),
            ]);

        $info = $this->sut->getCommunicatedProceduresInfo($relation);

        self::assertSame(['count' => 42, 'names' => ['First', 'Second']], $info);
    }

    public function testChangeOrganisationIdOfOrganisationsGivesAllOrganisationsTheNewIdAndReleasesProcedures(): void
    {
        $firstRelation = $this->createRelation('25');
        $secondRelation = $this->createRelation('25');
        $addonEntity = $this->createAddonEntity('procedure-1', 'bplan-1', 'First');
        $this->repository->method('findBy')->with(['meinBerlinOrganisationId' => '25'])
            ->willReturn([$firstRelation, $secondRelation]);
        $this->repository->method('getProceduresOfOrgaWithExistingBplanId')
            ->willReturnCallback(static fn ($relation) => $relation === $firstRelation ? [$addonEntity] : []);

        $result = $this->sut->changeOrganisationIdOfOrganisations('25', '29');

        self::assertSame(['organisations' => 2, 'procedures' => 1], $result);
        self::assertSame('29', $firstRelation->getMeinBerlinOrganisationId());
        self::assertSame('29', $secondRelation->getMeinBerlinOrganisationId());
        self::assertSame('', $addonEntity->getBplanId());
    }

    public function testChangeOrganisationIdOfOrganisationsResetsTheIdWhenTheNewIdIsEmpty(): void
    {
        $relation = $this->createRelation('25');
        $this->repository->method('findBy')->willReturn([$relation]);
        $this->repository->method('getProceduresOfOrgaWithExistingBplanId')->willReturn([]);

        $result = $this->sut->changeOrganisationIdOfOrganisations('25', '');

        self::assertSame(['organisations' => 1, 'procedures' => 0], $result);
        self::assertSame('', $relation->getMeinBerlinOrganisationId());
    }

    public function testChangeOrganisationIdOfOrganisationsChangesNothingWhenIdStaysTheSame(): void
    {
        $this->repository->expects(self::never())->method('findBy');

        $result = $this->sut->changeOrganisationIdOfOrganisations('25', '25');

        self::assertSame(['organisations' => 0, 'procedures' => 0], $result);
    }

    public function testInfoOfOrganisationIdSkipsNamesQueryWithoutCommunicatedProcedures(): void
    {
        $this->repository->method('countProceduresOfOrganisationIdWithExistingBplanId')->with('25')->willReturn(0);
        $this->repository->expects(self::never())->method('getProceduresOfOrganisationIdWithExistingBplanId');

        self::assertSame(['count' => 0, 'names' => []], $this->sut->getCommunicatedProceduresInfoOfOrganisationId('25'));
    }

    public function testInfoOfOrganisationIdReturnsCountAndLimitedNames(): void
    {
        $this->repository->method('countProceduresOfOrganisationIdWithExistingBplanId')->with('25')->willReturn(7);
        $this->repository->expects(self::once())->method('getProceduresOfOrganisationIdWithExistingBplanId')
            ->with('25', 10)
            ->willReturn([$this->createAddonEntity('procedure-1', 'bplan-1', 'First')]);

        $info = $this->sut->getCommunicatedProceduresInfoOfOrganisationId('25');

        self::assertSame(['count' => 7, 'names' => ['First']], $info);
    }

    private function createRelation(string $organisationId): MeinBerlinAddonOrgaRelation
    {
        $relation = new MeinBerlinAddonOrgaRelation();
        $relation->setMeinBerlinOrganisationId($organisationId);

        return $relation;
    }

    private function createAddonEntity(string $procedureId, string $bplanId, string $externalName): MeinBerlinAddonEntity
    {
        $procedure = $this->createMock(ProcedureInterface::class);
        $procedure->method('getId')->willReturn($procedureId);
        $procedure->method('getExternalName')->willReturn($externalName);
        $addonEntity = new MeinBerlinAddonEntity();
        $addonEntity->setProcedure($procedure);
        $addonEntity->setBplanId($bplanId);

        return $addonEntity;
    }
}
