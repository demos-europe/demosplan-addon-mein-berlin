<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum;

enum RelevantProcedureSettingsPropertiesForMeinBerlinCommunication: string
{
    use CommonEnumMethods;
    case image_url = 'pictogram';
    case image_copyright = 'pictogramCopyright';
    case image_alt_text = 'pictogramAltText';
    case point = 'coordinate';
}
