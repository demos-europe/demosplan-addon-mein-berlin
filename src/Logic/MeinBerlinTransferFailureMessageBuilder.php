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
use function htmlspecialchars;
use function preg_replace;
use function strtr;
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
        return $this->neutralizeReplacementSyntax($this->translator->trans(
            'mein.berlin.failure.mail.subject.'.$type->value,
            ['procedureName' => $procedure->getName()]
        ));
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
                    ['procedureName' => $this->escapeHtml((string) $procedure->getName())]
                ),
                'reason' => $this->translator->trans(
                    'mein.berlin.failure.mail.reason.'.$exception->getCategory()->value
                ),
                'contact' => $this->getContact(),
                'details' => $this->getTechnicalDetails($exception),
            ]
        );

        // line breaks as in the other mails of the core
        return $this->neutralizeReplacementSyntax(preg_replace('/\r?\n/', "\r\n", $body));
    }

    private function getContact(): string
    {
        $supportAddress = trim((string) $this->parameterBag->get('mein_berlin_failure_mail_support_address'));
        if ('' === $supportAddress) {
            return $this->translator->trans('mein.berlin.failure.mail.contact.generic');
        }

        return $this->translator->trans(
            'mein.berlin.failure.mail.contact.address',
            ['supportAddress' => $this->escapeHtml($supportAddress)]
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
            ['message' => $this->escapeHtml($exception->getMessage())]
        );
        $responseBody = $exception->getResponseBody();
        if (null !== $responseBody && '' !== $responseBody) {
            $details[] = $this->translator->trans(
                'mein.berlin.failure.mail.details.response',
                ['response' => $this->escapeHtml($responseBody)]
            );
        }

        return implode("\r\n", $details);
    }

    /**
     * The core sends the mail as HTML without escaping, so text of external origin (e.g. an HTML error page of
     * the server) would be rendered instead of shown. Quotes stay as they are to keep JSON readable.
     */
    private function escapeHtml(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * The core inserts the text via preg_replace as replacement string, where "$1", "${1}" and "\1" are
     * back references. Escaping them makes them appear literally.
     */
    private function neutralizeReplacementSyntax(string $text): string
    {
        return strtr($text, ['\\' => '\\\\', '$' => '\\$']);
    }
}
