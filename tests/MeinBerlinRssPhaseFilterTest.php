<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Tests;

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Service\MeinBerlinRssPhaseFilter;
use PHPUnit\Framework\TestCase;

class MeinBerlinRssPhaseFilterTest extends TestCase
{
    private MeinBerlinRssPhaseFilter|null $sut = null;

    protected function setUp(): void
    {
        $this->sut = new MeinBerlinRssPhaseFilter();
    }

    /**
     * @dataProvider parseProvider
     *
     * @param list<string> $expected
     */
    public function testParse(mixed $rawValue, array $expected): void
    {
        self::assertSame($expected, $this->sut->parse($rawValue));
    }

    /**
     * @return array<string, array{mixed, list<string>}>
     */
    public function parseProvider(): array
    {
        return [
            'not set' => [null, []],
            'empty string' => ['', []],
            'only whitespace' => ['   ', []],
            'empty list' => ['()', []],
            'single name' => ['Auswertung Öffentlichkeit', ['auswertung öffentlichkeit']],
            'single quoted name' => ['"Auswertung Öffentlichkeit"', ['auswertung öffentlichkeit']],
            'list syntax of the ticket' => [
                '("Beteiligung Öffentlichkeit","Frühzeitige Beteiligung Öffentlichkeit - § 3 (1) BauGB")',
                ['beteiligung öffentlichkeit', 'frühzeitige beteiligung öffentlichkeit - § 3 (1) baugb'],
            ],
            'list with spaces after the comma' => ['("A", "B")', ['a', 'b']],
            'comma separated list' => ['A,B', ['a', 'b']],
            'comma inside a quoted name' => ['"Hallo, Welt",B', ['hallo, welt', 'b']],
            'name with parentheses is not a list' => [
                'Frühzeitige Beteiligung Öffentlichkeit - § 3 (1) BauGB',
                ['frühzeitige beteiligung öffentlichkeit - § 3 (1) baugb'],
            ],
            'surplus whitespace' => ["  Auswertung \t  Öffentlichkeit ", ['auswertung öffentlichkeit']],
            'duplicates are removed' => ['"A","a"', ['a']],
            'array notation' => [['Beteiligung Öffentlichkeit', 'Auswertung Öffentlichkeit'], ['beteiligung öffentlichkeit', 'auswertung öffentlichkeit']],
            'array notation keeps commas of a name' => [['Hallo, Welt'], ['hallo, welt']],
            'array notation with quoted names' => [['"A"', '"B"'], ['a', 'b']],
            'array notation ignores empty and non string elements' => [['', ['nested'], 'A'], ['a']],
            'other types are ignored' => [42, []],
        ];
    }

    public function testMatchesIgnoresCaseAndWhitespace(): void
    {
        $names = $this->sut->parse('"Auswertung Öffentlichkeit"');

        self::assertTrue($this->sut->matches('Auswertung Öffentlichkeit', $names));
        self::assertTrue($this->sut->matches('  AUSWERTUNG   öffentlichkeit ', $names));
        self::assertFalse($this->sut->matches('Beteiligung Öffentlichkeit', $names));
    }

    public function testMatchesNeedsTheWholeName(): void
    {
        $names = $this->sut->parse('Beteiligung Öffentlichkeit');

        self::assertFalse($this->sut->matches('Frühzeitige Beteiligung Öffentlichkeit', $names));
    }

    public function testMatchesNothingWithoutNames(): void
    {
        self::assertFalse($this->sut->matches('Auswertung Öffentlichkeit', []));
    }

    /**
     * @dataProvider rawValueProvider
     *
     * @param array<string, mixed> $query
     */
    public function testGetRawValueIgnoresTheCaseOfTheParameterName(array $query, mixed $expected): void
    {
        self::assertSame($expected, $this->sut->getRawValue($query));
    }

    /**
     * @return array<string, array{array<string, mixed>, mixed}>
     */
    public function rawValueProvider(): array
    {
        return [
            'upper case' => [['VERFAHRENSSCHRITT' => 'A'], 'A'],
            'lower case' => [['verfahrensschritt' => 'A'], 'A'],
            'mixed case' => [['Verfahrensschritt' => 'A'], 'A'],
            'array value' => [['VERFAHRENSSCHRITT' => ['A', 'B']], ['A', 'B']],
            'next to other parameters' => [['foo' => 'bar', 'VERFAHRENSSCHRITT' => 'A'], 'A'],
            'missing' => [['foo' => 'bar'], null],
            'no parameters' => [[], null],
        ];
    }
}
