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
    public function __construct(private readonly MeinBerlinRssPhaseFilter $phaseFilter)
    {
    }

    /**
     * @param list<string> $phaseNames normalized names of the public phases (see {@link MeinBerlinRssPhaseFilter::parse()})
     *                                 to restrict the result to, an empty list returns all visible procedures
     *
     * @return ProcedureInterface[]
     * @throws Exception
     */
    public function getVisibleProcedures(OrgaInterface $orga, array $phaseNames = []): array
    {
        $procedures = $orga->getProcedures();
        $hits = collect($procedures)->filter(
            static fn (ProcedureInterface $procedure): bool => in_array(
                $procedure->getPublicParticipationPhasePermissionset(),
                [ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_READ, ProcedureInterface::PROCEDURE_PHASE_PERMISSIONSET_WRITE],
                true
            )
        );
        // only ever narrows down the procedures that are visible anyhow
        if ([] !== $phaseNames) {
            $hits = $hits->filter(
                fn (ProcedureInterface $procedure): bool => $this->phaseFilter->matches(
                    $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getName(),
                    $phaseNames
                )
            );
        }

        return $hits
            ->sortByDesc(static fn (ProcedureInterface $procedure): int => $procedure->getPublicParticipationEndDateTimestamp())
            ->toArray();
    }
}
