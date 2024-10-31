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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\PostProcedureUpdatedEventInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinCreateProcedureService;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinUpdateProcedureService;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonEntityRepository;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MeinBerlinPostProcedureUpdatedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MeinBerlinAddonEntityRepository $addonEntityRepository,
        private readonly MeinBerlinAddonOrgaRelationRepository $orgaRelationRepository,
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

    public function onProcedureUpdate(PostProcedureUpdatedEventInterface $postProcedureUpdatedEvent): void
    {
        // check if procedure is listed to be communicated at all and figure out what kind POST || PATCH
        // by checking for an organization-id as well as a publicly visible phase and dplan-id
        $newProcedure = $postProcedureUpdatedEvent->getProcedureAfterUpdate();
        if (false === $this->hasOrganisationIdSet($newProcedure)) {
            // for this procedure is no MeinBerlin organisationId set (dplan name: procedureShortName)
            // - it will not be published
            return;
        }

        $correspondingAddonEntity = $this->addonEntityRepository->getByProceduerId($newProcedure->getId());
        $correspondingAddonOrgaRelation = $this->orgaRelationRepository->getByOrgaId($newProcedure->getOrga()?->getId());

        $isPublishedVal = $this->checkProcedurePublicPhasePermissionsetIsHidden($newProcedure);
        $dplanIdIsPresent = $this->hasDplanIdSet($correspondingAddonEntity);
        if ($isPublishedVal && !$dplanIdIsPresent) {
            // create new Procedure entry at MeinBerlin if procedure is publicly visible, has an procedureShortName set
            // but was not communicated to MeinBerlin previously (dplanIdIsPresent = false)
            $this->createProcedureService->createMeinBerlinProcedure(
                $newProcedure,
                $correspondingAddonEntity,
                $correspondingAddonOrgaRelation
            );
        }
        if ($dplanIdIsPresent) {
            // update all fields for previously at MeinBerlin created Procedures
            $changeSet = $postProcedureUpdatedEvent->getModifiedValues();
            // check if publicPhase permission set visibility changed from hidden to something else or vice versa
            // if it did - include it as is_published boolean in PATCH request
            $isPublishedVal = $this->isPublishedIfNeedsToBeIncludedOnUpdate($postProcedureUpdatedEvent);
            $this->updateProcedureService->updateMeinBerlinProcedureEntry(
                $changeSet,
                $isPublishedVal,
                $correspondingAddonOrgaRelation->getMeinBerlinOrganisationId(),
                $correspondingAddonEntity->getDplanId()
            );
        }
    }

     private function isPublishedIfNeedsToBeIncludedOnUpdate(PostProcedureUpdatedEventInterface $event): ?bool
    {
        $oldPermissionSet = $event->getProcedureBeforeUpdate()->getPublicParticipationPhasePermissionset();
        $newPermissionSet = $event->getProcedureAfterUpdate()->getPublicParticipationPhasePermissionset();

        if ($oldPermissionSet !== $newPermissionSet) {
            $oldIsPublishedVal = 'hidden' === $oldPermissionSet ? false : true;
            $newIsPublishedVal = 'hidden' === $newPermissionSet ? false : true;

            if ($oldIsPublishedVal !== $newIsPublishedVal) {
                return $newIsPublishedVal;
            }
        }

        return null;
    }

    private function checkProcedurePublicPhasePermissionsetIsHidden(ProcedureInterface $newProcedure): bool
    {
        $newPermissionSet = $newProcedure->getPublicParticipationPhasePermissionset();

        return 'hidden' === $newPermissionSet ? false : true;
    }

    private function hasOrganisationIdSet(ProcedureInterface $procedure): bool
    {
        $orga = $procedure->getOrga();
        if (null !== $orga) {
            $orgaRelation = $this->orgaRelationRepository->getByOrgaId($orga->getId());

            return null !== $orgaRelation && '' !== $orgaRelation->getMeinBerlinOrganisationId();
        }

        return false;
    }

    private function hasDplanIdSet(?MeinBerlinAddonEntity $addonEntity): bool
    {
        return null !== $addonEntity && '' !== $addonEntity->getDplanId();
    }

    private function hasProcedureShortNameSet(?MeinBerlinAddonEntity $addonEntity): bool
    {
        return null !== $addonEntity && '' !== $addonEntity->getProcedureShortName();
    }
}
