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

use DemosEurope\DemosplanAddon\Contracts\Entities\MailSendInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\MailServiceInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\MeinBerlinTransferType;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinTransferFailureMessageBuilder;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinTransferFailureNotifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MeinBerlinTransferFailureNotifierTest extends TestCase
{
    private MeinBerlinTransferFailureNotifier|null $sut = null;
    private LoggerInterface|MockObject|null $logger = null;
    private MailServiceInterface|MockObject|null $mailService = null;
    private ProcedureInterface|MockObject|null $procedure = null;
    private MeinBerlinTransferFailureMessageBuilder|MockObject|null $messageBuilder = null;
    private ParameterBagInterface|MockObject|null $parameterBag = null;

    protected function setUp(): void
    {
        $this->procedure = $this->createMock(ProcedureInterface::class);
        $this->procedure->method('getId')->willReturn('procedureId');
        $this->procedure->method('getName')->willReturn('procedureName');
        $this->procedure->method('getAgencyMainEmailAddress')->willReturn('agency@example.org');

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mailService = $this->createMock(MailServiceInterface::class);

        $this->messageBuilder = $this->createMock(MeinBerlinTransferFailureMessageBuilder::class);
        $this->messageBuilder->method('getSubject')->willReturn('subject');
        $this->messageBuilder->method('getBody')->willReturn('body');

        $this->parameterBag = $this->createMock(ParameterBagInterface::class);

        $this->sut = new MeinBerlinTransferFailureNotifier(
            $this->logger,
            $this->mailService,
            $this->messageBuilder,
            $this->parameterBag
        );
    }

    public function testSendsMailWithBuiltSubjectAndBody(): void
    {
        $this->parameterBag->method('get')->with('mein_berlin_failure_mail_cc')->willReturn('cc@example.org');

        $this->mailService->expects(self::once())
            ->method('sendMail')
            ->with(
                'dm_schlussmitteilung',
                'de_DE',
                'agency@example.org',
                '',
                ['cc@example.org'],
                '',
                'extern',
                ['mailsubject' => 'subject', 'mailbody' => 'body']
            )
            ->willReturn($this->createMock(MailSendInterface::class));

        $this->sut->notifyAboutFailedTransfer($this->procedure, new MeinBerlinCommunicationException('failed'), MeinBerlinTransferType::create);
    }

    public function testPassesTheTransferTypeToTheMessageBuilder(): void
    {
        $messageBuilder = $this->createMock(MeinBerlinTransferFailureMessageBuilder::class);
        $messageBuilder->expects(self::once())->method('getSubject')
            ->with($this->procedure, MeinBerlinTransferType::update)->willReturn('subject');
        $messageBuilder->expects(self::once())->method('getBody')
            ->with($this->procedure, self::isInstanceOf(MeinBerlinCommunicationException::class), MeinBerlinTransferType::update)
            ->willReturn('body');
        $sut = new MeinBerlinTransferFailureNotifier(
            $this->logger,
            $this->mailService,
            $messageBuilder,
            $this->parameterBag
        );
        $this->mailService->expects(self::once())->method('sendMail')
            ->willReturn($this->createMock(MailSendInterface::class));

        $sut->notifyAboutFailedTransfer(
            $this->procedure,
            new MeinBerlinCommunicationException('failed'),
            MeinBerlinTransferType::update
        );
    }

    /**
     * @dataProvider carbonCopyProvider
     *
     * @param list<string> $expected
     */
    public function testSplitsCarbonCopyRecipients(?string $configured, array $expected): void
    {
        $this->parameterBag->method('get')->willReturn($configured);

        $this->mailService->expects(self::once())
            ->method('sendMail')
            ->with(
                self::anything(), self::anything(), self::anything(), self::anything(),
                $expected,
                self::anything(), self::anything(), self::anything()
            )
            ->willReturn($this->createMock(MailSendInterface::class));

        $this->sut->notifyAboutFailedTransfer($this->procedure, new MeinBerlinCommunicationException('failed'), MeinBerlinTransferType::create);
    }

    /**
     * @return array<string, array{string|null, list<string>}>
     */
    public function carbonCopyProvider(): array
    {
        return [
            'environment variable not set' => [null, []],
            'empty' => ['', []],
            'one address' => ['cc@example.org', ['cc@example.org']],
            'several addresses with spaces and gaps' => [' a@example.org , ,b@example.org,', ['a@example.org', 'b@example.org']],
        ];
    }

    public function testDoesNotSendMailWithoutRecipient(): void
    {
        $procedure = $this->createMock(ProcedureInterface::class);
        $procedure->method('getAgencyMainEmailAddress')->willReturn(' ');

        $this->mailService->expects(self::never())->method('sendMail');
        $this->logger->expects(self::once())->method('warning');

        $this->sut->notifyAboutFailedTransfer($procedure, new MeinBerlinCommunicationException('failed'), MeinBerlinTransferType::create);
    }

    public function testMailProblemDoesNotPropagate(): void
    {
        $this->mailService->method('sendMail')->willThrowException(new RuntimeException('no template'));
        $this->logger->expects(self::once())->method('error');

        $this->sut->notifyAboutFailedTransfer($this->procedure, new MeinBerlinCommunicationException('failed'), MeinBerlinTransferType::create);
    }
}
