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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\MeinBerlinCommunicationErrorCategory;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\MeinBerlinTransferType;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinTransferFailureMessageBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

class MeinBerlinTransferFailureMessageBuilderTest extends TestCase
{
    private MeinBerlinTransferFailureMessageBuilder|null $sut = null;
    private ParameterBagInterface|MockObject|null $parameterBag = null;
    private ProcedureInterface|MockObject|null $procedure = null;

    protected function setUp(): void
    {
        $this->procedure = $this->createMock(ProcedureInterface::class);
        $this->procedure->method('getName')->willReturn('Bebauungsplan 1-23');

        $this->parameterBag = $this->createMock(ParameterBagInterface::class);

        // the real translations, to notice errors in the texts and their placeholders
        $translator = new Translator('de');
        $translator->addLoader('yaml', new YamlFileLoader());
        $translator->addResource('yaml', __DIR__.'/../src/translations/messages+intl-icu.de.yml', 'de', 'messages+intl-icu');

        $this->sut = new MeinBerlinTransferFailureMessageBuilder($this->parameterBag, $translator);
    }

    public function testSubjectContainsProcedureName(): void
    {
        self::assertSame(
            'Übermittlung an mein.berlin.de fehlgeschlagen: Bebauungsplan 1-23',
            $this->sut->getSubject($this->procedure, MeinBerlinTransferType::create)
        );
    }

    public function testSubjectAndBodyDifferForAnUpdate(): void
    {
        $this->parameterBag->method('get')->willReturn('');
        $exception = new MeinBerlinCommunicationException('failed');

        self::assertSame(
            'Übermittlung von Änderungen an mein.berlin.de fehlgeschlagen: Bebauungsplan 1-23',
            $this->sut->getSubject($this->procedure, MeinBerlinTransferType::update)
        );
        $body = $this->sut->getBody($this->procedure, $exception, MeinBerlinTransferType::update);
        self::assertStringContainsString(
            'die Übermittlung der Änderungen am Verfahren "Bebauungsplan 1-23" an mein.berlin.de ist fehlgeschlagen.',
            $body
        );
        self::assertStringNotContainsString(
            'die Übermittlung des Verfahrens',
            $body
        );
    }

    public function testBodyContainsReasonSupportAddressAndTechnicalDetails(): void
    {
        $this->parameterBag->method('get')
            ->with('mein_berlin_failure_mail_support_address')
            ->willReturn('support@example.org');
        $exception = new MeinBerlinCommunicationException(
            'failed to transmit',
            category: MeinBerlinCommunicationErrorCategory::rejected,
            httpStatus: 422,
            responseBody: '{"district":["invalid"]}'
        );

        $body = $this->sut->getBody($this->procedure, $exception, MeinBerlinTransferType::create);

        self::assertStringContainsString('"Bebauungsplan 1-23"', $body);
        self::assertStringContainsString('Grund: mein.berlin.de hat die Annahme der Daten verweigert.', $body);
        self::assertStringContainsString('wenden Sie sich bitte an support@example.org', $body);
        self::assertStringContainsString('Kategorie: rejected', $body);
        self::assertStringContainsString('HTTP-Status: 422', $body);
        self::assertStringContainsString('Meldung: failed to transmit', $body);
        self::assertStringContainsString('Antwort von mein.berlin.de: {"district":["invalid"]}', $body);
        self::assertSame(substr_count($body, "\n"), substr_count($body, "\r\n"), 'only CRLF line breaks expected');
    }

    public function testBodyLeavesOutUnknownStatusAndResponse(): void
    {
        $this->parameterBag->method('get')->willReturn('support@example.org');
        $exception = new MeinBerlinCommunicationException(
            'timed out',
            category: MeinBerlinCommunicationErrorCategory::timeout
        );

        $body = $this->sut->getBody($this->procedure, $exception, MeinBerlinTransferType::create);

        self::assertStringContainsString('Zeitüberschreitung', $body);
        self::assertStringNotContainsString('HTTP-Status', $body);
        self::assertStringNotContainsString('Antwort von mein.berlin.de', $body);
    }

    public function testBodyNamesGenericSupportIfNoAddressIsConfigured(): void
    {
        $this->parameterBag->method('get')->willReturn('');

        $body = $this->sut->getBody($this->procedure, new MeinBerlinCommunicationException('failed'), MeinBerlinTransferType::create);

        self::assertStringContainsString('wenden Sie sich bitte an den Support', $body);
    }

    /**
     * @dataProvider categoryProvider
     */
    public function testEveryCategoryHasAReason(MeinBerlinCommunicationErrorCategory $category): void
    {
        $this->parameterBag->method('get')->willReturn('');

        $body = $this->sut->getBody(
            $this->procedure,
            new MeinBerlinCommunicationException('failed', category: $category),
            MeinBerlinTransferType::create
        );

        self::assertStringNotContainsString('mein.berlin.failure.mail.reason', $body, 'translation key is missing');
    }

    /**
     * @return array<string, array{MeinBerlinCommunicationErrorCategory}>
     */
    public function categoryProvider(): array
    {
        $cases = [];
        foreach (MeinBerlinCommunicationErrorCategory::cases() as $category) {
            $cases[$category->value] = [$category];
        }

        return $cases;
    }
}
