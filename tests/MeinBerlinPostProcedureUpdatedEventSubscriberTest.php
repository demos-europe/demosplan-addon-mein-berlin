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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\PostProcedureUpdatedEventInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\EventListener\MeinBerlinPostProcedureUpdatedEventSubscriber;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinCommunicationHelper;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinCreateProcedureService;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinUpdateProcedureService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class MeinBerlinPostProcedureUpdatedEventSubscriberTest extends TestCase
{
    private MeinBerlinCommunicationHelper|MockObject|null $communicationHelper = null;
    private MeinBerlinCreateProcedureService|MockObject|null $createProcedureService = null;
    private MeinBerlinUpdateProcedureService|MockObject|null $updateProcedureService = null;
    private MeinBerlinPostProcedureUpdatedEventSubscriber|null $sut = null;

    protected function setUp(): void
    {
        $this->communicationHelper = $this->createMock(MeinBerlinCommunicationHelper::class);
        $this->createProcedureService = $this->createMock(MeinBerlinCreateProcedureService::class);
        $this->updateProcedureService = $this->createMock(MeinBerlinUpdateProcedureService::class);

        // Create a partial mock for the SUT
        $this->sut = $this->getMockBuilder(MeinBerlinPostProcedureUpdatedEventSubscriber::class)
            ->setConstructorArgs([(new NullLogger()),$this->communicationHelper, $this->createProcedureService, $this->updateProcedureService])
            ->onlyMethods(['isPublishedIfNeedsToBeIncludedOnUpdate'])
            ->getMock();

        // Mock the isPublishedIfNeedsToBeIncludedOnUpdate method
        $this->sut->method('isPublishedIfNeedsToBeIncludedOnUpdate')->willReturn(true);
    }

    public function testGetSubscribedEvents(): void
    {
        self::assertSame(
            [PostProcedureUpdatedEventInterface::class => 'onProcedureUpdate'],
            MeinBerlinPostProcedureUpdatedEventSubscriber::getSubscribedEvents()
        );
    }

    public function testOnProcedureUpdateWithNoOrganisationId(): void
    {
        $event = $this->createMock(PostProcedureUpdatedEventInterface::class);
        $procedure = $this->createMock(ProcedureInterface::class);

        $event->method('getProcedureAfterUpdate')->willReturn($procedure);
        $this->communicationHelper->method('hasOrganisationIdSet')->willReturn(false);

        $this->sut->onProcedureUpdate($event);

        // Assert that no further methods are called
        $this->communicationHelper->expects(self::never())
            ->method('checkProcedurePublicPhasePermissionsetNotHidden');
    }

    public function testOnProcedureUpdateWithNoProcedureShortNameAndNoDplanId(): void
    {
        $event = $this->createMock(PostProcedureUpdatedEventInterface::class);
        $procedure = $this->createMock(ProcedureInterface::class);

        $event->method('getProcedureAfterUpdate')->willReturn($procedure);
        $this->communicationHelper->method('hasOrganisationIdSet')->willReturn(true);
        $this->communicationHelper->method('checkProcedurePublicPhasePermissionsetNotHidden')->willReturn(true);
        $this->communicationHelper->method('hasProcedureShortNameSet')->willReturn(false);
        $this->communicationHelper->method('hasDplanIdSet')->willReturn(false);

        $this->createProcedureService->expects(self::never())->method('createMeinBerlinProcedure');
        $this->updateProcedureService->expects(self::never())->method('updateMeinBerlinProcedureEntry');

        $this->sut->onProcedureUpdate($event);
    }
    public function testOnProcedureUpdateTriggersMeinBerlinProcedureCreate(): void
    {
        $event = $this->createMock(PostProcedureUpdatedEventInterface::class);
        $procedure = $this->createMock(ProcedureInterface::class);
        $addonEntity = $this->createMock(MeinBerlinAddonEntity::class);
        $orgaRelation = $this->createMock(MeinBerlinAddonOrgaRelation::class);

        $event->method('getProcedureAfterUpdate')->willReturn($procedure);
        $this->communicationHelper->method('hasOrganisationIdSet')->willReturn(true);
        $this->communicationHelper->method('checkProcedurePublicPhasePermissionsetNotHidden')
            ->willReturn(true);
        $this->communicationHelper->method('hasProcedureShortNameSet')->willReturn(true);
        $this->communicationHelper->method('hasDplanIdSet')->willReturn(false);
        $this->communicationHelper->method('getCorrespondingAddonEntity')->willReturn($addonEntity);
        $this->communicationHelper->method('getCorrespondingOrgaRelation')->willReturn($orgaRelation);

        $this->communicationHelper->expects(self::once())
            ->method('getCorrespondingAddonEntity')->with($procedure);
        $this->communicationHelper->expects(self::once())
            ->method('getCorrespondingOrgaRelation')->with($procedure);

        $this->createProcedureService->expects(self::once())
            ->method('createMeinBerlinProcedure')
            ->with($procedure, $addonEntity, $orgaRelation);

        $this->sut->onProcedureUpdate($event);
    }

    public function testOnProcedureUpdateTriggersMeinBerlinProcedureUpdate(): void
    {
        $event = $this->createMock(PostProcedureUpdatedEventInterface::class);
        $procedure = $this->createMock(ProcedureInterface::class);
        $addonEntity = $this->createMock(MeinBerlinAddonEntity::class);
        $orgaRelation = $this->createMock(MeinBerlinAddonOrgaRelation::class);
        $changeSet = ['someField' => 'newValue'];

        $event->method('getProcedureAfterUpdate')->willReturn($procedure);
        $event->method('getModifiedValues')->willReturn($changeSet);
        $procedure->method('getId')->willReturn('someId');
        $this->communicationHelper->method('hasOrganisationIdSet')->willReturn(true);
        $this->communicationHelper->method('checkProcedurePublicPhasePermissionsetNotHidden')
            ->willReturn(true);
        $this->communicationHelper->method('hasDistrictSet')->willReturn(true);
        $this->communicationHelper->method('hasBplanIdSet')->willReturn(true);
        $this->communicationHelper->method('getCorrespondingAddonEntity')->willReturn($addonEntity);
        $this->communicationHelper->method('getCorrespondingOrgaRelation')->willReturn($orgaRelation);

        $this->updateProcedureService->expects(self::once())
            ->method('updateMeinBerlinProcedureEntry')
            ->with(
                $changeSet,
                true,
                $orgaRelation->getMeinBerlinOrganisationId(),
                $addonEntity->getBplanId(),
                $procedure->getId()
            );

        $this->sut->onProcedureUpdate($event);
    }
}
