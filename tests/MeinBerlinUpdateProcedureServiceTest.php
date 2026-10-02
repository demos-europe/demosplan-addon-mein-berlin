<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Tests;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\MeinBerlinTransferType;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedurePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureSettingsPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevelantProcedurePhasePropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinProcedureCommunicator;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinProcedurePictogramFileHandler;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinProcedureSettingsCoordinateHandler;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinTransferFailureNotifier;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinUpdateProcedureService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\RouterInterface;

class MeinBerlinUpdateProcedureServiceTest extends TestCase
{
    private ?MeinBerlinUpdateProcedureService $sut = null;
    private LoggerInterface|MockObject|null $logger = null;
    private MeinBerlinProcedureCommunicator|MockObject|null $communicator = null;
    private MeinBerlinTransferFailureNotifier|MockObject|null $transferFailureNotifier = null;
    private ProcedureInterface|MockObject|null $procedure = null;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')
            ->willReturn('test');
        $router = $this->createMock(RouterInterface::class);
        $this->communicator = $this->createMock(MeinBerlinProcedureCommunicator::class);
        $this->transferFailureNotifier = $this->createMock(MeinBerlinTransferFailureNotifier::class);
        $this->procedure = $this->createMock(ProcedureInterface::class);
        $messageBag = $this->createMock(MessageBagInterface::class);
        $procedurePictogramFileHandler = $this->createMock(
            MeinBerlinProcedurePictogramFileHandler::class
        );
        $procedureCoordinateHandler = $this->createMock(
            MeinBerlinProcedureSettingsCoordinateHandler::class
        );
        $procedurePictogramFileHandler->method('checkForPictogramAndGetBase64FileString')
            ->willReturn('');

        $this->sut = new MeinBerlinUpdateProcedureService(
            $this->logger,
            $parameterBag,
            $router,
            $this->communicator,
            $messageBag,
            $procedurePictogramFileHandler,
            $procedureCoordinateHandler,
            $this->transferFailureNotifier
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
            'demosplan-mein-berlin-addon discovered the following important Procedure changes:',
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
            'testBplanId',
            'testProcedureId',
            $this->procedure
        );
        self::assertCount(4, $expectedMessages);
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
            'testBplanId',
            'testProcedureId',
            $this->procedure
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
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::tile_image->value => ['old' => 'oldUrl', 'new' => ''],
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
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::start_date->name => (new \DateTime())->format('Y-m-d'),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::end_date->name => (new \DateTime())->format('Y-m-d'),
            RelevelantProcedurePhasePropertiesForMeinBerlinCommunication::status->name => 'newStatus',
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::tile_image->name => '',
            RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::image_alt_text->name => 'newAlt'
        ];

        foreach ($mappedResult as $key => $value) {
            self::assertArrayHasKey($key, $exptectedRsult);
            self::assertSame($value, $exptectedRsult[$key]);
        }
    }

    public function testNotifiesAboutFailedUpdateWithoutPropagatingTheException(): void
    {
        $exception = new MeinBerlinCommunicationException('testingPurpose');
        $this->communicator->method('updateProcedure')->willThrowException($exception);
        $this->transferFailureNotifier->expects(self::once())
            ->method('notifyAboutFailedTransfer')
            ->with($this->procedure, $exception, MeinBerlinTransferType::update);

        $this->sut->updateMeinBerlinProcedureEntry(
            [
                RelevantProcedurePropertiesForMeinBerlinCommunication::name->value => ['old' => 'oldName', 'new' => 'newName'],
            ],
            null,
            'meinBerlinOrganisationId',
            'testBplanId',
            'testProcedureId',
            $this->procedure
        );
    }

    public function testDoesNotNotifyAfterSuccessfulUpdate(): void
    {
        $this->transferFailureNotifier->expects(self::never())->method('notifyAboutFailedTransfer');

        $this->sut->updateMeinBerlinProcedureEntry(
            [
                RelevantProcedurePropertiesForMeinBerlinCommunication::name->value => ['old' => 'oldName', 'new' => 'newName'],
            ],
            null,
            'meinBerlinOrganisationId',
            'testBplanId',
            'testProcedureId',
            $this->procedure
        );
    }

    public function testNotifiesAboutFailedDistrictUpdateBeforePropagatingTheException(): void
    {
        $exception = new MeinBerlinCommunicationException('testingPurpose');
        $this->communicator->method('updateProcedure')->willThrowException($exception);
        $this->transferFailureNotifier->expects(self::once())
            ->method('notifyAboutFailedTransfer')
            ->with($this->procedure, $exception, MeinBerlinTransferType::update);

        $this->expectException(MeinBerlinCommunicationException::class);
        $this->sut->updateDistrictByResourceType(
            $this->createMock(MeinBerlinAddonEntity::class),
            'meinBerlinOrganisationId',
            'testBplanId',
            'testProcedureId',
            $this->procedure
        );
    }
}
