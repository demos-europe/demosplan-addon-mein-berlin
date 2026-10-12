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
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
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
     * Returns the addonEntities of the not deleted procedures of the organisation that were already
     * communicated to meinBerlin, i.e. that have a bplanId. Those procedures belong to the meinBerlin
     * organisation id the organisation currently has.
     *
     * @return MeinBerlinAddonEntity[]
     */
    public function getProceduresOfOrgaWithExistingBplanId(
        MeinBerlinAddonOrgaRelation $orgaRelation,
        ?int $limit = null
    ): array {
        return $this->createCommunicatedProceduresQueryBuilder($orgaRelation)
            ->select('addonEntity')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countProceduresOfOrgaWithExistingBplanId(MeinBerlinAddonOrgaRelation $orgaRelation): int
    {
        return (int) $this->createCommunicatedProceduresQueryBuilder($orgaRelation)
            ->select('COUNT(addonEntity.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Same as {@link getProceduresOfOrgaWithExistingBplanId()} for all organisations that use the given
     * meinBerlin organisation id.
     *
     * @return MeinBerlinAddonEntity[]
     */
    public function getProceduresOfOrganisationIdWithExistingBplanId(
        string $meinBerlinOrganisationId,
        ?int $limit = null
    ): array {
        return $this->createCommunicatedProceduresOfOrganisationIdQueryBuilder($meinBerlinOrganisationId)
            ->select('addonEntity')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countProceduresOfOrganisationIdWithExistingBplanId(string $meinBerlinOrganisationId): int
    {
        return (int) $this->createCommunicatedProceduresOfOrganisationIdQueryBuilder($meinBerlinOrganisationId)
            ->select('COUNT(addonEntity.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createCommunicatedProceduresOfOrganisationIdQueryBuilder(
        string $meinBerlinOrganisationId
    ): QueryBuilder {
        return $this->getEntityManager()->createQueryBuilder()
            ->from(MeinBerlinAddonEntity::class, 'addonEntity')
            ->join('addonEntity.procedure', 'procedure')
            ->join(MeinBerlinAddonOrgaRelation::class, 'relation', Join::WITH, 'relation.orga = procedure.orga')
            ->where('relation.meinBerlinOrganisationId = :meinBerlinOrganisationId')
            ->andWhere('procedure.deleted = :deleted')
            ->andWhere('addonEntity.bplanId != :emptyBplanId')
            ->setParameter('meinBerlinOrganisationId', $meinBerlinOrganisationId)
            ->setParameter('deleted', false)
            ->setParameter('emptyBplanId', '');
    }

    private function createCommunicatedProceduresQueryBuilder(
        MeinBerlinAddonOrgaRelation $orgaRelation
    ): QueryBuilder {
        return $this->getEntityManager()->createQueryBuilder()
            ->from(MeinBerlinAddonEntity::class, 'addonEntity')
            ->join('addonEntity.procedure', 'procedure')
            ->where('procedure.orga = :orgaId')
            ->andWhere('procedure.deleted = :deleted')
            ->andWhere('addonEntity.bplanId != :emptyBplanId')
            ->setParameter('orgaId', $orgaRelation->getOrga()?->getId())
            ->setParameter('deleted', false)
            ->setParameter('emptyBplanId', '');
    }
}
