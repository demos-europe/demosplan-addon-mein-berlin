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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webmozart\Assert\Assert;

class MeinBerlinPostProcedureUpdatedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
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
        // check if procedure is listed to be communicated at all and figure out what kind POST || PATCH
        // by checking for an organization-id as well as a publicly visible phase and dplan-id
        $newProcedure = $postProcedureUpdatedEvent->getProcedureAfterUpdate();
        if (false === $this->communicationHelper->hasOrganisationIdSet($newProcedure)) {
            // for this procedure is no MeinBerlin organisationId set (dplan name: procedureShortName)
            // - it will not be published
            return;
        }
        $isPublishedVal = $this->communicationHelper->checkProcedurePublicPhasePermissionsetNotHidden($newProcedure);
        $hasProcedureShortNameSet = $this->communicationHelper->hasProcedureShortNameSet($newProcedure);
        $dplanIdIsPresent = $this->communicationHelper->hasDplanIdSet($newProcedure);
        $hasPictogram = $newProcedure->getPictogram() !== null && $newProcedure->getPictogram() !== '';
        if ($isPublishedVal && $hasProcedureShortNameSet && $hasPictogram && !$dplanIdIsPresent) {
            // create new Procedure entry at MeinBerlin if procedure is publicly visible, has an procedureShortName set
            // but was not communicated to MeinBerlin previously (dplanIdIsPresent = false)
            $correspondingAddonEntity = $this->communicationHelper->getCorrespondingAddonEntity($newProcedure);
            $correspondingAddonOrgaRelation = $this->communicationHelper->getCorrespondingOrgaRelation($newProcedure);
            // those can not be null as indirectly checked by methods beforehand
            Assert::notNull($correspondingAddonOrgaRelation);
            Assert::notNull($correspondingAddonEntity);

            $this->createProcedureService->createMeinBerlinProcedure(
                $newProcedure,
                $correspondingAddonEntity,
                $correspondingAddonOrgaRelation
            );

            return;
        }
        if ($dplanIdIsPresent) {
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
            $this->updateProcedureService->updateMeinBerlinProcedureEntry(
                $changeSet,
                $isPublishedVal,
                $correspondingAddonOrgaRelation->getMeinBerlinOrganisationId(),
                $correspondingAddonEntity->getDplanId(),
                $newProcedure->getId()
            );

            return;
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
