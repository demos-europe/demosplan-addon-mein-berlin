<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic;

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonEntityRepository;
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
                $this->getParameter('procedure_creat_url')
            );
            $json = json_encode($preparedProcedureData, JSON_THROW_ON_ERROR);

            $this->logger->info('demosplan-mein-berlin-addon sends POST to create Procedure now!', [$url]);
            $response = $this->httpClient->request(
                $method,
                $url,
                [
                    'headers' => $this->getMeinBerlinHeader(),
                    'json' => $json
                ]
            );
            $statusCode = $response->getStatusCode();
            if (200 > $statusCode || 299 < $statusCode) {
                $this->logger->error(
                    'demosplan-mein-berlin-addon failed transmitting the procedure create message',
                    [
                        $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                        'json' => $json,
                    ]
                );
                // todo handle error on sent
            }
            $responseContent = $response->getContent();
            $this->logger->info('demosplan-mein-berlin-addon got create response content: ', [$responseContent]);
            $dplanCommunicationId = $this->extractDplanCommunicationId($responseContent);
            $this->attachDplanCommunicationId($dplanCommunicationId, $correspondingAddonEntity, $flushIsInQueued);
            $this->logger->info(
                'demosplan-mein-berlin-addon succesfully transmitted a new procedure',
                [$correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId()]
            );

        } catch (ParameterNotFoundException $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed to transmit a procedure create message',
                [
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'procedureData' => $preparedProcedureData,
                ]
            );
        } catch (JsonException) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed to parse requestData',
                [
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'procedureData' => $preparedProcedureData,
                ]
            );
        } catch (
            TransportExceptionInterface|
            ClientExceptionInterface|
            RedirectionExceptionInterface|
            ServerExceptionInterface $e
        ) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed transmitting the procedure create message',
                [
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'json' => $json,
                ]
            );
        } catch (InvalidArgumentException) {
            $this->logger->error(
                'demosplan-mein-berlin-addon failed to parse the responseContent.
                 Expected different type or layout',
                [
                    $correspondingAddonEntity->getProcedure()?->getName() => $correspondingAddonEntity->getProcedure()?->getId(),
                    'json' => $json,
                ]
            );
        }


    }

    /**
     * Gets a parameter by its name.
     * @throws ParameterNotFoundException
     * @return array<int|string, mixed>|bool|string|int|float|\UnitEnum|null
     */
    private function getParameter(string $name): array|bool|string|int|float|\UnitEnum|null
    {
        return $this->parameterBag->get($name);
    }

    /**
     * @return array{Accept: 'application/json', Content-Type: 'application/json', Authorization: non-empty-string}
     * @throws ParameterNotFoundException
     * @throws InvalidArgumentException
     */
    private function getMeinBerlinHeader(): array
    {
        $bearerAuth = $this->getParameter('meinBerlinAuthorization');
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
        $procedureRelatedCommunicationId = $responseContentArray['id'];
        Assert::string(
            $procedureRelatedCommunicationId,
            'demosplan-mein-berlin-addon procedure communication id must be of type string.'
        );

        return $procedureRelatedCommunicationId;
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
