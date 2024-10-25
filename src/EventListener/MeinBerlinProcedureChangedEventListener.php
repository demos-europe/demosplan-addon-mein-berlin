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
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseInterface;
use DemosEurope\DemosplanAddon\Contracts\Repositories\ProcedurePhaseRepositoryInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedurePropertiesForMeinBerlinCommunication;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;
use Exception;
use Psr\Log\LoggerInterface;

#[AsDoctrineListener(Events::onFlush)]
class MeinBerlinProcedureChangedEventListener
{
    private UnitOfWork $unitOfWork;
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ProcedurePhaseRepositoryInterface $procedurePhaseRepository
    ) {

    }

    /**
     * @throws Exception
     */
    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
//        $this->unitOfWork = $eventArgs->getObjectManager()->getUnitOfWork();



        // todo add new table
    }

    private function getRelevantProcedures(): array
    {
        $relevantProcedures = [];
        $proceduresRelevantBecauseOfPhaseChange = $this->getRelevantProcedureForPhaseChanges();

        // todo merge with other procedures qualified by other relevant reasons

        // todo check if an organisation-id is present - otherwise its uninteresting

        return $relevantProcedures;
    }

    /**
     * @return ProcedureInterface[]
     */
    private function getRelevantProceduresForProcedureChanges():array
    {
        $allUpdatedProcedures = $this->getUpdated(ProcedureInterface::class);

        return array_filter(
            $allUpdatedProcedures,
            function (ProcedureInterface $procedure): bool {
                $entityChangeSet = $this->unitOfWork->getEntityChangeSet($procedure);

                return RelevantProcedurePropertiesForMeinBerlinCommunication::hasRelevantPropertyBeenChanged($entityChangeSet);
            }
        );
    }

    /**
     * @return array<int, array>
     */
    private function getRelevantProcedureForPhaseChanges(): array
    {
        /** @var ProcedurePhaseInterface[] $relevantChangedProcedurePhases */
        $relevantChangedProcedurePhases = array_filter(
            $this->getUpdated(ProcedurePhaseInterface::class),
            function (ProcedurePhaseInterface $procedurePhase): bool {
                $entityChangeSet = $this->unitOfWork->getEntityChangeSet($procedurePhase);

                return RelevantProcedurePropertiesForMeinBerlinCommunication::hasRelevantPropertyBeenChanged($entityChangeSet);
            }
        );

        return  $this->getProceduresForPublicPhaseChanges($relevantChangedProcedurePhases);
    }

    /**
     * @param array<int, ProcedurePhaseInterface> $procedurePhaseChanges
     * @return array<int, array>
     */
    private function getProceduresForPublicPhaseChanges(array $procedurePhaseChanges): array
    {
        $allProceduresByPhase = [];
        foreach ($procedurePhaseChanges as $procedurePhaseChange) {
           // todo - check assumption: we are only interested in public phase changes
           $mayRelevantProcedure = $this->procedurePhaseRepository
               ->getProcedureByPublicParticipationPhaseId($procedurePhaseChange->getId());
            if ($mayRelevantProcedure instanceof ProcedureInterface) {
                $allProceduresByPhase[] = $mayRelevantProcedure;
            }
        }

        return $allProceduresByPhase;
    }

    /**
     * @return array<int, object>
     */
    private function getUpdated(string $type): array
    {
        return array_filter(
            $this->unitOfWork->getScheduledEntityUpdates(),
            static fn (object $entity): bool => $entity instanceof $type
        );
    }


}
