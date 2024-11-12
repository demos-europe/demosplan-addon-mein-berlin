<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum;

enum RelevantProcedureSettingsPropertiesForMeinBerlinCommunication: string
{
    use CommonEnumMethods;

    case image_url = 'pictogram';
    case image_copyright = 'pictogramCopyright';
    case image_alt_text = 'pictogramAltText';
    case point = 'coordinate';
}
