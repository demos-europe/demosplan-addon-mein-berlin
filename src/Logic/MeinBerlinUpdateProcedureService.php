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

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedurePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureSettingsPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevelantProcedurePhasePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\ResourceType\MeinBerlinAddonProcedureDataResourceType;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use function is_array;
use function array_key_exists;
use function array_merge;
use function substr;
use function is_file;
use function base64_encode;
use function file_get_contents;

class MeinBerlinUpdateProcedureService
{
    public function __construct(
        private readonly FileServiceInterface $fileService,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag,
        private readonly RouterInterface $router,
        private readonly MeinBerlinProcedureCommunicator $procedureCommunicator,
        private readonly MessageBagInterface $messageBag,
    ){

    }

    /**
     * @throws MeinBerlinCommunicationException
     */
    public function updateProcedureShortNameByResourceType(
        MeinBerlinAddonEntity $changedEntity,
        string $meinBerlinOrganisationId,
        string $dplanId,
        string $procedureId
    ): void
    {
        $fieldsToUpdate = [];
        $fieldsToUpdate['bplan_id'] = $changedEntity->getProcedureShortName();

        try {
            $this->procedureCommunicator->updateProcedure(
                $fieldsToUpdate,
                $meinBerlinOrganisationId,
                $dplanId,
                $procedureId
            );
            $this->messageBag->add('confirm', 'mein.berlin.communication.update.success');
        } catch (MeinBerlinCommunicationException $e) {
            // logs have been written
            $this->messageBag->add('error', 'mein.berlin.communication.update.error');
            // rethrow here to prevent the flushing the change on our side
            throw $e;
        }

    }

    /** This Method will not include the { @link MeinBerlinAddonEntity::$procedureShortName }
     * as those changes will be sent in a separate PATCH request triggert by the corresponding
     * { @link MeinBerlinAddonProcedureDataResourceType }
     *
     * @param array<string, mixed> $changeSet
     */
    public function updateMeinBerlinProcedureEntry(
        array $changeSet,
        ?bool $isPublished,
        string $meinBerlinOrganisationId,
        string $dplanId,
        string $procedureId
    ): void
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
            if (null !== $isPublished) {
                $fieldsToUpdate['is_published '] = $isPublished;
            }

