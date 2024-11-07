<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
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
                        $this->handleProcedureShortNameUpdateAttempt($meinBerlinAddonEntity, $procedureShortName);

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
                    $this->handleProcedureShortNameCreateAttempt($meinBerlinAddonEntity);

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
     */
    private function handleProcedureShortNameCreateAttempt(
        MeinBerlinAddonEntity $meinBerlinAddonEntity
    ): void {
        // check if create is allowed by checking the presence of a corresponding MeinBerlin OrganisationId
        $currentProcedure = $this->currentContextProviderInterface->getCurrentProcedure();
        Assert::notNull($currentProcedure);
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
        // creation is allowed from here on.
        $this->meinBerlinAddonEntityRepository->persistMeinBerlinAddonEntity($meinBerlinAddonEntity);
        // check if create message should be sent by checking the procedurePhase
        // lastly check if a dplanId (communicationId) is already set - this would be an error here - log potential case
        if (null !== $currentProcedure &&
            '' !== $meinBerlinAddonEntity->getDplanId() &&
            $this->meinBerlinCommunicationHelper->checkProcedurePublicPhasePermissionsetNotHidden($currentProcedure)
        ) {
            $correspondingAddonOrgaRelation = $this->meinBerlinCommunicationHelper
                ->getCorrespondingOrgaRelation($currentProcedure);
            Assert::notNull($correspondingAddonOrgaRelation);
            $this->createProcedureService->createMeinBerlinProcedure(
                $currentProcedure,
                $meinBerlinAddonEntity,
                $correspondingAddonOrgaRelation
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function handleProcedureShortNameUpdateAttempt(
        MeinBerlinAddonEntity $meinBerlinAddonEntity,
        ?string $procedureShortName
    ): void {
        $meinBerlinAddonEntity->setProcedureShortName($procedureShortName);
        $organisationId = $this->meinBerlinCommunicationHelper
            ->getCorrespondingOrgaRelation(
                $this->currentContextProviderInterface->getCurrentProcedure()
            )?->getMeinBerlinOrganisationId();
        // the organisationId can not be null as in theory
        // you can only create this entity in the first place with an existing id
        Assert::notNull($organisationId);
        // check if update message should be sent by checking an existent communicationId
        if ('' !== $meinBerlinAddonEntity->getDplanId()) {
            $this->logger->info(
                'meinBerlin procedureShortName update is relevant to communicate as
                this procedure is known to / has been transferred to -meinBerlin',
                ['newShortName' => $procedureShortName, 'assignedCommunicationId' => $meinBerlinAddonEntity->getDplanId()]
            );
            $this->updateProcedureService->updateProcedureShortNameByResourceType(
                $meinBerlinAddonEntity,
                $organisationId
            );
        }
    }
}
