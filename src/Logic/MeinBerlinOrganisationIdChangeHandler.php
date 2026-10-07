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

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use Psr\Log\LoggerInterface;

/**
 * A bplanId is only valid together with the mein.berlin organisation id the procedure was created under.
 * This handler takes care of the procedures that were already communicated when that id is changed or reset.
 */
class MeinBerlinOrganisationIdChangeHandler
{
    /**
     * Maximum number of procedure names that are returned to be shown to the user.
     */
    private const NAMES_LIMIT = 10;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MeinBerlinAddonOrgaRelationRepository $orgaRelationRepository,
    ) {
    }

    /**
     * @return array{count: int, names: list<string>} the already communicated procedures of the organisation
     */
    public function getCommunicatedProceduresInfo(MeinBerlinAddonOrgaRelation $orgaRelation): array
    {
        $count = $this->orgaRelationRepository->countProceduresOfOrgaWithExistingBplanId($orgaRelation);
        if (0 === $count) {
            return ['count' => 0, 'names' => []];
        }

        return [
            'count' => $count,
            'names' => $this->getProcedureNames(
                $this->orgaRelationRepository->getProceduresOfOrgaWithExistingBplanId($orgaRelation, self::NAMES_LIMIT)
            ),
        ];
    }

    /**
     * @return array{count: int, names: list<string>} the already communicated procedures of all organisations
     *                                                that use the organisation id
     */
    public function getCommunicatedProceduresInfoOfOrganisationId(string $meinBerlinOrganisationId): array
    {
        $count = $this->orgaRelationRepository->countProceduresOfOrganisationIdWithExistingBplanId(
            $meinBerlinOrganisationId
        );
        if (0 === $count) {
            return ['count' => 0, 'names' => []];
        }

        return [
            'count' => $count,
            'names' => $this->getProcedureNames(
                $this->orgaRelationRepository->getProceduresOfOrganisationIdWithExistingBplanId(
                    $meinBerlinOrganisationId,
                    self::NAMES_LIMIT
                )
            ),
        ];
    }

    /**
     * Gives all organisations that use the organisation id of a district the new id, e.g. because the id of the
     * district was corrected. Their already communicated procedures are released, see
     * {@link releaseCommunicatedProcedures()}. The changes are not flushed, this is up to the caller.
     *
     * @return array{organisations: int, procedures: int} the number of changed organisations and released procedures
     */
    public function changeOrganisationIdOfOrganisations(string $oldOrganisationId, string $newOrganisationId): array
    {
        $result = ['organisations' => 0, 'procedures' => 0];
        if ($oldOrganisationId === $newOrganisationId) {
            return $result;
        }

        $orgaRelations = $this->orgaRelationRepository->findBy(['meinBerlinOrganisationId' => $oldOrganisationId]);
        foreach ($orgaRelations as $orgaRelation) {
            $result['procedures'] += $this->releaseCommunicatedProcedures($orgaRelation, $newOrganisationId);
            $orgaRelation->setMeinBerlinOrganisationId($newOrganisationId);
            ++$result['organisations'];
        }

        return $result;
    }

    /**
     * Releases the already communicated procedures of the organisation, if its organisation id is going to change.
     * Their bplanId is cleared, so they are created again with the new organisation id the next time they are
     * saved. The entry at meinBerlin that belongs to the old organisation id is not touched and has to be
     * updated or deleted manually in the meinBerlin dashboard, therefore the old values are logged.
     *
     * The changes are not flushed, this is up to the caller.
     *
     * @return int the number of released procedures
     */
    public function releaseCommunicatedProcedures(
        MeinBerlinAddonOrgaRelation $orgaRelation,
        string $newOrganisationId
    ): int {
        $oldOrganisationId = $orgaRelation->getMeinBerlinOrganisationId();
        if ($oldOrganisationId === $newOrganisationId) {
            return 0;
        }

        $addonEntities = $this->orgaRelationRepository->getProceduresOfOrgaWithExistingBplanId($orgaRelation);
        foreach ($addonEntities as $addonEntity) {
            $this->logger->info(
                'demosplan-mein-berlin-addon releases a procedure that was communicated to meinBerlin '
                .'because the meinBerlin organisation id changes. The entry at meinBerlin has to be '
                .'updated or deleted manually.',
                [
                    'procedureId' => $addonEntity->getProcedure()?->getId(),
                    'oldMeinBerlinOrganisationId' => $oldOrganisationId,
                    'newMeinBerlinOrganisationId' => $newOrganisationId,
                    'oldBplanId' => $addonEntity->getBplanId(),
                ]
            );
            $addonEntity->setBplanId('');
        }

        return count($addonEntities);
    }

    /**
     * @param MeinBerlinAddonEntity[] $addonEntities
     *
     * @return list<string>
     */
    private function getProcedureNames(array $addonEntities): array
    {
        return array_values(array_map(
            static fn (MeinBerlinAddonEntity $addonEntity): string => (string) $addonEntity->getProcedure()?->getExternalName(),
            $addonEntities
        ));
    }
}
