<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Service;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use Illuminate\Support\Collection;

class MeinBerlinAddonRelationSerivce
{
    public function __construct(
        private readonly MeinBerlinAddonOrgaRelation $orgaRelation,
    )
    {
    }

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
        } catch (\Exception $e) {
            throw $e;
        }
    }

}