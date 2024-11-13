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

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use Exception;
use Illuminate\Support\Collection;

class MeinBerlinAddonRelationService
{
    /**
     * @param array<int, string> $phaseKeys
     * @return array
     * @throws Exception
     */
    public function getProceduresWithEndedParticipation(array $phaseKeys, OrgaInterface $orga): array
    {
        try {
            $currentDate = new \DateTime();
            $procedures = $orga->getProcedures();
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
