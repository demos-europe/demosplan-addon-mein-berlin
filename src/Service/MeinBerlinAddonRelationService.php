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
     * @param array<int, string> $phaseKeys
     * @return ProcedureInterface[]
     * @throws Exception
     */
    public function getVisibleProcedures(array $phaseKeys, OrgaInterface $orga): array
    {
        $procedures = $orga->getProcedures();
        $phaseKeysCollection = collect($phaseKeys);
        $hits = collect($procedures)->filter(
            static fn (ProcedureInterface $procedure): bool => $phaseKeysCollection->contains($procedure->getPublicParticipationPhase())
        )
        ->sortByDesc(static fn (ProcedureInterface $procedure): int => $procedure->getPublicParticipationEndDateTimestamp());

        return $hits->toArray();
    }

}
