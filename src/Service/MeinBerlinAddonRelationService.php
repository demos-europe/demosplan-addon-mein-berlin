<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Service;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use Exception;

class MeinBerlinAddonRelationService
{
    /**
     * @return ProcedureInterface[]
     * @throws Exception
     */
    public function getVisibleProcedures(OrgaInterface $orga): array
    {
        $procedures = $orga->getProcedures();
        $hits = collect($procedures)->filter(
            static fn (ProcedureInterface $procedure): bool => in_array(
                $procedure->getPublicParticipationPhasePermissionset(),
                [ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_READ, ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_WRITE],
                true
            )
        )
        ->sortByDesc(static fn (ProcedureInterface $procedure): int => $procedure->getPublicParticipationEndDateTimestamp());

        return $hits->toArray();
    }

}
