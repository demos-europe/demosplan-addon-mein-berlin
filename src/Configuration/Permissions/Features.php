<?php

declare(strict_types=1);

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions;

use DemosEurope\DemosplanAddon\Permission\AbstractPermissionMeta;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\MeinBerlinAddon;

class Features extends AbstractPermissionMeta
{
        /**
     * @return non-empty-string
     */
    public function getAddonIdentifier(): string
    {
        return MeinBerlinAddon::ADDON_NAME;
    }
}
