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
 * Describes why a request to mein.berlin.de failed. The value is used as part of the translation key
 * `mein.berlin.communication.error.category.<value>` explaining the failure to the user.
 */
enum MeinBerlinCommunicationErrorCategory: string
{
    /** The server could not be reached at all. */
    case unreachable = 'unreachable';
    /** The server did not answer in time. */
    case timeout = 'timeout';
    /** The server answered with a 4xx status code and refused the data. */
    case rejected = 'rejected';
    /** The server answered with a 5xx status code. */
    case server_error = 'server_error';
    /** The server answered, but not with a usable response (unexpected status or content). */
    case invalid_response = 'invalid_response';
    /** The request could not be sent because the addon is not configured correctly. */
    case configuration = 'configuration';
    case unknown = 'unknown';
}
