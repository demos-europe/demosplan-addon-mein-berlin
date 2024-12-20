<?php
declare(strict_types=1);
/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Utilities\Reindexer;

/**
 * @template-extends FluentRepository<MeinBerlinAddonOrgaRelation>
 */
class MeinBerlinAddonOrgaRelationRepository extends FluentRepository
{
    /**
     * @param Reindexer<ClauseFunctionInterface<bool>, OrderBySortMethodInterface> $reindexer
     */
    public function __construct(
        DqlConditionFactory $conditionFactory,
        ManagerRegistry $registry,
        SortMethodFactory $sortMethodFactory,
        string $entityClass,
        Reindexer $reindexer
    ) {
        parent::__construct($conditionFactory, $registry, $reindexer, $sortMethodFactory, $entityClass);
    }

    public function getByOrgaId(string $orgaId): ?MeinBerlinAddonOrgaRelation
    {
        return $this->findOneBy(['orga' => $orgaId]);
    }

    public function persistMeinBerlinAddonOrgaRelation(MeinBerlinAddonOrgaRelation $meinBerlinAddonOrgaRelation): void
    {
        $this->getEntityManager()->persist($meinBerlinAddonOrgaRelation);
    }

    public function getOrganisationById(string $organisationId): ?OrgaInterface
    {
        return $this->getEntityManager()->getRepository(OrgaInterface::class)->find($organisationId);
    }

    /**
     * This method is used to determine if a meinBerlin organisation id is allowed to be updated.
     * It will return all already to meinBerlin communicated addonEntities of an organisation using the existing
     * meinBerlin organisation id. If the old organisation id is in use already, an update should be prohibited.
     * @return MeinBerlinAddonEntity[]
     */
    public function getProceduresOfOrgaWithExistingDplanId(MeinBerlinAddonOrgaRelation $orgaRelation): array
    {
        $orgaId = $orgaRelation->getOrga()?->getId();
        $procedureRepository = $this->getEntityManager()->getRepository(ProcedureInterface::class);
        $proceduresOfOrga = $procedureRepository->findBy(['orga' => $orgaId, 'deleted' => false]);
        $proceduresOfOrga = array_map(
            static fn(ProcedureInterface $procedure) => $procedure->getId(),
            $proceduresOfOrga
        );
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        return $queryBuilder->select('addonEntity')
            ->from(MeinBerlinAddonEntity::class, 'addonEntity')
            ->where('addonEntity.procedure IN (:procedureIds)')
            ->andWhere('addonEntity.dplanId != :emptyDplanId')
            ->setParameter('procedureIds', $proceduresOfOrga)
            ->setParameter('emptyDplanId', '')
            ->getQuery()
            ->getResult();
    }
}
