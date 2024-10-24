<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum;

enum RelevantPropertiesForMeinBerlinCommunication: string
{
    case NAME = 'name';
    case DESCRIPTION = 'description';
    case OFFICE_WORKER_EMAIL = 'officeWorkerEmail';
    case START_DATE = 'startDate';
    case END_DATE = 'endDate';

    public static function hasRelevantPropertyBeenChanged(array $changeSet): bool
    {
        foreach ($changeSet as $propertyName => $propertyValue) {
            if (null !== self::tryFrom($propertyName)) {
                return true;
            }
        }

        return false;
    }

    public static function getChangedProperties(array $changeSet): array
    {
        $changedProperties = [];

        foreach ($changeSet as $propertyName => $propertyValue) {
            if (null !== self::tryFrom($propertyName)) {
                $changedProperties[] = $propertyName;
            }
        }

        return $changedProperties;
    }

}
