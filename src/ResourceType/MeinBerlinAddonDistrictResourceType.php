<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\ResourceType;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\AddonResourceType;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions\Features;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonDistrict;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Logic\MeinBerlinOrganisationIdChangeHandler;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonDistrictRepository;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\Factory\CallbackAttributeSetBehaviorFactory;

/**
 * Exposes the Berlin districts with their mein.berlin.de organisation ID. The organisation dropdown is built
 * from it and the IDs can be maintained on the page of the districts.
 *
 * @template-extends AddonResourceType<MeinBerlinAddonDistrict>
 */
class MeinBerlinAddonDistrictResourceType extends AddonResourceType
{
    public function __construct(
        private readonly ConditionFactoryInterface $conditionFactory,
        private readonly PermissionEvaluatorInterface $permissionEvaluator,
        private readonly MeinBerlinAddonDistrictRepository $districtRepository,
        private readonly MeinBerlinAddonOrgaRelationRepository $orgaRelationRepository,
        private readonly MessageBagInterface $messageBag,
        private readonly MeinBerlinOrganisationIdChangeHandler $organisationIdChangeHandler,
    ) {
    }

    protected function getAccessConditions(): array
    {
        return [$this->conditionFactory->true()];
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        $configBuilder = new MeinBerlinAddonDistrictResourceConfigBuilder(
            $this->getEntityClass(),
            $this->getPropertyBuilderFactory()
        );

        $configBuilder->id->setReadableByPath()->setSortable()->setFilterable();
        $configBuilder->districtCode->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable();
        $configBuilder->name->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable();
        $configBuilder->meinBerlinOrganisationId->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable()
            ->addUpdateBehavior(
                new CallbackAttributeSetBehaviorFactory(
                    [],
                    function (MeinBerlinAddonDistrict $district, ?string $meinBerlinOrganisationId): array {
                        // an empty value means that the district has no ID (anymore)
                        $meinBerlinOrganisationId = '' === (string) $meinBerlinOrganisationId
                            ? null
                            : trim((string) $meinBerlinOrganisationId);
                        $this->assertOrganisationIdIsValid($district, $meinBerlinOrganisationId);
                        $this->logger->info(
                            'demosplan-mein-berlin-addon registered an update of the organisation ID of a district',
                            [
                                'districtCode' => $district->getDistrictCode(),
                                'oldMeinBerlinOrganisationId' => $district->getMeinBerlinOrganisationId(),
                                'newMeinBerlinOrganisationId' => $meinBerlinOrganisationId,
                            ]
                        );
                        // The organisations that use the old ID are given the new one. Their procedures that were
                        // already communicated lose their link to meinBerlin, see the change handler.
                        $oldOrganisationId = $district->getMeinBerlinOrganisationId();
                        if (null !== $oldOrganisationId && $oldOrganisationId !== $meinBerlinOrganisationId) {
                            $changes = $this->organisationIdChangeHandler->changeOrganisationIdOfOrganisations(
                                $oldOrganisationId,
                                $meinBerlinOrganisationId ?? ''
                            );
                            $this->logger->info(
                                'demosplan-mein-berlin-addon changed the organisation ID of the organisations of a district',
                                ['districtCode' => $district->getDistrictCode()] + $changes
                            );
                        }
                        $district->setMeinBerlinOrganisationId($meinBerlinOrganisationId);

                        return [];
                    },
                    OptionalField::NO,
                )
            );
        // The following attributes take queries for each district, so they are not part of the default fields
        $configBuilder->usedByOrganisations->setReadableByCallable(
            fn (MeinBerlinAddonDistrict $district): int => null === $district->getMeinBerlinOrganisationId()
                ? 0
                : $this->orgaRelationRepository->count([
                    'meinBerlinOrganisationId' => $district->getMeinBerlinOrganisationId(),
                ])
        );
        $configBuilder->communicatedProcedures->setReadableByCallable(
            fn (MeinBerlinAddonDistrict $district): array => null === $district->getMeinBerlinOrganisationId()
                ? ['count' => 0, 'names' => []]
                : $this->organisationIdChangeHandler->getCommunicatedProceduresInfoOfOrganisationId(
                    $district->getMeinBerlinOrganisationId()
                )
        );

        return $configBuilder;
    }

    public function getEntityClass(): string
    {
        return MeinBerlinAddonDistrict::class;
    }

    public function getTypeName(): string
    {
        return 'MeinBerlinAddonDistrict';
    }

    /**
     * The same users who may see an organisation ID need the districts to choose one.
     */
    public function isAvailable(): bool
    {
        return $this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id())
            || $this->permissionEvaluator->isPermissionEnabled(Features::feature_get_mein_berlin_organisation_id());
    }

    public function isGetAllowed(): bool
    {
        return $this->isAvailable();
    }

    public function isListAllowed(): bool
    {
        return $this->isAvailable();
    }

    public function isCreateAllowed(): bool
    {
        return false;
    }

    public function isUpdateAllowed(): bool
    {
        return $this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id());
    }

    public function isDeleteAllowed(): bool
    {
        return false;
    }

    /**
     * @throws MeinBerlinCommunicationException
     */
    private function assertOrganisationIdIsValid(MeinBerlinAddonDistrict $district, ?string $organisationId): void
    {
        if (null === $organisationId) {
            return;
        }

        if (!ctype_digit($organisationId)) {
            $this->messageBag->add('error', 'mein.berlin.error.update.organisation.id.invalid');
            throw new MeinBerlinCommunicationException('MeinBerlinOrganisationId has to be a number');
        }

        $districtWithSameId = $this->districtRepository->findOneBy(['meinBerlinOrganisationId' => $organisationId]);
        if ($districtWithSameId instanceof MeinBerlinAddonDistrict && $districtWithSameId !== $district) {
            $this->messageBag->add('error', 'mein.berlin.error.update.district.organisation.id.taken');
            throw new MeinBerlinCommunicationException('MeinBerlinOrganisationId belongs to another district');
        }
    }
}
