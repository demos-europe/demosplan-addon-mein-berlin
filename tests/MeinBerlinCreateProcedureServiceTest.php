<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedurePhaseInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureSettingsInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SlugInterface;
use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedurePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureSettingsPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevelantProcedurePhasePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinCreateProcedureService;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinProcedureCommunicator;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;

class MeinBerlinCreateProcedureServiceTest extends TestCase
{
    private LoggerInterface|MockObject|null $logger = null;
    private MeinBerlinCreateProcedureService|null $sut = null;
    private MessageBagInterface|MockObject|null $messageBag = null;
    private ProcedureInterface|MockObject|null $procedure = null;
    private MeinBerlinAddonEntity|MockObject|null $correspondingAddonEntity = null;
    private MeinBerlinAddonOrgaRelation|MockObject|null $correspondingOrgaRelation = null;

    protected function setUp(): void
    {
        $this->procedure = $this->createMock(ProcedureInterface::class);
        $this->procedure->method('getId')->willReturn('procedureId');
        $this->procedure->method('getExternalName')->willReturn('externalName');
        $this->procedure->method('getExternalDesc')->willReturn('externalDesc');
        $this->procedure->method('getAgencyMainEmailAddress')->willReturn('agencyEmail');
        $this->procedure->method('getCoordinate')->willReturn('coordinate');
        $this->procedure->method('getPictogram')->willReturn('');

        $procedureSettings = $this->createMock(ProcedureSettingsInterface::class);
        $procedureSettings->method('getPictogramCopyright')->willReturn('copyright');
        $procedureSettings->method('getPictogramAltText')->willReturn('altText');
        $this->procedure->method('getSettings')->willReturn($procedureSettings);

        $publicParticipationPhase = $this->createMock(ProcedurePhaseInterface::class);
        $publicParticipationPhase->method('getStartDate')->willReturn(new \DateTime('2024-11-14'));
        $publicParticipationPhase->method('getEndDate')->willReturn(new \DateTime('2099-12-31'));
        $publicParticipationPhase->method('getName')->willReturn('phaseName');
        $this->procedure->method('getPublicParticipationPhaseObject')->willReturn($publicParticipationPhase);

        $currentSlug = $this->createMock(SlugInterface::class);
        $currentSlug->method('getName')->willReturn('slugName');
        $this->procedure->method('getCurrentSlug')->willReturn($currentSlug);

        $this->correspondingAddonEntity = $this->createMock(MeinBerlinAddonEntity::class);
        $this->correspondingAddonEntity->method('getDplanId')->willReturn('dplanId');
        $this->correspondingAddonEntity->method('getProcedureShortName')->willReturn('shortName');

        $this->correspondingOrgaRelation = $this->createMock(MeinBerlinAddonOrgaRelation::class);
        $this->correspondingOrgaRelation->method('getMeinBerlinOrganisationId')->willReturn('organisationId');

        $this->messageBag = $this->createMock(MessageBagInterface::class);

        $fileService = $this->createMock(FileServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $router = $this->createMock(RouterInterface::class);
        $meinBerlinProcedureCommunicator = $this->createMock(MeinBerlinProcedureCommunicator::class);
        $meinBerlinProcedureCommunicator->method('createProcedure')
            ->willReturnCallback(
                function (
                    array $procedureCreateRequestData,
                    MeinBerlinAddonEntity $addonEntity,
                    string $organisationId,
                    bool $isPublished = false
                ): void {
                    // do nothing
                    echo 'test';
                }
            );
        $defaultStorage = $this->createMock(FilesystemOperator::class);

        $parameterBag->method('get')
            ->with('mein_berlin_public_procedure_route')
            ->willReturn('mein_berlin_public_procedure_route');

        $router->method('generate')
            ->willReturn('mein.berlin/test');

        $this->sut = new MeinBerlinCreateProcedureService(
            $fileService,
            $this->logger,
            $parameterBag,
            $router,
            $meinBerlinProcedureCommunicator,
            $this->messageBag,
            $defaultStorage
        );
    }

    public function testGetRelevantProcedureCreateData(): void
    {
        // assert that the logger never gets called attempting to load the file of an empty pictogram
        $never = true;
        $this->logger->method('info')
            ->willReturnCallback(
                function (string $message, array $context = []) use (&$never): void {
                    if ($message ===
                        'demosplan-mein-berlin-addon found Pictogram on create - converting file contents to base64'
                    ) {
                        $never = false;
                    }
                }
            );

        $result = $this->sut->getRelevantProcedureCreateData(
            $this->procedure,
            $this->correspondingAddonEntity,
            $this->correspondingOrgaRelation
        );

        self::assertTrue($never);

        $expected = [
            RelevantProcedurePropertiesForMeinBerlinCommunication::name->name => 'externalName',
            RelevantProcedurePropertiesForMeinBerlinCommunication::description->name => 'externalDesc',
            RelevantProcedureCurrentSlugPropertiesForMeinBerlinCommunication::url->name => 'mein.berlin/test',
            RelevantProcedurePropertiesForMeinBerlinCommunication::office_worker_email->name => 'agencyEmail',
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::start_date->name => '2024-11-14',
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::end_date->name => '2099-12-31',
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::status->name => 'phaseName',
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->name => '',
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::point->name => 'coordinate',
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_copyright->name => 'copyright',
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_alt_text->name => 'altText',
            MeinBerlinAddonEntity::MEIN_BERLIN_PROCEDURE_SHORT_NAME => 'shortName',
            MeinBerlinAddonOrgaRelation::MEIN_BERLIN_ORGANISATION_ID => 'organisationId',
            MeinBerlinAddonEntity::MEIN_BERLIN_IS_DRAFT => false,
        ];

        self::assertEquals($expected, $result);
    }

    public function testExceptionGetsCaughtIfNoFlushInQueue(): void
    {
        // assert that the messageBag gets filled with the confirmation message first
        // which is mocked to throw an exception after adding the step
        // which than fills the messageBag with the error message as an additional step
        $stepsMade = [];
        $this->messageBag->method('add')
            ->willReturnCallback(
                function (string $severity, string $message) use (&$stepsMade) : void {
                    if ($message === 'mein.berlin.communication.create.success'
                        && $severity === 'confirm'
                    ) {
                        $stepsMade[] = true;
                        throw new MeinBerlinCommunicationException('testingPurpose');
                    }
                    if ($message === 'mein.berlin.communication.create.error'
                        && $severity === 'error'
                    ) {
                        $stepsMade[] = true;
                    }
                }
            );

        $this->sut->createMeinBerlinProcedure(
            $this->procedure,
            $this->correspondingAddonEntity,
            $this->correspondingOrgaRelation
        );
        self::assertCount(2, $stepsMade);
        $stepsMade = [];

        $this->expectException(MeinBerlinCommunicationException::class);
        $this->sut->createMeinBerlinProcedure(
            $this->procedure,
            $this->correspondingAddonEntity,
            $this->correspondingOrgaRelation,
            true
        );
        self::assertCount(2, $stepsMade);
    }
}
