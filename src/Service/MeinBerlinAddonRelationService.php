<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Service;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use Exception;
use Illuminate\Support\Collection;

class MeinBerlinAddonRelationService
{
    public function __construct(
        private readonly MeinBerlinAddonOrgaRelation $orgaRelation,
    )
    {
    }

    /**
     * @param array $phaseKeys
     * @return array
     * @throws Exception
     */
    public function getProceduresWithEndedParticipation(array $phaseKeys): array
    {
        try {
            $currentDate = new \DateTime();
            $procedures = $this->orgaRelation->getOrga()->getProcedures();
            $phaseKeys = new Collection($phaseKeys);
            $hits = collect($procedures)->filter(
                static fn (ProcedureInterface $procedure): bool => $procedure->getPublicParticipationEndDate() < $currentDate
                    && $phaseKeys->contains($procedure->getPublicParticipationPhase())
            );

            return $hits->toArray();
        } catch (Exception $e) {
            throw $e;
        }
    }

}
