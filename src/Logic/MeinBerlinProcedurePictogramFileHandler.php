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

use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use Exception;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToReadFile;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use function base64_encode;

class MeinBerlinProcedurePictogramFileHandler
{
    public function __construct(
        private readonly FileServiceInterface $fileService,
        private readonly LoggerInterface $logger,
        private readonly FilesystemOperator $defaultStorage,
        private readonly ParameterBagInterface $parameterBag,
        private readonly MessageBagInterface $messageBag,
    ) {

    }
    public function checkForPictogramAndGetBase64FileString(string $pictogramFileString): string
    {
        $base64FileString = '';
        if ('' !== $pictogramFileString && null !== $pictogramFileString) {
            try {
                $pictogram = $this->fileService->getFileInfoFromFileString($pictogramFileString);
                $this->logger->info(
                    'demosplan-mein-berlin-addon found Pictogram to use - converting file contents to base64',
                    [$pictogram->getFileName(), $pictogram->getPath()]
                );
                if ($this->defaultStorage->fileExists($pictogram->getPath())) {
                    $fileSize = $this->defaultStorage->fileSize($pictogram->getPath());
                    if ((int) $this->parameterBag->get('mein_berlin_pictogram_max_file_size') <= $fileSize) {
                        $this->logger->error(
                            'demosplan-mein-berlin-addon could not append pictogram base64 file
                             to the procedure message as the allowed max size was exceeded',
                            [
                                'Max-allowed' => $this->parameterBag->get('mein_berlin_pictogram_max_file_size'),
                                'Got-size' => $fileSize
                            ]
                        );
                        $this->messageBag->add('error', 'mein.berlin.pictogram.file.to.large');

                        return $base64FileString;
                    }
                    $base64FileString = base64_encode(
                        $this->defaultStorage->read($pictogram->getPath())
                    );
                }
            } catch (FilesystemException|UnableToReadFile|Exception $e) {
                $this->logger->error(
                    'demosplan-mein-berlin-addon failed to load/convert the pictogram to base64 string',
                    [$e]
                );
            }
        }

        return $base64FileString;
    }
}
