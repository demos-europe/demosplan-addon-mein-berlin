<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum;

use function in_array;
trait CommonEnumMethods
{
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
                $changedProperties[$propertyName] = $propertyValue;
            }
        }

        return $changedProperties;
    }

    public static function getValues(): array
    {
        return array_map(static fn($case) => $case->value, self::cases());
    }

    public static function hasValue(string $value): bool
    {
        return in_array($value, self::getValues(), true);
    }

    public static function getNameFromValue(string $value): ?string
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case->name;
            }
        }
        return null;
    }

    public static function mapToCommunicationNamesIfValuesExist(array $changeSet): array
    {
        $replacedKeysByEnumNameArray = [];
        foreach (self::cases() as $case) {
            if (array_key_exists($case->value, $changeSet)) {
                $replacedKeysByEnumNameArray[$case->name] = $changeSet[$case->value];
            }
        }

        return $replacedKeysByEnumNameArray;
    }
}
