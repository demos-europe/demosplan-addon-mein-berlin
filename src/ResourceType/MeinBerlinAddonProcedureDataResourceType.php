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
use DemosEurope\DemosplanAddon\Contracts\ResourceType\AddonResourceType;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\ProcedureResourceTypeInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions\Features;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonEntityRepository;
use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\Factory\CallbackAttributeSetBehaviorFactory;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;

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
    ) {

    }
    protected function getAccessConditions(): array
    {
        $currentProcedure = $this->currentContextProviderInterface->getCurrentProcedure();

        return $this->conditionFactory->propertyHasValue(
            $currentProcedure?->getId(),
            ['procedure']
        );
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
        $configBuilder->procedureShortName->setReadableByPath()->setSortable()->setFilterable()
            ->addUpdateBehavior(
                new CallbackAttributeSetBehaviorFactory(
                    [],
                    function (MeinBerlinAddonEntity $meinBerlinAddonEntity, ?string $procedureShortName): array {
                        $this->logger->info('demosplan-mein-berlin-addon registered a procedureShortName update
                         - check if this change needs to be communicated to meinBerlin',
                            [$procedureShortName, $meinBerlinAddonEntity]
                        );
                        // todo check if update needs to be sent
                        $meinBerlinAddonEntity->setProcedureShortName($procedureShortName);

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
                    $this->meinBerlinAddonEntityRepository->persistMeinBerlinAddonEntity($meinBerlinAddonEntity);
                    $this->logger->info('demosplan-mein-berlin-addon registered a new procedureShortName
                         - check if all conditions for a create procedure entry at MeinBerlin are met.',
                        [$meinBerlinAddonEntity, $entityData]
                    );
                    // todo trigger create if everything else is set - and conditions are met

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
        return false;
    }

    public function getTypeName(): string
    {
        return 'MeinBerlinAddonProcedureData';
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isCreateAllowed();
    }
}
