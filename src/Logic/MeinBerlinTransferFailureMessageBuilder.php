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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\MeinBerlinTransferType;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use function preg_replace;
use function trim;

/**
 * Builds subject and body of the mail sent when a procedure could not be transferred to mein.berlin.de.
 * The texts are translations, so no mail template has to exist in the database.
 */
class MeinBerlinTransferFailureMessageBuilder
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getSubject(ProcedureInterface $procedure, MeinBerlinTransferType $type): string
    {
        return $this->translator->trans(
            'mein.berlin.failure.mail.subject.'.$type->value,
            ['procedureName' => $procedure->getName()]
        );
    }

    public function getBody(
        ProcedureInterface $procedure,
        MeinBerlinCommunicationException $exception,
        MeinBerlinTransferType $type
    ): string {
        $body = $this->translator->trans(
            'mein.berlin.failure.mail.body',
            [
                'intro' => $this->translator->trans(
                    'mein.berlin.failure.mail.intro.'.$type->value,
                    ['procedureName' => $procedure->getName()]
                ),
                'reason' => $this->translator->trans(
                    'mein.berlin.failure.mail.reason.'.$exception->getCategory()->value
                ),
                'contact' => $this->getContact(),
                'details' => $this->getTechnicalDetails($exception),
            ]
        );

        // line breaks as in the other mails of the core
        return preg_replace('/\r?\n/', "\r\n", $body);
    }

    private function getContact(): string
    {
        $supportAddress = trim((string) $this->parameterBag->get('mein_berlin_failure_mail_support_address'));
        if ('' === $supportAddress) {
            return $this->translator->trans('mein.berlin.failure.mail.contact.generic');
        }

        return $this->translator->trans(
            'mein.berlin.failure.mail.contact.address',
            ['supportAddress' => $supportAddress]
        );
    }

    /**
     * Technical details, meant to be forwarded to the support.
     */
    private function getTechnicalDetails(MeinBerlinCommunicationException $exception): string
    {
        $details = [
            $this->translator->trans(
                'mein.berlin.failure.mail.details.category',
                ['category' => $exception->getCategory()->value]
            ),
        ];
        if (null !== $exception->getHttpStatus()) {
            $details[] = $this->translator->trans(
                'mein.berlin.failure.mail.details.status',
                ['status' => (string) $exception->getHttpStatus()]
            );
        }
        $details[] = $this->translator->trans(
            'mein.berlin.failure.mail.details.message',
            ['message' => $exception->getMessage()]
        );
        $responseBody = $exception->getResponseBody();
        if (null !== $responseBody && '' !== $responseBody) {
            $details[] = $this->translator->trans(
                'mein.berlin.failure.mail.details.response',
                ['response' => $responseBody]
            );
        }

        return implode("\r\n", $details);
    }
}
