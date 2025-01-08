<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic;

use DemosEurope\DemosplanAddon\Contracts\Services\MapProjectionConverterInterface;
use JsonException;
use Psr\Log\LoggerInterface;
use const JSON_THROW_ON_ERROR;

class MeinBerlinProcedureSettingsCoordinateHandler
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MapProjectionConverterInterface $mapProjectionConverter,
    ) {

    }

    public function getCoordinateAsGeoJSON(?string $coordinate): string
    {
        if (null === $coordinate || '' === $coordinate) {
            return '';
        }

        $coordinate4326 = $this->mapProjectionConverter->convertCoordinate(
            $coordinate,
            $this->mapProjectionConverter->getProjection('EPSG:3857'),
            $this->mapProjectionConverter->getProjection('EPSG:4326')
        );
        $geoJson = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [
                    $coordinate4326[0],
                    $coordinate4326[1],
                ],
            ],
        ];

        try {
            return json_encode($geoJson, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->logger->error('failed to convert the coordinate to geojson', [$e]);
        }

        return '';
    }
}
