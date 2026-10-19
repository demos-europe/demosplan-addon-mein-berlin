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

/**
 * The kind of transfer to mein.berlin.de that failed. The value is used as part of the translation keys
 * of the failure mail.
 */
enum MeinBerlinTransferType: string
{
    case create = 'create';
    case update = 'update';
}
