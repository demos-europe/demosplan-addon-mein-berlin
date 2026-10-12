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

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Doctrine\Generator\UuidV4Generator;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonDistrictRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Berlin district and the mein.berlin.de organisation ID that belongs to it.
 *
 * This is the catalog the organisation dropdown is built from. The organisation ID saved for an
 * organisation ({@link MeinBerlinAddonOrgaRelation}) is a copy of its own and is not changed when this
 * catalog is edited.
 */
#[ORM\Entity(repositoryClass: MeinBerlinAddonDistrictRepository::class)]
#[ORM\Table(name: 'addon_mein_berlin_district')]
#[ORM\UniqueConstraint(name: 'unique_district_code', columns: ['district_code'])]
#[ORM\UniqueConstraint(name: 'unique_district_organisation_id', columns: ['mein_berlin_organisation_id'])]
class MeinBerlinAddonDistrict implements UuidEntityInterface
{
    #[ORM\Column(type: 'string', length: 36, nullable: false, options: ['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    private ?string $id = null;

    /**
     * Same two character code as {@link MeinBerlinAddonEntity::getDistrict()}.
     */
    #[ORM\Column(name: 'district_code', type: 'string', length: 2, nullable: false)]
    private string $districtCode = '';

    #[ORM\Column(name: 'name', type: 'string', length: 255, nullable: false)]
    private string $name = '';

    #[ORM\Column(name: 'mein_berlin_organisation_id', type: 'string', length: 255, nullable: true)]
    private ?string $meinBerlinOrganisationId = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getDistrictCode(): string
    {
        return $this->districtCode;
    }

    public function setDistrictCode(string $districtCode): void
    {
        $this->districtCode = $districtCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getMeinBerlinOrganisationId(): ?string
    {
        return $this->meinBerlinOrganisationId;
    }

    public function setMeinBerlinOrganisationId(?string $meinBerlinOrganisationId): void
    {
        $this->meinBerlinOrganisationId = $meinBerlinOrganisationId;
    }
}
