<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Doctrine\Generator\UuidV4Generator;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MeinBerlinAddonOrgaRelationRepository::class)]
#[ORM\Table(name: 'addon_mein_berlin_orga_relation')]
class MeinBerlinAddonOrgaRelation implements UuidEntityInterface
{
    #[ORM\Column(type: 'string', length: 36, nullable: false, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    private ?string $id = null;

    #[ORM\OneToOne(targetEntity: OrgaInterface::class)]
    #[ORM\JoinColumn(name: '_orga_id', referencedColumnName: '_o_id', nullable: false)]
    private ?OrgaInterface $orga = null;

    #[ORM\Column(name: 'mein_berlin_organisation_id', type: 'string', length: 255, nullable: false)]
    private string $meinBerlinOrganisationId = '';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }
    public function getOrga(): ?OrgaInterface
    {
        return $this->orga;
    }

    public function setOrga(OrgaInterface $orga): void
    {
        $this->orga = $orga;
    }
    public function getMeinBerlinOrganisationId(): string
    {
        return $this->meinBerlinOrganisationId;
    }

    public function setMeinBerlinOrganisationId(string $meinBerlinOrganisationId): void
    {
        $this->meinBerlinOrganisationId = $meinBerlinOrganisationId;
    }

}
