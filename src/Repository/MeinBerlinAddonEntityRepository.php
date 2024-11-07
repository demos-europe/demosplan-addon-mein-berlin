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


use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Utilities\Reindexer;

/**
 * @template-extends FluentRepository<MeinBerlinAddonEntity>
 */
class MeinBerlinAddonEntityRepository extends FluentRepository
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

    public function getByProceduerId(string $procedureId): ?MeinBerlinAddonEntity
    {
        return $this->findOneBy(['procedure' => $procedureId]);
    }

    public function persistMeinBerlinAddonEntity(MeinBerlinAddonEntity $meinBerlinAddonEntity): void
    {
        $this->getEntityManager()->persist($meinBerlinAddonEntity);
    }

    public function flushEverything(): void
    {
        $this->getEntityManager()->flush();
    }
}
