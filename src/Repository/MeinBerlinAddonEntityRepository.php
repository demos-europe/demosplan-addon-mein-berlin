<?php

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
        return $this->findOneBy(['procedureId' => $procedureId]);
    }
}
