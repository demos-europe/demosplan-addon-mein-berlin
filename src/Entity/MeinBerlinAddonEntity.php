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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Doctrine\Generator\UuidV4Generator;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonEntityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MeinBerlinAddonEntityRepository::class)]
#[ORM\Table(name: 'addon_mein_berlin_entity')]
class MeinBerlinAddonEntity implements UuidEntityInterface
{
    public const MEIN_BERLIN_PROCEDURE_SHORT_NAME = 'bplan_id';
    public const MEIN_BERLIN_IS_DRAFT = 'is_draft';

    #[ORM\Column(type: 'string', length: 36, nullable: false, options:['fixed' => true])]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidV4Generator::class)]
    private ?string $id = null;

    #[ORM\OneToOne(targetEntity: ProcedureInterface::class)]
    #[ORM\JoinColumn(name: 'procedure_id', referencedColumnName: '_p_id', nullable: false)]
    private ?ProcedureInterface $procedure = null;

    #[ORM\Column(name: 'dplan_id', length: 255, type: 'string', nullable: false)]
    private string $dplanId = '';

    #[ORM\Column(name: 'procedure_short_name', length: 255, type: 'string', nullable: false)]
    private string $procedureShortName = '';

    #[ORM\Column(name: 'is_interface_activated', type: 'boolean', nullable: false, options: ['default' => false])]
    private bool $isInterfaceActivated = false;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getProcedure(): ?ProcedureInterface
    {
        return $this->procedure;
    }

    public function setProcedure(ProcedureInterface $procedure): void
    {
        $this->procedure = $procedure;
    }

    public function getDplanId(): string
    {
        return $this->dplanId;
    }

    public function setDplanId(string $dplanId): void
    {
        $this->dplanId = $dplanId;
    }

    public function getProcedureShortName(): string
    {
        return $this->procedureShortName;
    }

    public function setProcedureShortName(string $procedureShortName): void
    {
        $this->procedureShortName = $procedureShortName;
    }

    public function getIsInterfaceActivated(): bool
    {
        return $this->isInterfaceActivated;
    }

    public function setIsInterfaceActivated(bool $isInterfaceActivated): void
    {
        $this->isInterfaceActivated = $isInterfaceActivated;
    }
}
