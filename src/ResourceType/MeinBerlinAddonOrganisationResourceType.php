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

use DemosEurope\DemosplanAddon\Contracts\ResourceType\AddonResourceType;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;

/**
 * @template-extends AddonResourceType<MeinBerlinAddonOrgaRelation>
 */
class MeinBerlinAddonOrganisationResourceType extends AddonResourceType
{
    public function __construct(
        private readonly ConditionFactoryInterface $conditionFactory,
    ) {

    }

    protected function getAccessConditions(): array
    {
        // TODO: Implement getAccessConditions() method.
    }

    public function isCreateAllowed(): bool
    {
        // TODO: Implement isCreateAllowed() method.
    }

    public function isDeleteAllowed(): bool
    {
        // TODO: Implement isDeleteAllowed() method.
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        // TODO: Implement getProperties() method.
    }

    public function getEntityClass(): string
    {
        // TODO: Implement getEntityClass() method.
    }

    public function isGetAllowed(): bool
    {
        // TODO: Implement isGetAllowed() method.
    }

    public function isAvailable(): bool
    {
        // TODO: Implement isAvailable() method.
    }

    public function isListAllowed(): bool
    {
        // TODO: Implement isListAllowed() method.
    }

    public function getTypeName(): string
    {
        // TODO: Implement getTypeName() method.
    }

    public function isUpdateAllowed(): bool
    {
        // TODO: Implement isUpdateAllowed() method.
    }
}
