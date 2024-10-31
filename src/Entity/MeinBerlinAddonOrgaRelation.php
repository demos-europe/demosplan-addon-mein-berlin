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

use DemosEurope\DemosplanAddon\DemosMeinBerlin\Doctrine\Generator\UuidV4Generator;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use Doctrine\ORM\Mapping as ORM;
#[ORM\Entity(repositoryClass: MeinBerlinAddonOrgaRelationRepository::class)]
class MeinBerlinAddonOrgaRelation
{
    #[ORM\Column(type: 'string', length: 36, nullable: false, options:['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'orga_id', type: 'string', length: 36, nullable: false, options:['fixed' => true])]
    private string $orgaId = '';

    #[ORM\Column(name: 'mein_berlin_organisation_id', length: 255, type: 'string', nullable: false)]
    private string $meinBerlinOrganisationId = '';

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }
    public function getOrgaId(): string
    {
        return $this->orgaId;
    }

    public function setOrgaId(string $orgaId): void
    {
        $this->orgaId = $orgaId;
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
