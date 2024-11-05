<?php
declare(strict_types=1);
/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

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
        return $this->findOneBy(['orga' => $orgaId]);
    }

    public function persistMeinBerlinAddonOrgaRelation(MeinBerlinAddonOrgaRelation $meinBerlinAddonOrgaRelation): void
    {
        $this->getEntityManager()->persist($meinBerlinAddonOrgaRelation);
    }
}
