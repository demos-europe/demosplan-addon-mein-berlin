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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @template-extends MagicResourceConfigBuilder<ClauseFunctionInterface<bool>,OrderBySortMethodInterface, MeinBerlinAddonEntity>
 *
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface, MeinBerlinAddonEntity, ProcedureInterface> $procedure
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,MeinBerlinAddonEntity> $bplanId
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,MeinBerlinAddonEntity> $district
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,MeinBerlinAddonEntity> $isInterfaceActivated
 */
class MeinBerlinAddonProcedureDataResourceConfigBuilder extends MagicResourceConfigBuilder
{

}
