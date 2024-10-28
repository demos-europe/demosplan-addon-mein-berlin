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
use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedurePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureSettingsPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevelantProcedurePhasePropertiesForMeinBerlinCommunication;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use function is_array;
use function array_key_exists;
use function array_merge;
use function substr;
use function is_file;

class MeinBerlinPostProcedureUpdatedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly FileServiceInterface $fileService,
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
            $this->logger->info(
                'demosplan-mein-berlin-addon discovered a procedure update with fields that
                might be relevant to communicate. - start collecting relevant changes',
                $changeSet
            );
            $fieldsToUpdate = $this->collectRelevantFields($changeSet);
        }
    }

    private function collectRelevantFields(array $changeSet): array
    {
        $importantChanges = [];
        $relevantProcedureChanges = [];
        $procedureChangeSet = RelevantProcedurePropertiesForMeinBerlinCommunication::getChangedProperties($changeSet);
        $procedureChangeSet = $this->getOnlyNewValuesForChangeSet($procedureChangeSet);
        foreach ($procedureChangeSet as $name => $value) {
            if (RelevantProcedurePropertiesForMeinBerlinCommunication::SETTINGS->value === $name &&
                RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::hasRelevantPropertyBeenChanged($value)
            ) {
                // get relevant ProcedureSettings Changes:
                $relevantProcedureSettingsChanges =
                    RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::getChangedProperties($value);
                $this->logger->info(
                    'demosplan-mein-berlin-addon discovered relevant ProcedureSettings changes:',
                    $relevantProcedureSettingsChanges
                );
                $relevantProcedureSettingsChanges = $this->checkForPictogramAndReplaceLinkWithFileContent(
                    $relevantProcedureSettingsChanges
                );
                // todo handle special settings Würste

                // map our property names to their requested names
                $mappedProcedureSettingsChanges =
                    RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::mapToCommunicationNamesIfValuesExist(
                        $relevantProcedureSettingsChanges
                    );

                // merge prepared changes
                $importantChanges = array_merge($importantChanges, $mappedProcedureSettingsChanges);

                $mappedProcedureSettingsChanges = $this->truncateBase64FileStringBeforeLogging(
                    $mappedProcedureSettingsChanges
                );
                $this->logger->info(
                    'demosplan-mein-berlin-addon mapped relevant ProcedureSettings changes like:',
                    $mappedProcedureSettingsChanges
                );

                continue;
            }
            if (RelevantProcedurePropertiesForMeinBerlinCommunication::PARTICIPATIONPHASE->value === $name &&
                RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::hasRelevantPropertyBeenChanged($value)
            ) {
                // get relevant public ProcedurePhase Changes:
                $relevantProcedurePublicPhaseChanges =
                    RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::getChangedProperties($value);
                // todo handle special publicPhase Würste

                // map our property names to their requested names
                $mappedProcedurePublicPhaseChanges =
                    RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::mapToCommunicationNamesIfValuesExist(
                        $relevantProcedurePublicPhaseChanges
                    );

                $importantChanges = array_merge($importantChanges, $mappedProcedurePublicPhaseChanges);

                continue;
            }
            $relevantProcedureChanges[] = $value;
            $importantChanges[RelevantProcedurePropertiesForMeinBerlinCommunication::getNameFromValue($name)] = $value;
        }
        $this->logger->info(
            'demosplan-mein-berlin-addon mapped the following changes to communicate',
            $importantChanges
        );

        return $importantChanges;
    }

    /** The changeSet obtained from the { @link PostProcedureUpdatedEventInterface::getModifiedValues() }
     * follows the format:
     * $array = [
     *      'property' => ['old' => 'oldVal', 'new' => 'newVal']],
     *      'relation' => [
     *                      'property' => ['old' => 'oldVal', 'new' => 'newVal'],
     *                      'property2' => ['old' => 'oldVal', 'new' => 'newVal'],
     *                    ],
     *      ]
     *      'relation' => [
     *                      'property' => ['old' => 'oldVal', 'new' => 'newVal'],
     *                      'relation' => [
     *                              'property' => ['old' => 'oldVal', 'new' => 'newVal'],
     *                              'property2' => ['old' => 'oldVal', 'new' => 'newVal'],
     *                              ]
     *                    ],
     *      ]
     *      'property' => ['old' => 'oldVal', 'new' => 'newVal'],
     *      ...
     * ];
     * this method simplifies the structure since only the new value is of interest here.
     */
    private function getOnlyNewValuesForChangeSet(array $changeSet): array
    {
        $simplifiedStructure = [];

        foreach ($changeSet as $key => $value) {
            if (is_array($value)) {
                // Check if the format is: ['old' => $oldValue, 'new' => $newValue]
                if (isset($value['old'], $value['new'])) {
                    // Replace it with just the new vlaue
                    $simplifiedStructure[$key] = $value['new'];
                } else {
                    // Otherwise, recursively process the nested array
                    $simplifiedStructure[$key] = $this->getOnlyNewValuesForChangeSet($value);
                }
            } else {
                // Copy any non-array elements as they are
                $simplifiedStructure[$key] = $value;
            }
        }

        return $simplifiedStructure;
    }

    private function checkForPictogramAndReplaceLinkWithFileContent(array $relevantProcedurePublicPhaseChanges): array
    {
        if (array_key_exists(
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->value,
            $relevantProcedurePublicPhaseChanges
        )) {
            $pictogramFileString = $relevantProcedurePublicPhaseChanges[
                RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->value
            ];
            try {
                $pictogram = $this->fileService->getFileInfoFromFileString($pictogramFileString);
                $this->logger->info(
                    'demosplan-mein-berlin-addon found changed Pictogram - converting file contents to base64',
                    [$pictogram->getFileName(), $pictogram->getPath()]
                );
                if (is_file($pictogram->getPath())) {
                    $relevantProcedurePublicPhaseChanges[
                    RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->value
                    ] = base64_encode(file_get_contents($pictogram->getPath()));
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'demosplan-mein-berlin-addon failed to load/convert the pictogram to base64 string',
                    [$e]
                );

                $relevantProcedurePublicPhaseChanges[
                RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->value
                ] = '';
            }
        }

        return $relevantProcedurePublicPhaseChanges;
    }

    private function truncateBase64FileStringBeforeLogging(array $mappedProcedureSettingsChanges): array
    {
        // cut base64 content for logging purpose
        if(array_key_exists(
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->name,
            $mappedProcedureSettingsChanges)
        ) {
            $mappedProcedureSettingsChanges[
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->name
            ] = substr(
                $mappedProcedureSettingsChanges[
                RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->name
                ],
                0,
                64
            );
        }

        return $mappedProcedureSettingsChanges;
    }

}
