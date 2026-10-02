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
use DemosEurope\DemosplanAddon\Contracts\Services\MailServiceInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Enum\MeinBerlinTransferType;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Throwable;
use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function is_string;
use function trim;

/**
 * Tells the responsible planning agency by mail that a procedure (or a change of it) could not be transferred to
 * mein.berlin.de.
 *
 * mein.berlin.de sends a mail itself when a procedure was created successfully, hence only failures are mailed.
 */
class MeinBerlinTransferFailureNotifier
{
    // generic template of the core, consisting of nothing but the subject and body passed as variables
    private const MAIL_TEMPLATE = 'dm_schlussmitteilung';
    private const MAIL_LANGUAGE = 'de_DE';
    private const MAIL_SCOPE = 'extern';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MailServiceInterface $mailService,
        private readonly MeinBerlinTransferFailureMessageBuilder $messageBuilder,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    /**
     * Never throws: a problem while notifying must not break saving the procedure.
     */
    public function notifyAboutFailedTransfer(
        ProcedureInterface $procedure,
        MeinBerlinCommunicationException $exception,
        MeinBerlinTransferType $type
    ): void {
        $logContext = [
            'procedureId' => $procedure->getId(),
            'transferType' => $type->value,
            'errorCategory' => $exception->getCategory()->value,
            'httpStatus' => $exception->getHttpStatus(),
        ];

        try {
            $recipient = $procedure->getAgencyMainEmailAddress();
            if (!is_string($recipient) || '' === trim($recipient)) {
                $this->logger->warning(
                    'demosplan-mein-berlin-addon transfer to MeinBerlin failed - no failure mail sent: '
                    .'the procedure has no agency main email address',
                    $logContext
                );

                return;
            }

            $this->mailService->sendMail(
                self::MAIL_TEMPLATE,
                self::MAIL_LANGUAGE,
                $recipient,
                '',
                $this->getCarbonCopyRecipients(),
                '',
                self::MAIL_SCOPE,
                [
                    'mailsubject' => $this->messageBuilder->getSubject($procedure, $type),
                    'mailbody' => $this->messageBuilder->getBody($procedure, $exception, $type),
                ]
            );
            $this->logger->info(
                'demosplan-mein-berlin-addon transfer to MeinBerlin failed - failure mail queued',
                $logContext
            );
        } catch (Throwable $e) {
            $this->logger->error(
                'demosplan-mein-berlin-addon transfer to MeinBerlin failed - failure mail could not be queued',
                $logContext + ['Exception' => $e, 'ExceptionMessage' => $e->getMessage()]
            );
        }
    }

    /**
     * @return list<string>
     */
    private function getCarbonCopyRecipients(): array
    {
        // comma separated, null if the environment variable is not set
        $recipients = explode(',', (string) $this->parameterBag->get('mein_berlin_failure_mail_cc'));

        return array_values(array_filter(array_map('trim', $recipients)));
    }
}
