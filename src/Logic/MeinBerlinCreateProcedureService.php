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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\MapProjectionConverterInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedurePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureSettingsPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevelantProcedurePhasePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use function array_key_exists;
use function substr;

class MeinBerlinCreateProcedureService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MapProjectionConverterInterface $mapProjectionConverter,
        private readonly ParameterBagInterface $parameterBag,
        private readonly RouterInterface $router,
        private readonly MeinBerlinProcedureCommunicator $meinBerlinProcedureCommunicator,
        private readonly MessageBagInterface $messageBag,
        private readonly MeinBerlinProcedurePictogramFileHandler $meinBerlinProcedurePictogramFileHandler,
    ){

    }

    /**
     * @throws MeinBerlinCommunicationException
     */
    public function createMeinBerlinProcedure(
        ProcedureInterface $procedure,
        MeinBerlinAddonEntity $correspondingAddonEntity,
        MeinBerlinAddonOrgaRelation $correspondingAddonOrgaRelation,
        bool $calledViaResourceTypeFlushIsQueued = false
    ): void {
        $this->logger->info(
            'demosplan-mein-berlin-addon discovered a procedure update for a new not yet communicated procedure.
             This procedure is now relevant for MeinBerlin as it is:
             publicly visible and its organisation has a MeinBerlin identifier assigned.
             => gathering all needed data to POST this procedure to MeinBerlin',
            [$correspondingAddonEntity, $correspondingAddonOrgaRelation, $procedure]
        );

        try {
            $procedureCreateRequestData = $this->getRelevantProcedureCreateData(
                $procedure,
                $correspondingAddonEntity,
                $correspondingAddonOrgaRelation
            );
            $this->meinBerlinProcedureCommunicator->createProcedure(
                $procedureCreateRequestData,
                $correspondingAddonEntity,
                $correspondingAddonOrgaRelation->getMeinBerlinOrganisationId(),
                $calledViaResourceTypeFlushIsQueued
            );
            $this->messageBag->add('confirm', 'mein.berlin.communication.create.success');
        } catch (MeinBerlinCommunicationException $e) {
            $this->messageBag->add('error', 'mein.berlin.communication.create.error');
            // propagate only if flush is still in queue - otherwise nothing can be done.
            if ($calledViaResourceTypeFlushIsQueued) {
                throw $e;
            }
        }
    }

    /**
     * @return array<string, string|bool>
     */
    public function getRelevantProcedureCreateData(
        ProcedureInterface $procedure,
        MeinBerlinAddonEntity $correspondingAddonEntity,
        MeinBerlinAddonOrgaRelation $correspondingAddonOrgaRelation
    ): array {
        $data = [
            RelevantProcedurePropertiesForMeinBerlinCommunication::name->name => $procedure->getExternalName(),
            RelevantProcedurePropertiesForMeinBerlinCommunication::description->name => $procedure->getExternalDesc(),
            RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::url->name =>
                $this->generateProcedurePublicRoute($procedure->getCurrentSlug()->getName()),
            RelevantProcedurePropertiesForMeinBerlinCommunication::office_worker_email->name =>
                $procedure->getAgencyMainEmailAddress(),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::start_date->name =>
                $procedure->getPublicParticipationPhaseObject()->getStartDate()->format('Y-m-d'),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::end_date->name =>
                $procedure->getPublicParticipationPhaseObject()->getEndDate()->format('Y-m-d'),
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::tile_image->name =>
                $this->getBase64PictogramFileString($procedure),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::status->name =>
                $procedure->getPublicParticipationPhaseObject()->getName(),
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::point->name => $this->getCoordinateAsGeoJSON($procedure),
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_copyright->name =>
                $procedure->getSettings()->getPictogramCopyright(),
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_alt_text->name =>
                $procedure->getSettings()->getPictogramAltText(),
            MeinBerlinAddonEntity::MEIN_BERLIN_PROCEDURE_SHORT_NAME => $correspondingAddonEntity->getProcedureShortName(),
            MeinBerlinAddonOrgaRelation::MEIN_BERLIN_ORGANISATION_ID => $correspondingAddonOrgaRelation->getMeinBerlinOrganisationId(),
            MeinBerlinAddonEntity::MEIN_BERLIN_IS_DRAFT => false,
        ];
        $this->logProcedureCreateData($data);

        return $data;
    }

    private function getBase64PictogramFileString(ProcedureInterface $procedure): string
    {
        $pictogramFileString = $procedure->getPictogram();

        return $this->meinBerlinProcedurePictogramFileHandler
            ->checkForPictogramAndGetBase64FileString($pictogramFileString);
    }

    private function generateProcedurePublicRoute(string $slug): string
    {
        try {
            $routeName = $this->parameterBag->get('mein_berlin_public_procedure_route');
            $route = $this->router->generate(
                $routeName,
                ['slug' => $slug],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } catch (Exception $e) {
            $this->logger->error(
                'failed generating the public procedure link for MeinBerlin',
                [$e]
            );
            $route = 'Not Available';
        }

        return $route;
    }

    /**
     * @param array<string, string|bool> $procedureCreateData
     */
    private function logProcedureCreateData(array $procedureCreateData): void
    {
        $procedureCreateData = $this->truncateBase64FileStringBeforeLogging($procedureCreateData);
        $this->logger->info(
            'demosplan-mein-berlin-addon prepared data for a procedure create POST:',
            [$procedureCreateData]
        );
    }

    /**
     * @param array<string, string|bool> $mappedProcedureData
     * @return array<string, string>
     */
    private function truncateBase64FileStringBeforeLogging(array $mappedProcedureData): array
    {
        // cut base64 content for logging purpose
        if(array_key_exists(
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::tile_image->name,
            $mappedProcedureData)
        ) {
            $mappedProcedureData[
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::tile_image->name
            ] = substr(
                $mappedProcedureData[
                RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::tile_image->name
                ],
                0,
                64
            );
        }

        return $mappedProcedureData;
    }

    private function getCoordinateAsGeoJSON(ProcedureInterface $procedure): string
    {
        $coordinate = $procedure->getCoordinate();
        if (null === $coordinate || '' === $coordinate) {
            return '';
        }

        $coordinate4326 = $this->mapProjectionConverter->convertCoordinate(
            $coordinate,
            $this->mapProjectionConverter->getProjection('EPSG:3857'),
            $this->mapProjectionConverter->getProjection('EPSG:4326')
        );
        $geoJson = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    $coordinate4326[0],
                    $coordinate4326[1],
                ],
            ],
        ];

        try {
            return json_encode($geoJson, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('failed to convert the coordinate to geojson', [$e]);
        }

        return '';
    }

}
