<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedurePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureSettingsPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevelantProcedurePhasePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinProcedureCommunicator;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinUpdateProcedureService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;

class MeinBerlinUpdateProcedureServiceTest extends TestCase
{
    private ?MeinBerlinUpdateProcedureService $sut = null;
    private LoggerInterface|MockObject|null $logger = null;

    protected function setUp(): void
    {
        $fileService = $this->createMock(FileServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')
            ->willReturn('test');
        $router = $this->createMock(RouterInterface::class);
        $meinBerlinProcedureCommunicator = $this->createMock(MeinBerlinProcedureCommunicator::class);
        $messageBag = $this->createMock(MessageBagInterface::class);
        $defaultStorage = $this->createMock(FilesystemOperator::class);

        $this->sut = new MeinBerlinUpdateProcedureService(
            $fileService,
            $this->logger,
            $parameterBag,
            $router,
            $meinBerlinProcedureCommunicator,
            $messageBag,
            $defaultStorage
        );
    }
    public function testUpdateMeinBerlinProcedureEntryWithRelevantChanges()
    {
        $changeSet = [
            'externalName' => ['old' => 'oldName', 'new' => 'newName'],
            'externalDesc' => ['old' => 'oldDesc', 'new' => 'newDesc'],
        ];

        $index = 0;
        $expectedMessages = [
            'demosplan-mein-berlin-addon discovered a procedure update with fields that
                might be relevant to communicate. - start collecting relevant changes',
            'demosplan-mein-berlin-addon discovered the following relevant Procedure changes:',
            'demosplan-mein-berlin-addon mapped relevant Procedure changes like:',
        ];
        $this->logger->method('info')
            ->willReturnCallback(
                function (string $message, array $context) use (&$index, $expectedMessages) {
                    if ($message === $expectedMessages[$index]) {
                        $index++;
                    }
                }
            );
        // Act
        $this->sut->updateMeinBerlinProcedureEntry(
            $changeSet,
            null,
            'meinBerlinOrganisationId',
            'testDplanId',
            'testProcedureId',
        );
        self::assertCount(3, $expectedMessages);
    }

    public function testUpdateMeinBerlinProcedureEntryWithIrrelevantChanges()
    {
        $changeSet = [
            'irrelevantField' => ['old' => 'oldValue', 'new' => 'newValue'],
        ];

        $this->logger->expects(self::never())
            ->method('info');

        // Act
        $this->sut->updateMeinBerlinProcedureEntry(
            $changeSet,
            null,
            'meinBerlinOrganisationId',
            'testDplanId',
            'testProcedureId',
        );
    }

    public function testCollectRelevantFieldsOnlyAndMapCorrectly(): void
    {
        $phaseChangeSet = [
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::status->value => ['old' => 'oldStatus', 'new' => 'newStatus'],
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::start_date->value => ['old' => new DateTime(), 'new' => new DateTime()],
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::end_date->value => ['old' => new DateTime(), 'new' => new DateTime()],
            'irrelevantField' => ['old' => 'oldValue', 'new' => 'newValue'],
        ];
        $slugChangeSet = [
            'irrelevantField' => ['old' => 'oldValue', 'new' => 'newValue'],
        ];
        $settingsChangeSet = [
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->value => ['old' => 'oldUrl', 'new' => ''],
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_alt_text->value => ['old' => 'oldAlt', 'new' => 'newAlt'],
            'irrelevantField' => ['old' => 'oldValue', 'new' => 'newValue'],
        ];
        $changeSet = [
            RelevantProcedurePropertiesForMeinBerlinCommunication::name->value => ['old' => 'oldName', 'new' => 'newName'],
            RelevantProcedurePropertiesForMeinBerlinCommunication::description->value => ['old' => 'oldDesc', 'new' => 'newDesc'],
            'irrelevantField' => ['old' => 'oldValue', 'new' => 'newValue'],
            RelevantProcedurePropertiesForMeinBerlinCommunication::PARTICIPATIONPHASE->value => $phaseChangeSet,
            RelevantProcedurePropertiesForMeinBerlinCommunication::CURRENTSLUG->value => $slugChangeSet,
            RelevantProcedurePropertiesForMeinBerlinCommunication::SETTINGS->value => $settingsChangeSet,
        ];

        $mappedResult = $this->sut->collectRelevantFields($changeSet);

        $exptectedRsult = [
            RelevantProcedurePropertiesForMeinBerlinCommunication::name->name => 'newName',
            RelevantProcedurePropertiesForMeinBerlinCommunication::description->name => 'newDesc',
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::start_date->name => (new DateTime())->format('Y-m-d'),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::end_date->name => (new DateTime())->format('Y-m-d'),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::status->name => 'newStatus',
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_url->name => '',
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_alt_text->name => 'newAlt'
        ];

        foreach ($mappedResult as $key => $value) {
            self::assertArrayHasKey($key, $exptectedRsult);
            self::assertSame($value, $exptectedRsult[$key]);
        }
    }
}
