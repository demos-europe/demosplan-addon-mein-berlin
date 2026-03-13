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
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
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
    private MessageBagInterface|MockObject|null $messageBag = null;
    private MeinBerlinPostProcedureUpdatedEventSubscriber|null $sut = null;

    protected function setUp(): void
    {
        $this->communicationHelper = $this->createMock(MeinBerlinCommunicationHelper::class);
        $this->createProcedureService = $this->createMock(MeinBerlinCreateProcedureService::class);
        $this->updateProcedureService = $this->createMock(MeinBerlinUpdateProcedureService::class);
        $this->messageBag = $this->createMock(MessageBagInterface::class);

        // Create a partial mock for the SUT
        $this->sut = $this->getMockBuilder(MeinBerlinPostProcedureUpdatedEventSubscriber::class)
            ->setConstructorArgs([(new NullLogger()), $this->communicationHelper, $this->createProcedureService, $this->updateProcedureService, $this->messageBag])
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

    public function testOnProcedureUpdateWithNoDistrictAndNoBplanId(): void
    {
        $event = $this->createMock(PostProcedureUpdatedEventInterface::class);
        $procedure = $this->createMock(ProcedureInterface::class);

        $event->method('getProcedureAfterUpdate')->willReturn($procedure);
        $this->communicationHelper->method('hasOrganisationIdSet')->willReturn(true);
        $this->communicationHelper->method('checkProcedurePublicPhasePermissionsetNotHidden')->willReturn(true);
        $this->communicationHelper->method('hasDistrictSet')->willReturn(false);
        $this->communicationHelper->method('hasBplanIdSet')->willReturn(false);

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

        // Mock isInterfaceActivated to return true
        $addonEntity->method('getIsInterfaceActivated')->willReturn(true);

        $event->method('getProcedureAfterUpdate')->willReturn($procedure);
        $this->communicationHelper->method('hasOrganisationIdSet')->willReturn(true);
        $this->communicationHelper->method('getCorrespondingAddonEntity')->willReturn($addonEntity);
        $this->communicationHelper->method('checkProcedurePublicPhasePermissionsetNotHidden')
            ->willReturn(true);
        $this->communicationHelper->method('hasDistrictSet')->willReturn(true);
        $this->communicationHelper->method('hasCoordinateSet')->willReturn(true);
        $this->communicationHelper->method('hasBplanIdSet')->willReturn(false);
        $this->communicationHelper->method('getCorrespondingOrgaRelation')->willReturn($orgaRelation);

        $this->createProcedureService->expects(self::once())
            ->method('createMeinBerlinProcedure')
            ->with($procedure, $addonEntity, $orgaRelation);

        $this->sut->onProcedureUpdate($event);
    }

    public function testOnProcedureUpdateSkipsCreateWhenNoCoordinateSet(): void
    {
        $event = $this->createMock(PostProcedureUpdatedEventInterface::class);
        $procedure = $this->createMock(ProcedureInterface::class);
        $addonEntity = $this->createMock(MeinBerlinAddonEntity::class);

        $addonEntity->method('getIsInterfaceActivated')->willReturn(true);

        $event->method('getProcedureAfterUpdate')->willReturn($procedure);
        $this->communicationHelper->method('hasOrganisationIdSet')->willReturn(true);
        $this->communicationHelper->method('getCorrespondingAddonEntity')->willReturn($addonEntity);
        $this->communicationHelper->method('checkProcedurePublicPhasePermissionsetNotHidden')->willReturn(true);
        $this->communicationHelper->method('hasDistrictSet')->willReturn(true);
        $this->communicationHelper->method('hasCoordinateSet')->willReturn(false);
        $this->communicationHelper->method('hasBplanIdSet')->willReturn(false);

        $this->createProcedureService->expects(self::never())->method('createMeinBerlinProcedure');
        $this->messageBag->expects(self::once())->method('add')->with('error', 'mein.berlin.error.create.empty.coordinate');

        $this->sut->onProcedureUpdate($event);
    }

    public function testOnProcedureUpdateTriggersMeinBerlinProcedureUpdate(): void
    {
        $event = $this->createMock(PostProcedureUpdatedEventInterface::class);
        $procedure = $this->createMock(ProcedureInterface::class);
        $addonEntity = $this->createMock(MeinBerlinAddonEntity::class);
        $orgaRelation = $this->createMock(MeinBerlinAddonOrgaRelation::class);
        $changeSet = ['someField' => 'newValue'];

        // Mock isInterfaceActivated to return true
        $addonEntity->method('getIsInterfaceActivated')->willReturn(true);

        $event->method('getProcedureAfterUpdate')->willReturn($procedure);
        $event->method('getModifiedValues')->willReturn($changeSet);
        $procedure->method('getId')->willReturn('someId');
        $this->communicationHelper->method('hasOrganisationIdSet')->willReturn(true);
        $this->communicationHelper->method('getCorrespondingAddonEntity')->willReturn($addonEntity);
        $this->communicationHelper->method('checkProcedurePublicPhasePermissionsetNotHidden')
            ->willReturn(true);
        $this->communicationHelper->method('hasDistrictSet')->willReturn(true);
        $this->communicationHelper->method('hasBplanIdSet')->willReturn(true);
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
