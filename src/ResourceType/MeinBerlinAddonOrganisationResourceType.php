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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\AddonResourceType;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\OrgaResourceTypeInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions\Features;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\Factory\CallbackAttributeSetBehaviorFactory;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;

/**
 * @template-extends AddonResourceType<MeinBerlinAddonOrgaRelation>
 */
class MeinBerlinAddonOrganisationResourceType extends AddonResourceType
{
    public function __construct(
        private readonly ConditionFactoryInterface $conditionFactory,
        private readonly PermissionEvaluatorInterface $permissionEvaluator,
        private readonly OrgaResourceTypeInterface $orgaResourceType,
        private readonly MeinBerlinAddonOrgaRelationRepository $meinBerlinAddonOrgaRelationRepository,
        private readonly CurrentUserInterface $currentUser,
    ) {

    }

    protected function getAccessConditions(): array
    {
        $ownOrgaId = $this->currentUser->getUser()->getOrga()?->getId();

        $conditions = [$this->conditionFactory->false()];
        if ($this->permissionEvaluator->isPermissionEnabled(
            Features::feature_get_mein_berlin_organisation_id()
        )) {
            $conditions = [$this->conditionFactory->propertyHasValue($ownOrgaId, ['orga', 'id'])];
        }
        if ($this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id())) {
            $conditions = [$this->conditionFactory->true()];
        }

        return $conditions;
    }

    public function isCreateAllowed(): bool
    {
        return $this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id());
    }

    public function isDeleteAllowed(): bool
    {
        return $this->isCreateAllowed();
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        $configBuilder = new MeinBerlinAddonOrganisationResourceConfigBuilder(
            $this->getEntityClass(),
            $this->getPropertyBuilderFactory()
        );

        $configBuilder->id->setReadableByPath()->setSortable()->setFilterable();
        $configBuilder->meinBerlinOrganisationId->setReadableByPath()->setSortable()->setFilterable()
            ->addUpdateBehavior(
                new CallbackAttributeSetBehaviorFactory(
                    [],
                    function (
                        MeinBerlinAddonOrgaRelation $meinBerlinAddonOrgaRelation,
                        ?string $meinBerlinOrganisationId
                    ): array {
                        $this->logger->info('demosplan-mein-berlin-addon registered an
                        MeinBerlinOrganisationId update - check if any Procedures were live already at MeinBerlin and
                        attempt to update all of them',
                            [$meinBerlinAddonOrgaRelation, ['newMeinBerlinOrganisationId' => $meinBerlinOrganisationId]]
                        );
                        // todo what todo with allready sent entries
                        $meinBerlinAddonOrgaRelation->setMeinBerlinOrganisationId($meinBerlinOrganisationId);

                        return [];
                    },
                    OptionalField::NO,
                )
            );
        $configBuilder->orga->setRelationshipType($this->orgaResourceType)
            ->setReadableByPath()
            ->setFilterable()
            ->setSortable()
            ->initializable();
        $configBuilder->addPostConstructorBehavior(
            new FixedSetBehavior(
                function (
                    MeinBerlinAddonOrgaRelation $meinBerlinAddonOrgaRelation,
                    EntityDataInterface $entityData
                ): array {
                    $this->meinBerlinAddonOrgaRelationRepository
                        ->persistMeinBerlinAddonOrgaRelation($meinBerlinAddonOrgaRelation);
                    $this->logger->info('demosplan-mein-berlin-addon registered a new
                            MeinBerlinOrganisationId relation
                            - check if any Procedures meet all conditions to be made public at MeinBerlin.',
                        [$meinBerlinAddonOrgaRelation]
                    );
                    // todo trigger create if everything else is set and conditions are met
                    return [];
                }
            )
        );

        return $configBuilder;
    }

    public function getEntityClass(): string
    {
        return MeinBerlinAddonOrgaRelation::class;
    }

    public function isGetAllowed(): bool
    {
        return $this->isAvailable();
    }

    public function isAvailable(): bool
    {
        return $this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id())
            || $this->permissionEvaluator->isPermissionEnabled(Features::feature_get_mein_berlin_organisation_id());
    }

    public function isListAllowed(): bool
    {
        return $this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id());
    }

    public function getTypeName(): string
    {
        return 'MeinBerlinAddonOrgaRelation';
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isCreateAllowed();
    }
}
