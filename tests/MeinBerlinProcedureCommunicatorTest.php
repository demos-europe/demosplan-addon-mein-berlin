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

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\MeinBerlinCommunicationErrorCategory;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinProcedureCommunicator;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonEntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\Exception\TimeoutException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MeinBerlinProcedureCommunicatorTest extends TestCase
{
    private const AUTHORIZATION = 'Bearer secret-token';

    /**
     * @dataProvider failedResponseProvider
     */
    public function testCreateProcedureDescribesFailedResponse(
        int $statusCode,
        MeinBerlinCommunicationErrorCategory $expectedCategory
    ): void {
        $sut = $this->getSut(new MockHttpClient(new MockResponse('some body', ['http_code' => $statusCode])));

        $exception = $this->createAndCatch($sut);

        self::assertSame($expectedCategory, $exception->getCategory());
        self::assertSame($statusCode, $exception->getHttpStatus());
        self::assertSame('some body', $exception->getResponseBody());
        self::assertStringNotContainsString(self::AUTHORIZATION, $exception->getMessage());
    }

    /**
     * @return array<string, array{int, MeinBerlinCommunicationErrorCategory}>
     */
    public function failedResponseProvider(): array
    {
        return [
            'bad request' => [400, MeinBerlinCommunicationErrorCategory::rejected],
            'unprocessable' => [422, MeinBerlinCommunicationErrorCategory::rejected],
            'server error' => [500, MeinBerlinCommunicationErrorCategory::server_error],
            'bad gateway' => [502, MeinBerlinCommunicationErrorCategory::server_error],
            'unexpected status' => [301, MeinBerlinCommunicationErrorCategory::invalid_response],
        ];
    }

    public function testCreateProcedureTruncatesLongResponseBody(): void
    {
        $sut = $this->getSut(new MockHttpClient(new MockResponse(str_repeat('a', 5000), ['http_code' => 500])));

        $exception = $this->createAndCatch($sut);

        self::assertSame(MeinBerlinCommunicationException::MAX_RESPONSE_BODY_LENGTH, mb_strlen($exception->getResponseBody()));
    }

    public function testCreateProcedureDescribesUnreachableServer(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new class('could not resolve host') extends \RuntimeException implements TransportExceptionInterface {
            };
        });

        $exception = $this->createAndCatch($this->getSut($client));

        self::assertSame(MeinBerlinCommunicationErrorCategory::unreachable, $exception->getCategory());
        self::assertNull($exception->getHttpStatus());
        self::assertNull($exception->getResponseBody());
    }

    public function testCreateProcedureDescribesTimeout(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TimeoutException('timed out');
        });

        $exception = $this->createAndCatch($this->getSut($client));

        self::assertSame(MeinBerlinCommunicationErrorCategory::timeout, $exception->getCategory());
    }

    public function testCreateProcedureDescribesUnusableResponse(): void
    {
        $sut = $this->getSut(new MockHttpClient(new MockResponse('no json', ['http_code' => 201])));

        $exception = $this->createAndCatch($sut);

        self::assertSame(MeinBerlinCommunicationErrorCategory::invalid_response, $exception->getCategory());
    }

    public function testCreateProcedureDescribesMissingConfiguration(): void
    {
        $sut = $this->getSut(new MockHttpClient(new MockResponse('{}', ['http_code' => 201])), '');

        $exception = $this->createAndCatch($sut);

        self::assertSame(MeinBerlinCommunicationErrorCategory::configuration, $exception->getCategory());
    }

    /**
     * @dataProvider failedResponseProvider
     */
    public function testUpdateProcedureDescribesFailedResponse(
        int $statusCode,
        MeinBerlinCommunicationErrorCategory $expectedCategory
    ): void {
        $sut = $this->getSut(new MockHttpClient(new MockResponse('some body', ['http_code' => $statusCode])));

        $exception = $this->updateAndCatch($sut);

        self::assertSame($expectedCategory, $exception->getCategory());
        self::assertSame($statusCode, $exception->getHttpStatus());
        self::assertSame('some body', $exception->getResponseBody());
        self::assertStringNotContainsString(self::AUTHORIZATION, $exception->getMessage());
    }

    public function testUpdateProcedureDescribesTimeout(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TimeoutException('timed out');
        });

        $exception = $this->updateAndCatch($this->getSut($client));

        self::assertSame(MeinBerlinCommunicationErrorCategory::timeout, $exception->getCategory());
    }

    public function testUpdateProcedureDescribesMissingConfiguration(): void
    {
        $sut = $this->getSut(new MockHttpClient(new MockResponse('{}', ['http_code' => 200])), '');

        $exception = $this->updateAndCatch($sut);

        self::assertSame(MeinBerlinCommunicationErrorCategory::configuration, $exception->getCategory());
    }

    public function testUpdateProcedureSucceeds(): void
    {
        $sut = $this->getSut(new MockHttpClient(new MockResponse('{}', ['http_code' => 200])));

        $sut->updateProcedure(['name' => 'test'], 'organisationId', 'bplanId', 'procedureId');

        $this->addToAssertionCount(1);
    }

    private function updateAndCatch(MeinBerlinProcedureCommunicator $sut): MeinBerlinCommunicationException
    {
        try {
            $sut->updateProcedure(['name' => 'test'], 'organisationId', 'bplanId', 'procedureId');
        } catch (MeinBerlinCommunicationException $exception) {
            return $exception;
        }
        self::fail('expected a MeinBerlinCommunicationException');
    }

    private function createAndCatch(MeinBerlinProcedureCommunicator $sut): MeinBerlinCommunicationException
    {
        try {
            $sut->createProcedure(['name' => 'test'], $this->createMock(MeinBerlinAddonEntity::class), 'organisationId', false);
        } catch (MeinBerlinCommunicationException $exception) {
            return $exception;
        }
        self::fail('expected a MeinBerlinCommunicationException');
    }

    private function getSut(
        HttpClientInterface $httpClient,
        string $authorization = self::AUTHORIZATION
    ): MeinBerlinProcedureCommunicator {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturnMap([
            ['mein_berlin_procedure_create_url', 'https://mein.berlin.example/organisations/<organisation-id>/bplan/'],
            ['mein_berlin_procedure_update_url', 'https://mein.berlin.example/organisations/<organisation-id>/bplan/<bplan-id>/'],
            ['mein_berlin_authorization', $authorization],
        ]);

        return new MeinBerlinProcedureCommunicator(
            $httpClient,
            $this->createMock(LoggerInterface::class),
            $parameterBag,
            $this->createMock(MeinBerlinAddonEntityRepository::class)
        );
    }
}