            try {
                $this->procedureCommunicator->updateProcedure(
                    $fieldsToUpdate,
                    $meinBerlinOrganisationId,
                    $dplanId,
                    $procedureId
                );
                $this->messageBag->add('confirm', 'mein.berlin.communication.update.success');
            } catch (MeinBerlinCommunicationException $e) {
                // logs have been written already
                $this->messageBag->add('error', 'mein.berlin.communication.update.error');
                // do not rethrow this Exception as the procedure updates are flushed anyhow and information is lost
            }
        }
    }

    /**
     * @param array<string, mixed> $changeSet
     * @return array<string, string|bool>
     */
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
                $mappedProcedureSettingsChanges = $this->mapProcedureSettingsChanges($value);
                // merge prepared changes
                $importantChanges = array_merge($importantChanges, $mappedProcedureSettingsChanges);

                $this->logMappedProcedureSettingsChanges($mappedProcedureSettingsChanges);

                continue;
            }
            if (RelevantProcedurePropertiesForMeinBerlinCommunication::PARTICIPATIONPHASE->value === $name &&
                RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::hasRelevantPropertyBeenChanged($value)
            ) {
                $mappedProcedurePublicPhaseChanges = $this->mapProcedurePublicParticipationPhaseChanges($value);

                $importantChanges = array_merge($importantChanges, $mappedProcedurePublicPhaseChanges);

                $this->logger->info(
                    'demosplan-mein-berlin-addon mapped relevant ProcedureSettings changes like:',
                    $mappedProcedurePublicPhaseChanges
                );

                continue;
            }
            if (RelevantProcedurePropertiesForMeinBerlinCommunication::CURRENTSLUG->value === $name &&
                RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::
                    hasRelevantPropertyBeenChanged($value)
            ) {
                $mappedProcedureCurrentSlugChanges = $this->mapProcedureCurrentSlugChanges($value);

                $importantChanges = array_merge($importantChanges, $mappedProcedureCurrentSlugChanges);

                $this->logger->info(
                    'demosplan-mein-berlin-addon mapped relevant ProcedureCurrentSlug changes like:',
                    $mappedProcedureCurrentSlugChanges
                );

                continue;
            }
            $relevantProcedureChanges[] = $value;
            $importantChanges[
            RelevantProcedurePropertiesForMeinBerlinCommunication::getNameFromValue((string) $name)
            ] = $value;
        }
        $this->logger->info(
            'demosplan-mein-berlin-addon discovered relevant the following Procedure changes:',
            $relevantProcedureChanges
        );
        $this->logger->info(
            'demosplan-mein-berlin-addon mapped relevant Procedure changes like:',
            RelevantProcedurePropertiesForMeinBerlinCommunication::mapToCommunicationNamesIfValuesExist(
                $relevantProcedureChanges
            )
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
     * @param array<string, mixed> $changeSet
     * @return array<string, mixed>
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

    /**
     * @param array<string, mixed> $relevantProcedurePublicPhaseChanges
     * @return array<string, mixed>
     */
    private function checkForPictogramAndReplaceLinkWithFileContent(array $relevantProcedurePublicPhaseChanges): array
    {
        if (array_key_exists(
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->value,
            $relevantProcedurePublicPhaseChanges
        )) {
            $pictogramFileString = $relevantProcedurePublicPhaseChanges[
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->value
            ];
            if ('' !== $pictogramFileString && null !== $pictogramFileString) {
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
        }

        return $relevantProcedurePublicPhaseChanges;
    }

    /**
     * @param array<string, string> $mappedProcedureSettingsChanges
     */
    private function logMappedProcedureSettingsChanges(array $mappedProcedureSettingsChanges): void
    {
        $mappedProcedureSettingsChanges = $this->truncateBase64FileStringBeforeLogging(
            $mappedProcedureSettingsChanges
        );
        $this->logger->info(
            'demosplan-mein-berlin-addon mapped relevant ProcedureSettings changes like:',
            $mappedProcedureSettingsChanges
        );
    }

    /**
     * @param array<string, string> $mappedProcedureSettingsChanges
     * @return array<string, string>
     */
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

    /**
     * @param array<string, mixed> $relevantProcedureSlugChanges
     * @return array<string, mixed>
     */
    private function getUrlByCurrentSlug(array $relevantProcedureSlugChanges): array
    {
        if (array_key_exists(
            RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::url->value,
            $relevantProcedureSlugChanges)
        ) {
            $slug = $relevantProcedureSlugChanges[
            RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::url->value
            ];
            try {
                $routeName = $this->getParameter('public_procedure_route');
                $route = $this->router->generate(
                    $routeName,
                    ['slug' => $slug],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                $relevantProcedureSlugChanges[
                RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::url->value
                ] = $route;
            } catch (Exception $e) {
                $this->logger->error(
                    'failed generating the public procedure link for MeinBerlin',
                    [$e]
                );
                $relevantProcedureSlugChanges[
                RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::url->value
                ] = 'Not Available';
            }
        }

        return $relevantProcedureSlugChanges;
    }

    /**
     * @param array<string, mixed> $relevantProcedurePublicPhaseChanges
     * @return array<string, mixed>
     */
    private function formatDateTime(array $relevantProcedurePublicPhaseChanges): array
    {
        foreach ($relevantProcedurePublicPhaseChanges as $key => $value) {
            if ($value instanceof DateTime) {
                $relevantProcedurePublicPhaseChanges[$key] = $value->format('Y-m-d');
            }
        }

        return $relevantProcedurePublicPhaseChanges;
    }

    /**
     * @param array<string, mixed> $procedurePhaseChangeSet
     * @return array<string, string>
     */
    private function mapProcedurePublicParticipationPhaseChanges(array $procedurePhaseChangeSet): array
    {
        // get relevant public ProcedurePhase Changes:
        $relevantProcedurePublicPhaseChanges =
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::getChangedProperties($procedurePhaseChangeSet);
        $this->logger->info(
            'demosplan-mein-berlin-addon discovered relevant ProcedurePublicPhase changes:',
            $relevantProcedurePublicPhaseChanges
        );
        $relevantProcedurePublicPhaseChanges = $this->formatDateTime($relevantProcedurePublicPhaseChanges);

        // map our property names to their requested names
        return RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::
            mapToCommunicationNamesIfValuesExist($relevantProcedurePublicPhaseChanges);
    }

    /**
     * @param array<string, mixed> $procedureSettingsChangeSet
     * @return array<string, string>
     */
    private function mapProcedureSettingsChanges(array $procedureSettingsChangeSet): array
    {
        // get relevant ProcedureSettings Changes:
        $relevantProcedureSettingsChanges =
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::getChangedProperties(
                $procedureSettingsChangeSet
            );
        $this->logger->info(
            'demosplan-mein-berlin-addon discovered relevant ProcedureSettings changes:',
            $relevantProcedureSettingsChanges
        );
        $relevantProcedureSettingsChanges = $this->checkForPictogramAndReplaceLinkWithFileContent(
            $relevantProcedureSettingsChanges
        );

        // map our property names to their requested names
        return RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::
            mapToCommunicationNamesIfValuesExist($relevantProcedureSettingsChanges);
    }

    /**
     * @param array<string, mixed> $procedureCurrentSlugChanges
     * @return array<string, string>
     */
    private function mapProcedureCurrentSlugChanges(array $procedureCurrentSlugChanges): array
    {
        $relevantProcedureCurrentSlugChanges = RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::
        getChangedProperties($procedureCurrentSlugChanges);
        $this->logger->info(
            'demosplan-mein-berlin-addon discovered relevant ProcedureCurrentSlug changes:',
            $relevantProcedureCurrentSlugChanges
        );
        $relevantProcedureCurrentSlugChanges = $this->getUrlByCurrentSlug($relevantProcedureCurrentSlugChanges);

        return RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::
        mapToCommunicationNamesIfValuesExist($relevantProcedureCurrentSlugChanges);
    }

    /**
     * Gets a parameter by its name.
     * @throws ParameterNotFoundException
     * @return array<int|string, mixed>|bool|string|int|float|\UnitEnum|null
     */
    private function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
    {
        return $this->parameterBag->get($name);
    }

}
