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

enum RelevelantProcedurePhasePropertiesForMeinBerlinCommunication: string
{
    use CommonEnumMethods;

    case start_date = 'startDate';
    case end_date = 'endDate';
    case status = 'name';
}
