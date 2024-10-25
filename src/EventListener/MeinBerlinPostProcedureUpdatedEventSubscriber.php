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

use DemosEurope\DemosplanAddon\Contracts\Events\PostProcedureUpdatedEventInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedurePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureSettingsPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevelantProcedurePhasePropertiesForMeinBerlinCommunication;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MeinBerlinPostProcedureUpdatedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
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
        // todo check if procedure is listed to be communicated at all
        // by checking for an organization-id as well as a publicly visible phase

        $changeSet = $postProcedureUpdatedEvent->getModifiedValues();

        // todo check if a create POST is necessary by checking for an existing dplan-id
        $create = false;

        if(!$create) {
            $this->updateProcedureData($changeSet);
        }


//        $oldProcedure = $postProcedureUpdatedEvent->getProcedureBeforeUpdate();
//        $oldPhase = $oldProcedure->getPublicParticipationPhaseObject();
//        $oldPhaseName = $oldPhase->getName();
//        $opPhase = $oldProcedure->getPublicParticipationPhase();
//
//        $newProcedure = $postProcedureUpdatedEvent->getProcedureAfterUpdate();
//        $newPhase = $newProcedure->getPublicParticipationPhaseObject();
//        $newPhaseName = $newPhase->getName();
//        $pPhase = $oldProcedure->getPhase();
//        $npPhase = $newProcedure->getPublicParticipationPhase();
//
//        $changeSet = $postProcedureUpdatedEvent->getModifiedValues();
//
//        $test = 5;
    }

    private function updateProcedureData(array $changeSet): void
    {
        if (RelevantProcedurePropertiesForMeinBerlinCommunication::
        hasRelevantPropertyBeenChanged($changeSet)
        ) {
            $fieldsToUpdate = $this->collectRelevantFields($changeSet);
        }
    }

    private function collectRelevantFields(array $changeSet): array
    {
        $importantChanges = [];
        $procedureChangeSet = RelevantProcedurePropertiesForMeinBerlinCommunication::getChangedProperties($changeSet);
        foreach ($procedureChangeSet as $name => $value) {
            if (RelevantProcedurePropertiesForMeinBerlinCommunication::SETTINGS->value === $name &&
                RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::hasRelevantPropertyBeenChanged($value)
            ) {
                $procedureSettingsChangeSet =
                    RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::mapToCommunicationNamesIfValuesExist(
                        RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::getChangedProperties($value)
                );
                $importantChanges = array_merge($importantChanges, $procedureSettingsChangeSet);

                continue;
            }
            if (RelevantProcedurePropertiesForMeinBerlinCommunication::PARTICIPATIONPHASE->value === $name &&
                RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::hasRelevantPropertyBeenChanged($value)
            ) {
                $procedurePublicParticipationPhaseChangeSet =
                    RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::mapToCommunicationNamesIfValuesExist(
                        RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::getChangedProperties($value)
                );
                $importantChanges = array_merge($importantChanges, $procedurePublicParticipationPhaseChangeSet);

                continue;
            }
            $importantChanges[RelevantProcedurePropertiesForMeinBerlinCommunication::getNameFromValue($name)] = $value;
        }

        return $importantChanges;
    }
}
