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

enum RelevantProcedurePropertiesForMeinBerlinCommunication: string
{
    use CommonEnumMethods;

    case name = 'externalName';
    case description = 'externalDesc';
    case office_worker_email = 'agencyMainEmailAddress';
    case PARTICIPATIONPHASE = 'publicParticipationPhase';
    case SETTINGS = 'settings';
    case CURRENTSLUG = 'currentSlug';
}
