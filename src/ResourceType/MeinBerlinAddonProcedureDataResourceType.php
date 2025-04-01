<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */
namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\ResourceType;

use DemosEurope\DemosplanAddon\Contracts\CurrentContextProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\Exceptions\AddonResourceNotFoundException;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\AddonResourceType;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\ProcedureResourceTypeInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions\Features;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinCommunicationHelper;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinCreateProcedureService;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinUpdateProcedureService;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonEntityRepository;
use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\Factory\CallbackAttributeSetBehaviorFactory;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use InvalidArgumentException;
use Webmozart\Assert\Assert;
use function array_key_exists;

/**
 * @template-extends AddonResourceType<MeinBerlinAddonEntity>
 */
class MeinBerlinAddonProcedureDataResourceType extends AddonResourceType
{
    public function __construct(
        private readonly ConditionFactoryInterface $conditionFactory,
        private readonly CurrentContextProviderInterface $currentContextProviderInterface,
        private readonly PermissionEvaluatorInterface $permissionEvaluator,
        private readonly ProcedureResourceTypeInterface $procedureResourceType,
        private readonly MeinBerlinAddonEntityRepository $meinBerlinAddonEntityRepository,
        private readonly MeinBerlinCommunicationHelper $meinBerlinCommunicationHelper,
        private readonly MeinBerlinCreateProcedureService $createProcedureService,
        private readonly MeinBerlinUpdateProcedureService $updateProcedureService,
        private readonly MessageBagInterface $messageBag,
    ) {

    }
    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentContextProviderInterface->getCurrentProcedure();

