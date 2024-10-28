<?php

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Doctrine\Generator\UuidV4Generator;

/**
 * @ORM\Table(name="mein_berlin_addon")
 *
 */
class MeinBerlinAddonEntity implements UuidEntityInterface
{
    #[ORM\Column(type: 'string', length: 36, nullable: false, options:['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    private ?string $id = null;

    #[ORM\Column(name: 'procedure_id', length: 36, type: 'string', nullable: false)]
    private string $procedure_id;

    #[ORM\Column(name: 'organisation_id', length: 255, type: 'string', nullable: false)]
    private string $organisation_id;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
    }

    public function getProcedureId(): string
    {
        return $this->procedure_id;
    }

    public function setProcedureId($procedureId)
    {
        $this->procedure_id = $procedureId;
    }

    public function getOrganisationId(): string
    {
        return $this->organisation_id;
    }

    public function setOrganisationId($organisationId)
    {
        $this->organisation_id = $organisationId;
    }

}
