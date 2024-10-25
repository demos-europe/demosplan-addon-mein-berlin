<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum;

enum RelevelantProcedurePhasePropertiesForMeinBerlinCommunication: string
{
    use CommonEnumMethods;
    case start_date = 'startDate';
    case end_date = 'endDate';
    case status = 'name';
}