        return [$this->conditionFactory->propertyHasValue(
            $currentProcedure?->getId(),
            ['procedure']
        )];
    }

    public function isCreateAllowed(): bool
    {
        return $this->permissionEvaluator
            ->isPermissionEnabled(Features::feature_set_mein_berlin_procedure_short_name());
    }

    public function isDeleteAllowed(): bool
    {
        return $this->isCreateAllowed();
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        $currentProcedureCondition = $this->conditionFactory->propertyHasValue(
            $this->currentContextProviderInterface->getCurrentProcedure()?->getId(),
            ['id']
        );

        $configBuilder = new MeinBerlinAddonProcedureDataResourceConfigBuilder(
            $this->getEntityClass(),
            $this->getPropertyBuilderFactory()
        );

        $configBuilder->id->setReadableByPath()->setSortable()->setFilterable();
        $configBuilder->procedureShortName->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable()
            ->addUpdateBehavior(
                new CallbackAttributeSetBehaviorFactory(
                    [],
                    function (MeinBerlinAddonEntity $meinBerlinAddonEntity, ?string $procedureShortName): array {
                        $this->logger->info('demosplan-mein-berlin-addon registered a procedureShortName update
                         - check if this change needs to be communicated to meinBerlin',
                            [$procedureShortName, $meinBerlinAddonEntity]
                        );
                        try {
                            $this->handleProcedureShortNameUpdateAttempt($meinBerlinAddonEntity, $procedureShortName);
                        } catch (InvalidArgumentException $e) {
                            $this->logger->error(
                                'demosplan-mein-berlin-addon is missing mandatory properties/relations',
                                [$e]
                            );
                            $this->messageBag->add('error', 'mein.berlin.communication.update.error');
                            throw $e;
                        }

                        return [];
                    },
                    OptionalField::NO
                )
            )
            ->addPathCreationBehavior();
        $configBuilder->procedure->setRelationshipType($this->procedureResourceType)
            ->setReadableByPath()
            ->setFilterable()
            ->setSortable()
            ->addPathCreationBehavior(
                OptionalField::NO,
                [],
                [$currentProcedureCondition]
            );
        $configBuilder->addCreationBehavior(
            new FixedSetBehavior(
                function (MeinBerlinAddonEntity $meinBerlinAddonEntity, EntityDataInterface $entityData): array {
                    $this->logger->info('demosplan-mein-berlin-addon registered a new procedureShortName
                         - check if a necessary MeinBerlin OrganisationId to allow this create ist set
                          and if all conditions for a create procedure entry at MeinBerlin are met.',
                        [$meinBerlinAddonEntity, $entityData]
                    );
                    $this->handleProcedureShortNameCreateAttempt($meinBerlinAddonEntity, $entityData);

                    return [];
                }
            )
        );

        return $configBuilder;
    }

    public function getEntityClass(): string
    {
        return MeinBerlinAddonEntity::class;
    }

    public function isGetAllowed(): bool
    {
        return $this->isAvailable();
    }

    public function isAvailable(): bool
    {
        return $this->permissionEvaluator
            ->isPermissionEnabled(Features::feature_set_mein_berlin_procedure_short_name());
    }

    public function isListAllowed(): bool
    {
        return $this->isGetAllowed();
    }

    public function getTypeName(): string
    {
        return 'MeinBerlinAddonProcedureData';
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isCreateAllowed();
    }

    /**
     * @throws AddonResourceNotFoundException
     * @throws InvalidArgumentException
     * @throws MeinBerlinCommunicationException
     */
    private function handleProcedureShortNameCreateAttempt(
        MeinBerlinAddonEntity $meinBerlinAddonEntity,
        EntityDataInterface $entityData
    ): void {
        // check if create is allowed by checking the presence of a corresponding MeinBerlin OrganisationId
        $currentProcedure = $this->currentContextProviderInterface->getCurrentProcedure();
        Assert::notNull($currentProcedure);
        $procedureId = ((array) $entityData->getToOneRelationships())['procedure']['id'] ?? null;
        if (null === $procedureId
            || $procedureId !== $currentProcedure?->getId()
        ) {
            throw new AddonResourceNotFoundException('create with invalid procedure is invalid');
        }
        if ($this->meinBerlinCommunicationHelper->getCorrespondingAddonEntity($currentProcedure)) {
            $this->logger->warning(
                'A second MeinBerlinAddonEntity for this procedure was tried to be created',
                ['procedureId' => $currentProcedure->getId()]
            );

            return;
        }

        if (!$this->meinBerlinCommunicationHelper->hasOrganisationIdSet($currentProcedure)) {
            $this->logger->info('FP-A tried to set a procedureShortName, but his organisation has not
            MeinBerlinOrganisationId set yet - therefore this action is not allowed');
            $this->messageBag->add(
                'error',
                'mein.berlin.organisation.id.missing'
            );

            throw new AddonResourceNotFoundException(
                'Can not create a MeinBerlinAddonEntity as no MeinBerlinAddonOrgaRelation has been set yet'
            );
        }
        if (!array_key_exists('procedureShortName', $entityData->getAttributes())
            || '' === $entityData->getAttributes()['procedureShortName']
        ) {
            $this->logger->info('FP-A tried saving an empty meinBerlin procedureShortName');
            $this->messageBag->add(
                'error',
                'mein.berlin.error.create.empty.procedure.short.name'
            );

            throw new AddonResourceNotFoundException('create with empty procedureShortName is invalid');
        }
        // creation is allowed from here on.
        $this->meinBerlinAddonEntityRepository->persistMeinBerlinAddonEntity($meinBerlinAddonEntity);
        // check if create message should be sent by checking the procedurePhase and an existing pictogram
        // lastly check if a dplanId (communicationId) is already set - this would be an error here - unique constraint
        // we do not want to send a message before the database says nope.
        $hasPictogram = $currentProcedure->getPictogram() !== null && $currentProcedure->getPictogram() !== '';
        if (!$this->meinBerlinCommunicationHelper->hasDplanIdSet($currentProcedure) &&
            $this->meinBerlinCommunicationHelper->checkProcedurePublicPhasePermissionsetNotHidden($currentProcedure) &&
            $hasPictogram
        ) {
            $correspondingAddonOrgaRelation = $this->meinBerlinCommunicationHelper
                ->getCorrespondingOrgaRelation($currentProcedure);
            Assert::notNull($correspondingAddonOrgaRelation);
            $meinBerlinAddonEntity->setProcedureShortName($entityData->getAttributes()['procedureShortName']);
            $this->createProcedureService->createMeinBerlinProcedure(
                $currentProcedure,
                $meinBerlinAddonEntity,
                $correspondingAddonOrgaRelation,
                true
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws MeinBerlinCommunicationException
     */
    private function handleProcedureShortNameUpdateAttempt(
        MeinBerlinAddonEntity $meinBerlinAddonEntity,
        ?string $procedureShortName
    ): void {
        if ('' === $procedureShortName) {
            $this->logger->info('FP-A tried saving an empty meinBerlin procedureShortName');
            $this->messageBag->add(
                'error',
                'mein.berlin.error.create.empty.procedure.short.name'
            );

            throw new AddonResourceNotFoundException('create with empty procedureShortName is invalid');
        }
        $meinBerlinAddonEntity->setProcedureShortName($procedureShortName);
        $oragnisationRelation = $this->meinBerlinCommunicationHelper
            ->getCorrespondingOrgaRelation(
                $this->currentContextProviderInterface->getCurrentProcedure()
            );
        Assert::notNull($oragnisationRelation);
        $organisationId = $oragnisationRelation->getMeinBerlinOrganisationId();
        // the organisationId can not be empty as in theory
        // you can only create this Addon entity in the first place with an existing id
        Assert::stringNotEmpty($organisationId);
        // check if update message should be sent by checking an existent communicationId
        if ('' === $meinBerlinAddonEntity->getDplanId()) {
            $this->logger->info(
                'this procedure has not been transmitted to meinBerlin yet.
                No update message will be sent to meinBerlin'
            );
            // still check if all conditions for a create message are fulfilled
            // (viable shortName, publicPhase, pictogram and organisationRelation, but no dplanId)
            // to allow this field as a sort of retrigger if a previous create request failed
            // if a prev update failed is a different question - would be a real problem as its content is lost.
            $currentProcedure = $this->currentContextProviderInterface->getCurrentProcedure();
            $hasPictogram = $currentProcedure->getPictogram() !== null && $currentProcedure->getPictogram() !== '';
            Assert::notNull($currentProcedure);
            if ($this->meinBerlinCommunicationHelper
                ->checkProcedurePublicPhasePermissionsetNotHidden($currentProcedure) && $hasPictogram
            ) {
                $this->logger->warning(
                    'demosplan-mein-berlin-addon registered an update of a procedure that should have been
                    transmitted to myBerlin, but is not. All conditions were met before here and now.
                    - reattempt creating this procedure.',
                    [
                        $currentProcedure->getName() => $currentProcedure->getId(),
                        'PublicParticipationPhasePermissionsetNotHidden' => true,
                        'meinBerlinOrganisationId' => $organisationId,
                        'meinBerlinprocedureShortName' => $procedureShortName,
                    ]
                );
                $this->createProcedureService->createMeinBerlinProcedure(
                    $currentProcedure,
                    $meinBerlinAddonEntity,
                    $oragnisationRelation,
                    true
                );
            }

            return;
        }
        $this->logger->info(
            'meinBerlin procedureShortName update is relevant to communicate as
                this procedure is known to / has been transferred to -meinBerlin',
            ['newShortName' => $procedureShortName, 'assignedCommunicationId' => $meinBerlinAddonEntity->getDplanId()]
        );
        $this->updateProcedureService->updateProcedureShortNameByResourceType(
            $meinBerlinAddonEntity,
            $organisationId,
            $meinBerlinAddonEntity->getDplanId(),
            $this->currentContextProviderInterface->getCurrentProcedure()?->getId(),
        );
    }
}
