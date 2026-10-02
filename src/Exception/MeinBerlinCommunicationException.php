<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception;

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\MeinBerlinCommunicationErrorCategory;
use Exception;
use Throwable;
use function mb_substr;

class MeinBerlinCommunicationException extends Exception
{
    public const MAX_RESPONSE_BODY_LENGTH = 1000;

    private readonly ?string $responseBody;

    /**
     * Never pass request headers (authorization) or the request payload (base64 image) as details.
     */
    public function __construct(
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
        private readonly MeinBerlinCommunicationErrorCategory $category = MeinBerlinCommunicationErrorCategory::unknown,
        private readonly ?int $httpStatus = null,
        ?string $responseBody = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->responseBody = null === $responseBody
            ? null
            : mb_substr($responseBody, 0, self::MAX_RESPONSE_BODY_LENGTH);
    }

    public function getCategory(): MeinBerlinCommunicationErrorCategory
    {
        return $this->category;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Truncated to {@link self::MAX_RESPONSE_BODY_LENGTH} characters.
     */
    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
