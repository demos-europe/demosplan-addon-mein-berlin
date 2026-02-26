<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\EventListener;


use DemosEurope\DemosplanAddon\Contracts\Events\PostProcedureUpdatedEventInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinCommunicationHelper;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinCreateProcedureService;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinUpdateProcedureService;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webmozart\Assert\Assert;

class MeinBerlinPostProcedureUpdatedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MeinBerlinCommunicationHelper $communicationHelper,
        private readonly MeinBerlinCreateProcedureService $createProcedureService,
        private readonly MeinBerlinUpdateProcedureService $updateProcedureService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostProcedureUpdatedEventInterface::class => 'onProcedureUpdate',
        ];
    }

    /**
     * Those Exceptions can not really occur as they are handled beforehand when called
     * after the flush with updates on the procedure happened anyhow - messagebag/logs have been filled.
     * @throws MeinBerlinCommunicationException
     * @throws InvalidArgumentException
     */
    public function onProcedureUpdate(PostProcedureUpdatedEventInterface $postProcedureUpdatedEvent): void
    {
        $this->logger->info('MeinBerlinPostProcedureUpdatedEventSubscriber::onProcedureUpdate');
        // check if procedure is listed to be communicated at all and figure out what kind POST || PATCH
        // by checking for an organization-id as well as a publicly visible phase and dplan-id as well as a pictogram
        $newProcedure = $postProcedureUpdatedEvent->getProcedureAfterUpdate();
        $isInterfaceActivated =
            $this->communicationHelper->getCorrespondingAddonEntity($newProcedure)?->getIsInterfaceActivated();

        if (false === $this->communicationHelper->hasOrganisationIdSet($newProcedure)
            || false === $isInterfaceActivated) {
            $this->logger->info('MeinBerlinPostProcedureUpdatedEventSubscriber::onProcedureUpdate - skipping: no organisationId set or interface not activated', [
                'hasOrganisationId' => $this->communicationHelper->hasOrganisationIdSet($newProcedure),
                'isInterfaceActivated' => $isInterfaceActivated,
            ]);

            return;
        }
        $isPublishedVal = $this->communicationHelper->checkProcedurePublicPhasePermissionsetNotHidden($newProcedure);
        $hasDistrictSet = $this->communicationHelper->hasDistrictSet($newProcedure);
        $hasCoordinateSet = $this->communicationHelper->hasCoordinateSet($newProcedure);
        $bplanIdIsPresent = $this->communicationHelper->hasBplanIdSet($newProcedure);

        if ($isPublishedVal && $hasDistrictSet && !$bplanIdIsPresent) {
            if (!$hasCoordinateSet) {
                $this->logger->warning('MeinBerlinPostProcedureUpdatedEventSubscriber::onProcedureUpdate - skipping create: no coordinate (point) set on procedure', [
                    'procedureId' => $newProcedure->getId(),
                ]);

                return;
            }
            $this->logger->info('MeinBerlinPostProcedureUpdatedEventSubscriber::onProcedureUpdate - create new procedure entry at MeinBerlin');
            // create new Procedure entry at MeinBerlin if procedure is publicly visible, has a district set
            // and has a pictogram set, but was not communicated to MeinBerlin previously (bplanIdIsPresent = false)
            $correspondingAddonEntity = $this->communicationHelper->getCorrespondingAddonEntity($newProcedure);
            $correspondingAddonOrgaRelation = $this->communicationHelper->getCorrespondingOrgaRelation($newProcedure);
            // those can not be null as indirectly checked by methods beforehand
            Assert::notNull($correspondingAddonOrgaRelation);
            Assert::notNull($correspondingAddonEntity);

            $this->logger->info('MeinBerlinPostProcedureUpdatedEventSubscriber::onProcedureUpdate - createMeinBerlinProcedure request');
            $this->createProcedureService->createMeinBerlinProcedure(
                $newProcedure,
                $correspondingAddonEntity,
                $correspondingAddonOrgaRelation
            );

            return;
        }
        if ($bplanIdIsPresent) {
            $this->logger->info('MeinBerlinPostProcedureUpdatedEventSubscriber::onProcedureUpdate - update existing procedure entry at MeinBerlin');
            $correspondingAddonEntity = $this->communicationHelper->getCorrespondingAddonEntity($newProcedure);
            $correspondingAddonOrgaRelation = $this->communicationHelper->getCorrespondingOrgaRelation($newProcedure);
            // those can not be null as indirectly checked by metods beforehand
            Assert::notNull($correspondingAddonOrgaRelation);
            Assert::notNull($correspondingAddonEntity);
            // update all fields for previously at MeinBerlin created Procedures
            $changeSet = $postProcedureUpdatedEvent->getModifiedValues();
            // check if publicPhase permission set visibility changed from hidden to something else or vice versa
            // if it did - include it as is_draft boolean in PATCH request
            $isPublishedVal = $this->isPublishedIfNeedsToBeIncludedOnUpdate($postProcedureUpdatedEvent);
            $this->logger->info('MeinBerlinPostProcedureUpdatedEventSubscriber::onProcedureUpdate - updateMeinBerlinProcedureEntry request');
            $this->updateProcedureService->updateMeinBerlinProcedureEntry(
                $changeSet,
                $isPublishedVal,
                $correspondingAddonOrgaRelation->getMeinBerlinOrganisationId(),
                $correspondingAddonEntity->getBplanId(),
                $newProcedure->getId()
            );

        }
    }

     protected function isPublishedIfNeedsToBeIncludedOnUpdate(PostProcedureUpdatedEventInterface $event): ?bool
    {
        $oldPermissionSet = $event->getProcedureBeforeUpdate()->getPublicParticipationPhasePermissionset();
        $newPermissionSet = $event->getProcedureAfterUpdate()->getPublicParticipationPhasePermissionset();

        if ($oldPermissionSet !== $newPermissionSet) {
            $oldIsPublishedVal = 'hidden' !== $oldPermissionSet;
            $newIsPublishedVal = 'hidden' !== $newPermissionSet;

            if ($oldIsPublishedVal !== $newIsPublishedVal) {
                return $newIsPublishedVal;
            }
        }

        return null;
    }
}
