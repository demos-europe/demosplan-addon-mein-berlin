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
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonEntityRepository;
use Exception;
use InvalidArgumentException;
use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
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
        string $dplanId,
        string $procedureId
    ): void {
        try {
            $method = 'PATCH';
            $url = str_replace(
                ['<organisation-id>', '<bplan-id>'],
                [$organisationId, $dplanId],
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
                $this->logger->error(
                    'demosplan-mein-berlin-addon failed transmitting the procedure create message during update',
                    [
                        'statusCode' => $statusCode,
                        'procedureId' => $procedureId,
                        'meinBerlinOrganisationId' => $organisationId,
                        'meinBerlinProcedureCommunicationId' => $dplanId,
                        'PATCH url' => $url,
                        'payload' => $preparedProcedureData,
                        'content' => $response->getContent(false)
                    ]
                );
                throw new MeinBerlinCommunicationException('failed to update procedure data for meinBerlin');
            }
            $this->logger->info(
                'demosplan-mein-berlin-addon successfully transmitted updated procedure data',
                ['procedureId' => $procedureId, 'PATCH url' => $url, 'payload' => $preparedProcedureData]
            );
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
            throw new MeinBerlinCommunicationException($e->getMessage());
        } catch (
            TransportExceptionInterface|
            ClientExceptionInterface|
            RedirectionExceptionInterface|
            ServerExceptionInterface $e
        ) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed transmitting the procedure update message',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    'procedureId' => $procedureId,
                    'payload' => $preparedProcedureData,
                ]
            );
            throw new MeinBerlinCommunicationException($e->getMessage());
        } catch (Exception $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed updating a procedure.',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    'procedureId' => $procedureId,
                ]
            );
            throw new MeinBerlinCommunicationException($e->getMessage());
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
                $this->logger->error(
                    'demosplan-mein-berlin-addon failed transmitting the procedure create message during create, non 2xx status code',
                    [
                        'statusCode' => $statusCode,
                        'meinBerlinOrganisationId' => $organisationId,
                        $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                        'payload' => $preparedProcedureData,
                        'content' => $response->getContent(false)
                    ]
                );
                throw new MeinBerlinCommunicationException(
                    'demosplan-mein-berlin-addon failed to transmit a procedure create message'
                );
            }
            $responseContent = $response->getContent();
            $this->logger->info('demosplan-mein-berlin-addon got create response content: ', [$responseContent]);
            $dplanCommunicationId = $this->extractDplanCommunicationId($responseContent);
            $this->attachDplanCommunicationId($dplanCommunicationId, $correspondingAddonEntity, $flushIsInQueued);
            $this->logger->info(
                'demosplan-mein-berlin-addon successfully transmitted a new procedure',
                [
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'POST url' => $url,
                ]
            );

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
            throw new MeinBerlinCommunicationException($e->getMessage());
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
            throw new MeinBerlinCommunicationException($e->getMessage());
        } catch (
            TransportExceptionInterface|
            ClientExceptionInterface|
            RedirectionExceptionInterface|
            ServerExceptionInterface $e
        ) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed transmitting the procedure create message',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'payload' => $preparedProcedureData,
                    'content' => $response?->getContent(false)

                ]
            );
            throw new MeinBerlinCommunicationException($e->getMessage());
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
            throw new MeinBerlinCommunicationException($e->getMessage());
        } catch (Exception $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed creating a new procedure.',
                [
                    'Exception' => $e,
                    'ExceptionMessage' => $e->getMessage(),
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                ]
            );
            throw new MeinBerlinCommunicationException($e->getMessage());
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
    private function extractDplanCommunicationId(string $responseContent): string
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

    private function attachDplanCommunicationId(
        string $dplanId,
        MeinBerlinAddonEntity $meinBerlinAddonEntity,
        bool $flushIsQueued
    ): void {
        $meinBerlinAddonEntity->setDplanId($dplanId);
        $this->logger->info(
            'demosplan-mein-berlin-addon peristing new procedure related dplanCommunicationId',
            [$dplanId]
        );
        $this->addonEntityRepository->persistMeinBerlinAddonEntity($meinBerlinAddonEntity);
        if (!$flushIsQueued) {
            $this->logger->info('flush straight away if not in queue via resourceType');
            $this->addonEntityRepository->flushEverything();
        }
    }
}
