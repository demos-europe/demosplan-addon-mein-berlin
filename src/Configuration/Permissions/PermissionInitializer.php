<?php

declare(strict_types=1);

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions;

use DemosEurope\DemosplanAddon\Permission\PermissionInitializerInterface;
use DemosEurope\DemosplanAddon\Permission\ResolvablePermissionCollectionInterface;

class PermissionInitializer implements PermissionInitializerInterface
{


    public function configurePermissions(ResolvablePermissionCollectionInterface $permissionCollection): void
    {
        throw new \Exception('Method not yet implemented.');
    }
}
