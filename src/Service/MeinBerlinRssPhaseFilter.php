<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Service;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function mb_strtolower;
use function preg_replace;
use function str_ends_with;
use function str_getcsv;
use function str_starts_with;
use function strlen;
use function strtoupper;
use function substr;
use function trim;

/**
 * Restricts an RSS feed to procedures in certain phases (Verfahrensschritte), named in a request parameter.
 *
 * Supported notations of the parameter {@link self::PARAMETER_NAME}:
 * - a single name:                  VERFAHRENSSCHRITT=Auswertung Öffentlichkeit
 * - a single quoted name:           VERFAHRENSSCHRITT="Auswertung Öffentlichkeit"
 * - a list in parentheses:          VERFAHRENSSCHRITT=("Beteiligung Öffentlichkeit","Auswertung Öffentlichkeit")
 * - a comma separated list:         VERFAHRENSSCHRITT=A,B (a name containing a comma has to be quoted)
 * - the array notation:             VERFAHRENSSCHRITT[]=A&VERFAHRENSSCHRITT[]=B
 *
 * A repeated parameter without brackets can not be supported, as only the last value reaches PHP.
 * Names are compared ignoring case and surplus whitespace.
 */
class MeinBerlinRssPhaseFilter
{
    public const PARAMETER_NAME = 'VERFAHRENSSCHRITT';

    /**
     * Picks the filter parameter out of the query parameters of a request, ignoring the case of its name.
     *
     * @param array<string, mixed> $query
     */
    public function getRawValue(array $query): mixed
    {
        foreach ($query as $name => $value) {
            if (self::PARAMETER_NAME === strtoupper((string) $name)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<string> normalized phase names, an empty list means that no filter is set
     */
    public function parse(mixed $rawValue): array
    {
        $names = is_array($rawValue) ? $this->parseArray($rawValue) : $this->parseString($rawValue);
        $normalizedNames = array_map($this->normalize(...), $names);

        return array_values(array_unique(array_filter(
            $normalizedNames,
            static fn (string $name): bool => '' !== $name
        )));
    }

    /**
     * @param list<string> $normalizedNames result of {@link self::parse()}
     */
    public function matches(string $phaseName, array $normalizedNames): bool
    {
        return in_array($this->normalize($phaseName), $normalizedNames, true);
    }

    /**
     * @return list<string>
     */
    private function parseString(mixed $value): array
    {
        if (!is_string($value)) {
            return [];
        }
        $value = trim($value);
        if (str_starts_with($value, '(') && str_ends_with($value, ')')) {
            $value = substr($value, 1, -1);
        }

        // splitting like a csv line keeps commas inside quoted names
        $names = [];
        foreach (str_getcsv($value, ',', '"', '') as $name) {
            $names[] = trim((string) $name);
        }

        return $names;
    }

    /**
     * Every element is a name of its own, so commas inside of it belong to the name.
     *
     * @param array<array-key, mixed> $values
     * @return list<string>
     */
    private function parseArray(array $values): array
    {
        $names = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $value = trim($value);
            if (2 <= strlen($value) && str_starts_with($value, '"') && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }
            $names[] = $value;
        }

        return $names;
    }

    private function normalize(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return mb_strtolower(trim($collapsed));
    }
}
