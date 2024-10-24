<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\EventListener;

use DemosEurope\DemosplanAddon\Contracts\Events\PostProcedureUpdatedEventInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MeinBerlinPostProcedureUpdatedEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
    ) {

    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostProcedureUpdatedEventInterface::class => 'onProcedureUpdate',
        ];
    }

    public function onProcedureUpdate(PostProcedureUpdatedEventInterface $postProcedureUpdatedEvent): void
    {
        $oldProcedure = $postProcedureUpdatedEvent->getProcedureBeforeUpdate();
        $oldPhase = $oldProcedure->getPublicParticipationPhaseObject();
        $oldPhaseName = $oldPhase->getName();
        $opPhase = $oldProcedure->getPublicParticipationPhase();

        $newProcedure = $postProcedureUpdatedEvent->getProcedureAfterUpdate();
        $newPhase = $newProcedure->getPublicParticipationPhaseObject();
        $newPhaseName = $newPhase->getName();
        $pPhase = $oldProcedure->getPhase();
        $npPhase = $newProcedure->getPublicParticipationPhase();

        $changeSet = $postProcedureUpdatedEvent->getModifiedValues();

        $test = 5;
    }
}
