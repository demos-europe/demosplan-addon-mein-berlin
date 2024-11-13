<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception;

use LogicException;

class MeinBerlinRssFeedException extends LogicException
{
    public static function missingParameter(string $string): self
    {
        return new self(sprintf('Missing configuration parameter `%s`', $string));
    }
}