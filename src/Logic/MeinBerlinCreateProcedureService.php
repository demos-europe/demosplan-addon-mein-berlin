<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedurePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureSettingsPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevelantProcedurePhasePropertiesForMeinBerlinCommunication;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class MeinBerlinCreateProcedureService
{
    public function __construct(
        private readonly FileServiceInterface $fileService,
        private readonly LoggerInterface $logger,
        private readonly RouterInterface $router,
    ){

    }

    public function createMeinBerlinProcedure(
        ProcedureInterface $procedure,
        MeinBerlinAddonEntity $correspondingAddonEntity
    ): void {
        $procedureCreateRequestData = $this->getRelevantProcedureCreateData($procedure, $correspondingAddonEntity);
        // todo send POST request
    }

    private function getRelevantProcedureCreateData(
        ProcedureInterface $procedure,
        MeinBerlinAddonEntity $correspondingAddonEntity
    ): array {
        return [
            RelevantProcedurePropertiesForMeinBerlinCommunication::name->name => $procedure->getExternalName(),
            RelevantProcedurePropertiesForMeinBerlinCommunication::description->name => $procedure->getExternalDesc(),
            RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::url->name => $this->router->
            generate(
                'core_procedure_slug',
                ['slug' => $procedure->getCurrentSlug()->getName()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            RelevantProcedurePropertiesForMeinBerlinCommunication::office_worker_email->name =>
                $procedure->getAgencyMainEmailAddress(),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::start_date->name =>
                $procedure->getPublicParticipationPhaseObject()->getStartDate()->format('Y-m-d'),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::end_date->name =>
                $procedure->getPublicParticipationPhaseObject()->getEndDate()->format('Y-m-d'),
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->name =>
                $this->getBase64PictogramFileString($procedure),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::status->name =>
                $procedure->getPublicParticipationPhaseObject()->getName(),
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::point->name => $procedure->getCoordinate(),
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_copyright->name =>
                $procedure->getSettings()->getPictogramCopyright(),
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_alt_text->name =>
                $procedure->getSettings()->getPictogramAltText(),
            'bplan_id' => $correspondingAddonEntity->getProcedureShortName(),
            'organisation_id' => $correspondingAddonEntity->getOrganisationId(),
            'is_published' => true,
        ];
    }

    private function getBase64PictogramFileString(ProcedureInterface $procedure): string
    {
        $pictogramFileString = $procedure->getPictogram();
        $base64FileString = '';

        if ('' !== $pictogramFileString && null !== $pictogramFileString) {
            try {
                $pictogram = $this->fileService->getFileInfoFromFileString($pictogramFileString);
                $this->logger->info(
                    'demosplan-mein-berlin-addon found Pictogram on create - converting file contents to base64',
                    [$pictogram->getFileName(), $pictogram->getPath()]
                );
                if (is_file($pictogram->getPath())) {
                    $base64FileString = base64_encode(file_get_contents($pictogram->getPath()));
                }
            } catch (Exception $e) {
                $this->logger->error(
                    'demosplan-mein-berlin-addon failed to load/convert the pictogram to base64 string',
                    [$e]
                );

            }
        }

        return $base64FileString;
    }

}
