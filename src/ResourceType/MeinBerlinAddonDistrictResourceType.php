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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\AddonResourceType;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions\Features;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonDistrict;
use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

/**
 * Exposes the Berlin districts with their mein.berlin.de organisation ID, which the organisation
 * dropdown is built from.
 *
 * @template-extends AddonResourceType<MeinBerlinAddonDistrict>
 */
class MeinBerlinAddonDistrictResourceType extends AddonResourceType
{
    public function __construct(
        private readonly ConditionFactoryInterface $conditionFactory,
        private readonly PermissionEvaluatorInterface $permissionEvaluator,
    ) {
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->true()];
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        $configBuilder = new MeinBerlinAddonDistrictResourceConfigBuilder(
            $this->getEntityClass(),
            $this->getPropertyBuilderFactory()
        );

        $configBuilder->id->setReadableByPath()->setSortable()->setFilterable();
        $configBuilder->districtCode->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable();
        $configBuilder->name->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable();
        $configBuilder->meinBerlinOrganisationId->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable();

        return $configBuilder;
    }

    public function getEntityClass(): string
    {
        return MeinBerlinAddonDistrict::class;
    }

    public function getTypeName(): string
    {
        return 'MeinBerlinAddonDistrict';
    }

    /**
     * The same users who may see an organisation ID need the districts to choose one.
     */
    public function isAvailable(): bool
    {
        return $this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id())
            || $this->permissionEvaluator->isPermissionEnabled(Features::feature_get_mein_berlin_organisation_id());
    }

    public function isGetAllowed(): bool
    {
        return $this->isAvailable();
    }

    public function isListAllowed(): bool
    {
        return $this->isAvailable();
    }

    public function isCreateAllowed(): bool
    {
        return false;
    }

    public function isUpdateAllowed(): bool
    {
        return false;
    }

    public function isDeleteAllowed(): bool
    {
        return false;
    }
}
