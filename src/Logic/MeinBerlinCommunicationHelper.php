<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonEntityRepository;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;

class MeinBerlinCommunicationHelper
{
    public function __construct(
        private readonly MeinBerlinAddonOrgaRelationRepository $orgaRelationRepository,
        private readonly MeinBerlinAddonEntityRepository $addonEntityRepository,
    ){

    }

    public function checkProcedurePublicPhasePermissionsetIsHidden(ProcedureInterface $procedure): bool
    {
        $permissionSet = $procedure->getPublicParticipationPhasePermissionset();

        return 'hidden' === $permissionSet ? false : true;
    }

    public function hasOrganisationIdSet(ProcedureInterface $procedure): bool
    {
        $orga = $procedure->getOrga();
        if (null !== $orga) {
            $orgaRelation = $this->orgaRelationRepository->getByOrgaId($orga->getId());

            return null !== $orgaRelation && '' !== $orgaRelation->getMeinBerlinOrganisationId();
        }

        return false;
    }

    public function hasDplanIdSet(ProcedureInterface $procedure): bool
    {
        $addonEntity = $this->addonEntityRepository->getByProceduerId($procedure->getId());

        return null !== $addonEntity && '' !== $addonEntity->getDplanId();
    }

    public function hasProcedureShortNameSet(ProcedureInterface $procedure): bool
    {
        $addonEntity = $this->addonEntityRepository->getByProceduerId($procedure->getId());

        return null !== $addonEntity && '' !== $addonEntity->getProcedureShortName();
    }

    public function getCorrespondingAddonEntity(ProcedureInterface $procedure): ?MeinBerlinAddonEntity
    {
        return $this->addonEntityRepository->getByProceduerId($procedure->getId());
    }

    public function getCorrespondingOrgaRelation(ProcedureInterface $procedure): ?MeinBerlinAddonOrgaRelation
    {
        $orga = $procedure->getOrga();
        if (null === $orga) {
            return null;
        }

        return $this->orgaRelationRepository->getByOrgaId($orga->getId());
    }
}
