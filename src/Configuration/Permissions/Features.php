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

    /**
     * Allows to set a custom organisation id related to our organisation id.
     * This is a necessary parameter of an update url for meinBerlin
     */
    public static function feature_set_mein_berlin_organisation_id(): self
    {
        return new self('feature_set_mein_berlin_organisation_id');
    }
}
