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

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonDistrict;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @template-extends MagicResourceConfigBuilder<ClauseFunctionInterface<bool>,OrderBySortMethodInterface, MeinBerlinAddonDistrict>
 *
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,MeinBerlinAddonDistrict> $districtCode
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,MeinBerlinAddonDistrict> $name
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,MeinBerlinAddonDistrict> $meinBerlinOrganisationId
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,MeinBerlinAddonDistrict> $usedByOrganisations
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,MeinBerlinAddonDistrict> $communicatedProcedures
 */
class MeinBerlinAddonDistrictResourceConfigBuilder extends MagicResourceConfigBuilder
{

}
