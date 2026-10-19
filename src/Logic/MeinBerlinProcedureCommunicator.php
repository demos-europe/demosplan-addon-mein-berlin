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

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\MeinBerlinCommunicationErrorCategory;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\RelevantProcedureSettingsPropertiesForMeinBerlinCommunication;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonEntityRepository;
use Exception;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;
use Webmozart\Assert\Assert;
use const JSON_OBJECT_AS_ARRAY;
use const JSON_THROW_ON_ERROR;

class MeinBerlinProcedureCommunicator
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag,
        private readonly MeinBerlinAddonEntityRepository $addonEntityRepository,
    ) {

    }

    /**
     * @param array<string, string|bool> $preparedProcedureData
     * @throws MeinBerlinCommunicationException
     */
    public function updateProcedure(
        array $preparedProcedureData,
        string $organisationId,
        string $bplanId,
        string $procedureId
    ): void {
        $response = null;
        try {
            $method = 'PATCH';
            $url = str_replace(
                ['<organisation-id>', '<bplan-id>'],
                [$organisationId, $bplanId],
                $this->parameterBag->get('mein_berlin_procedure_update_url')
            );
            $this->logger->info('demosplan-mein-berlin-addon sends PATCH to update Procedure now!', [$url]);
            $response = $this->httpClient->request(
                $method,
                $url,
                [
                    'headers' => $this->getMeinBerlinHeader(),
                    'json' => $preparedProcedureData
                ]
            );

            $statusCode = $response->getStatusCode();
            if (200 > $statusCode || 299 < $statusCode) {
                $responseBody = $this->extractResponseBody($response);
                $this->logger->error(
                    'demosplan-mein-berlin-addon failed transmitting the procedure create message during update',
                    [
                        'statusCode' => $statusCode,
                        'procedureId' => $procedureId,
                        'meinBerlinOrganisationId' => $organisationId,
                        'meinBerlinProcedureCommunicationId' => $bplanId,
                        'PATCH url' => $url,
                        'payload' => $this->truncateTileImageForLogging($preparedProcedureData),
                        'content' => $responseBody
                    ]
                );
                throw new MeinBerlinCommunicationException(
                    sprintf('failed to update procedure data for meinBerlin, status code %d', $statusCode),
                    category: $this->getCategoryForStatusCode($statusCode),
                    httpStatus: $statusCode,
                    responseBody: $responseBody
                );
            }
            $this->logger->info(
                'demosplan-mein-berlin-addon successfully transmitted updated procedure data',
                ['procedureId' => $procedureId, 'PATCH url' => $url, 'payload' => $preparedProcedureData]
            );
        } catch (MeinBerlinCommunicationException $e) {
            // already logged and described in detail where it was thrown
            throw $e;
        } catch (ParameterNotFoundException $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed to transmit a procedure update message
                - check all parameters are correctly set/defined',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    'procedureId' => $procedureId,
                    'procedureData' => $preparedProcedureData,
                ]
            );
            throw new MeinBerlinCommunicationException(
                $e->getMessage(),
                previous: $e,
                category: MeinBerlinCommunicationErrorCategory::configuration
            );
        } catch (InvalidArgumentException $e) {
            // nothing is parsed on update, so this can only be an unusable configuration, e.g. an empty authorization
            $this->logger->error(
                'demosplan-mein-berlin-addon failed to prepare the procedure update message',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    'procedureId' => $procedureId,
                ]
            );
            throw new MeinBerlinCommunicationException(
                $e->getMessage(),
                previous: $e,
                category: MeinBerlinCommunicationErrorCategory::configuration
            );
        } catch (
            TransportExceptionInterface|
            ClientExceptionInterface|
            RedirectionExceptionInterface|
            ServerExceptionInterface $e
        ) {
            $failedResponse = $e instanceof HttpExceptionInterface ? $e->getResponse() : $response;
            $responseBody = $this->extractResponseBody($failedResponse);
            $this->logger->error(
                'demosplan-mein-berlin-addon failed transmitting the procedure update message',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    'procedureId' => $procedureId,
                    'payload' => $preparedProcedureData,
                    'content' => $responseBody,
                ]
            );
            throw new MeinBerlinCommunicationException(
                $e->getMessage(),
                previous: $e,
                category: $this->getCategoryForHttpClientException($e),
                httpStatus: $this->extractStatusCode($failedResponse),
                responseBody: $responseBody
            );
        } catch (Exception $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed updating a procedure.',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    'procedureId' => $procedureId,
                ]
            );
            throw new MeinBerlinCommunicationException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @param array<string, string|bool> $preparedProcedureData
     * @throws MeinBerlinCommunicationException
     */
    public function createProcedure(
        array $preparedProcedureData,
        MeinBerlinAddonEntity $correspondingAddonEntity,
        string $organisationId,
        bool $flushIsInQueued
    ): void {
        $response = null;
        try {
            $method = 'POST';
            $url = str_replace(
                '<organisation-id>',
                $organisationId,
                $this->parameterBag->get('mein_berlin_procedure_create_url')
            );

            $this->logger->info('demosplan-mein-berlin-addon sends POST to create Procedure now!', [$url]);
            $response = $this->httpClient->request(
                $method,
                $url,
                [
                    'headers' => $this->getMeinBerlinHeader(),
                    'json' => $preparedProcedureData
                ]
            );
            $statusCode = $response->getStatusCode();
            if (200 > $statusCode || 299 < $statusCode) {
                $responseBody = $this->extractResponseBody($response);
                $this->logger->error(
                    'demosplan-mein-berlin-addon failed transmitting the procedure create message during create, non 2xx status code',
                    [
                        'statusCode' => $statusCode,
                        'meinBerlinOrganisationId' => $organisationId,
                        $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                        'content' => $responseBody,
                        'payload' => $this->truncateTileImageForLogging($preparedProcedureData)
                    ]
                );
                throw new MeinBerlinCommunicationException(
                    sprintf(
                        'demosplan-mein-berlin-addon failed to transmit a procedure create message, status code %d',
                        $statusCode
                    ),
                    category: $this->getCategoryForStatusCode($statusCode),
                    httpStatus: $statusCode,
                    responseBody: $responseBody
                );
            }
            $responseContent = $response->getContent();
            $this->logger->info('demosplan-mein-berlin-addon got create response content: ', [$responseContent]);
            $bplanCommunicationId = $this->extractBplanCommunicationId($responseContent);
            $this->attachBplanCommunicationId($bplanCommunicationId, $correspondingAddonEntity, $flushIsInQueued);
            $this->logger->info(
                'demosplan-mein-berlin-addon successfully transmitted a new procedure',
                [
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'POST url' => $url,
                ]
            );

        } catch (MeinBerlinCommunicationException $e) {
            // already logged and described in detail where it was thrown
            throw $e;
        } catch (ParameterNotFoundException $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed to transmit a procedure create message',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'procedureData' => $preparedProcedureData,
                ]
            );
            throw new MeinBerlinCommunicationException(
                $e->getMessage(),
                previous: $e,
                category: MeinBerlinCommunicationErrorCategory::configuration
            );
        } catch (JsonException $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed to parse requestData',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'procedureData' => $preparedProcedureData,
                ]
            );
            throw new MeinBerlinCommunicationException(
                $e->getMessage(),
                previous: $e,
                category: MeinBerlinCommunicationErrorCategory::invalid_response
            );
        } catch (
            TransportExceptionInterface|
            ClientExceptionInterface|
            RedirectionExceptionInterface|
            ServerExceptionInterface $e
        ) {
            $failedResponse = $e instanceof HttpExceptionInterface ? $e->getResponse() : $response;
            $responseBody = $this->extractResponseBody($failedResponse);
            $this->logger->error(
                'demosplan-mein-berlin-addon failed transmitting the procedure create message',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'payload' => $preparedProcedureData,
                    'content' => $responseBody

                ]
            );
            throw new MeinBerlinCommunicationException(
                $e->getMessage(),
                previous: $e,
                category: $this->getCategoryForHttpClientException($e),
                httpStatus: $this->extractStatusCode($failedResponse),
                responseBody: $responseBody
            );
        } catch (InvalidArgumentException $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed to parse the responseContent.
                 Expected different type or layout',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'payload' => $preparedProcedureData,
                ]
            );
            // without a response the failure happened before sending, e.g. while building the request headers
            throw new MeinBerlinCommunicationException(
                $e->getMessage(),
                previous: $e,
                category: $response instanceof ResponseInterface
                    ? MeinBerlinCommunicationErrorCategory::invalid_response
                    : MeinBerlinCommunicationErrorCategory::configuration
            );
        } catch (Exception $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed creating a new procedure.',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                ]
            );
            throw new MeinBerlinCommunicationException($e->getMessage(), previous: $e);
        }
    }

    private function getCategoryForStatusCode(int $statusCode): MeinBerlinCommunicationErrorCategory
    {
        return match (true) {
            500 <= $statusCode => MeinBerlinCommunicationErrorCategory::server_error,
            400 <= $statusCode => MeinBerlinCommunicationErrorCategory::rejected,
            default => MeinBerlinCommunicationErrorCategory::invalid_response,
        };
    }

    private function getCategoryForHttpClientException(Throwable $exception): MeinBerlinCommunicationErrorCategory
    {
        return match (true) {
            $exception instanceof TimeoutExceptionInterface => MeinBerlinCommunicationErrorCategory::timeout,
            $exception instanceof TransportExceptionInterface => MeinBerlinCommunicationErrorCategory::unreachable,
            $exception instanceof ClientExceptionInterface => MeinBerlinCommunicationErrorCategory::rejected,
            $exception instanceof ServerExceptionInterface => MeinBerlinCommunicationErrorCategory::server_error,
            default => MeinBerlinCommunicationErrorCategory::invalid_response,
        };
    }

    /**
     * Reading the body of a failed request can fail again (e.g. connection lost), which must not hide the original failure.
     */
    private function extractResponseBody(?ResponseInterface $response): ?string
    {
        try {
            return $response?->getContent(false);
        } catch (Throwable) {
            return null;
        }
    }

    private function extractStatusCode(?ResponseInterface $response): ?int
    {
        try {
            return $response?->getStatusCode();
        } catch (Throwable) {
            return null;
        }
    }


    /**
     * @return array{Accept: 'application/json', Content-Type: 'application/json', Authorization: non-empty-string}
     * @throws ParameterNotFoundException
     * @throws InvalidArgumentException
     */
    private function getMeinBerlinHeader(): array
    {
        $bearerAuth = $this->parameterBag->get('mein_berlin_authorization');
        Assert::stringNotEmpty($bearerAuth);

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $bearerAuth
        ];
    }

    /**
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    private function extractBplanCommunicationId(string $responseContent): string
    {
        /** @var array{ id: non-empty-string, embed_code: string } $responseContentArray */
        $responseContentArray = json_decode(
            $responseContent,
            true,
            512,
            JSON_OBJECT_AS_ARRAY | JSON_THROW_ON_ERROR
        );
        $this->logger->info('decoded create responseContent: ', $responseContentArray);
        Assert::isArray($responseContentArray);
        Assert::keyExists(
            $responseContentArray,
            'id',
            'demosplan-mein-berlin-addon failed to extract the id
            necessary for future procedure related communication.'
        );
        return (string)$responseContentArray['id'];
    }

    /**
     * @param array<string, string|bool> $payload
     * @return array<string, string|bool>
     */
    private function truncateTileImageForLogging(array $payload): array
    {
        $key = RelevantProcedureSettingsPropertiesForMeinBerlinCommunication::tile_image->name;
        if (isset($payload[$key]) && is_string($payload[$key])) {
            $payload[$key] = substr($payload[$key], 0, 64);
        }

        return $payload;
    }

    private function attachBplanCommunicationId(
        string $bplanId,
        MeinBerlinAddonEntity $meinBerlinAddonEntity,
        bool $flushIsQueued
    ): void {
        $meinBerlinAddonEntity->setBplanId($bplanId);
        $this->logger->info(
            'demosplan-mein-berlin-addon peristing new procedure related bplanCommunicationId',
            [$bplanId]
        );
        $this->addonEntityRepository->persistMeinBerlinAddonEntity($meinBerlinAddonEntity);
        if (!$flushIsQueued) {
            $this->logger->info('flush straight away if not in queue via resourceType');
            $this->addonEntityRepository->flushEverything();
        }
    }
}
