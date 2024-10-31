<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository;

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @template-extends ServiceEntityRepository<MeinBerlinAddonOrgaRelation>
 */
class MeinBerlinAddonOrgaRelationRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        string $entityClass,
    ){
        parent::__construct($registry, $entityClass);
    }

    public function getByOrgaId(string $orgaId): ?MeinBerlinAddonOrgaRelation
    {
        return $this->findOneBy(['orgaId' => $orgaId]);
    }
}
