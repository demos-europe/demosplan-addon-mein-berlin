<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

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

    public function checkProcedurePublicPhasePermissionsetNotHidden(ProcedureInterface $procedure): bool
    {
        $permissionSet = $procedure->getPublicParticipationPhasePermissionset();

        return 'hidden' !== $permissionSet;
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

    public function hasDistrictSet(ProcedureInterface $procedure): bool
    {
        $addonEntity = $this->addonEntityRepository->getByProceduerId($procedure->getId());

        return null !== $addonEntity && '' !== $addonEntity->getDistrict();
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
