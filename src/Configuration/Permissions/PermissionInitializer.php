<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Permission\PermissionConditionBuilder;
use DemosEurope\DemosplanAddon\Permission\PermissionInitializerInterface;
use DemosEurope\DemosplanAddon\Permission\ResolvablePermissionCollectionInterface;

class PermissionInitializer implements PermissionInitializerInterface
{


    public function configurePermissions(ResolvablePermissionCollectionInterface $permissionCollection): void
    {
        $permissionCollection->configurePermissionInstance(
            Features::feature_set_mein_berlin_organisation_id(),
            PermissionConditionBuilder::start()->enableIfUserHasRole(RoleInterface::PLATFORM_SUPPORT)
        );
    }
}
