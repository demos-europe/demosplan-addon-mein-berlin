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
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @template-extends ServiceEntityRepository<MeinBerlinAddonEntity>
 */
class MeinBerlinAddonEntityRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        string $entityClass,
    ){
        parent::__construct($registry, $entityClass);
    }

    public function getByProceduerId(string $procedureId): ?MeinBerlinAddonEntity
    {
        return $this->findOneBy(['procedure' => $procedureId]);
    }

    public function persistMeinBerlinAddonEntity(MeinBerlinAddonEntity $meinBerlinAddonEntity): void
    {
        $this->getEntityManager()->persist($meinBerlinAddonEntity);
    }
}
