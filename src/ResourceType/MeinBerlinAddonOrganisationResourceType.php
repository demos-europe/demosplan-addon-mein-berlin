<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\ResourceType;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\AddonResourceType;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\OrgaResourceTypeInterface;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions\Features;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonEntity;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Entity\MeinBerlinAddonOrgaRelation;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Exception\MeinBerlinCommunicationException;
use DemosEurope\DemosplanAddon\DemosMeinBerlin\Repository\MeinBerlinAddonOrgaRelationRepository;
use DemosEurope\DemosplanAddon\Permission\PermissionEvaluatorInterface;
use EDT\ConditionFactory\ConditionFactoryInterface;
use EDT\JsonApi\ApiDocumentation\DefaultField;
use EDT\JsonApi\ApiDocumentation\OptionalField;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Wrapping\EntityDataInterface;
use EDT\Wrapping\PropertyBehavior\Attribute\Factory\CallbackAttributeSetBehaviorFactory;
use EDT\Wrapping\PropertyBehavior\FixedSetBehavior;
use function array_map;
use function count;

/**
 * @template-extends AddonResourceType<MeinBerlinAddonOrgaRelation>
 */
class MeinBerlinAddonOrganisationResourceType extends AddonResourceType
{
    public function __construct(
        private readonly ConditionFactoryInterface $conditionFactory,
        private readonly PermissionEvaluatorInterface $permissionEvaluator,
        private readonly OrgaResourceTypeInterface $orgaResourceType,
        private readonly MeinBerlinAddonOrgaRelationRepository $meinBerlinAddonOrgaRelationRepository,
        private readonly CurrentUserInterface $currentUser,
        private readonly MessageBagInterface $messageBag,
    ) {

    }

    protected function getAccessConditions(): array
    {
        $ownOrgaId = $this->currentUser->getUser()->getOrga()?->getId();

        $conditions = [$this->conditionFactory->false()];
        if ($this->permissionEvaluator->isPermissionEnabled(
            Features::feature_get_mein_berlin_organisation_id()
        )) {
            $conditions = [$this->conditionFactory->propertyHasValue($ownOrgaId, ['orga', 'id'])];
        }
        if ($this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id())) {
            $conditions = [$this->conditionFactory->true()];
        }

        return $conditions;
    }

    public function isCreateAllowed(): bool
    {
        return $this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id());
    }

    public function isDeleteAllowed(): bool
    {
        return $this->isCreateAllowed();
    }

    protected function getProperties(): array|ResourceConfigBuilderInterface
    {
        $configBuilder = new MeinBerlinAddonOrganisationResourceConfigBuilder(
            $this->getEntityClass(),
            $this->getPropertyBuilderFactory()
        );

        $configBuilder->id->setReadableByPath()->setSortable()->setFilterable();
        $configBuilder->meinBerlinOrganisationId->setReadableByPath(DefaultField::YES)->setSortable()->setFilterable()
            ->addUpdateBehavior(
                new CallbackAttributeSetBehaviorFactory(
                    [],
                    function (
                        MeinBerlinAddonOrgaRelation $meinBerlinAddonOrgaRelation,
                        ?string $meinBerlinOrganisationId
                    ): array {
                        // no update will be sent to meinBerlin as updating this field is allowed only until
                        // the first procedure has been published with this organisationId
                        $this->logger->info('demosplan-mein-berlin-addon registered an
                        MeinBerlinOrganisationId update - check if any Procedures were live already',
                            [$meinBerlinAddonOrgaRelation, ['newMeinBerlinOrganisationId' => $meinBerlinOrganisationId]]
                        );
                        $alreadyEstablishedCommunications = $this->meinBerlinAddonOrgaRelationRepository
                            ->getProceduresOfOrgaWithExistingDplanId($meinBerlinOrganisationId);
                        if (0 < count($alreadyEstablishedCommunications)) {
                            $alreadyEstablishedCommunicationProcedureIds = array_map(
                                static fn(MeinBerlinAddonEntity $addonEntity) => $addonEntity->getProcedure()?->getId(),
                                $alreadyEstablishedCommunications
                            );
                            $this->logger->info('demosplan-mein-berlin-addon found already established
                            communications for MeinBerlinOrganisationId',
                                ['procedures' => $alreadyEstablishedCommunicationProcedureIds]
                            );
                            $this->messageBag->add(
                                'error',
                                'mein.berlin.error.update.organisation.id.for.established.communication'
                            );
                            throw new MeinBerlinCommunicationException('MeinBerlinOrganisationId already in use');
                        }
                        $this->logger->info('checks passed - will update MeinBerlinOrganisationId');
                        $meinBerlinAddonOrgaRelation->setMeinBerlinOrganisationId($meinBerlinOrganisationId);

                        return [];
                    },
                    OptionalField::NO,
                )
            )
            ->addPathCreationBehavior();
        $configBuilder->orga->setRelationshipType($this->orgaResourceType)
            ->setReadableByPath()
            ->setFilterable()
            ->setSortable()
            ->addPathCreationBehavior();
        $configBuilder->addCreationBehavior(
            new FixedSetBehavior(
                function (
                    MeinBerlinAddonOrgaRelation $meinBerlinAddonOrgaRelation,
                    EntityDataInterface $entityData
                ): array {
                    $meinBerlinAddonOrganisationId =
                        ((array) $entityData->getAttributes())['meinBerlinOrganisationId'] ?? '';
                    if ('' === $meinBerlinAddonOrganisationId) {
                        $this->logger->warning('demosplan-mein-berlin-addon tried to create a new
                            MeinBerlinOrganisation relation without a MeinBerlinOrganisationId',
                            ['relationToOrga' => ((array) $entityData->getToOneRelationships())['orga']['id'] ?? null]
                        );
                        $this->messageBag->add('error', 'mein.berlin.error.create.empty.organisation.id');
                        throw new MeinBerlinCommunicationException('missing MeinBerlinOrganisationId');
                    }
                    $orgaRelationId = ((array) $entityData->getToOneRelationships())['orga']['id'] ?? '';
                    if ('' === $orgaRelationId
                        || null !== $this->meinBerlinAddonOrgaRelationRepository->getByOrgaId($orgaRelationId)
                    ) {
                        $this->logger->error('demosplan-mein-berlin-addon tried to create a second new
                            MeinBerlinOrganisation relation',
                            ['relation for orgaId already present: ' => $orgaRelationId]
                        );
                        // messageBag gets the generic error message automatically
                        throw new MeinBerlinCommunicationException('relation has to be unique');
                    }

                    $this->meinBerlinAddonOrgaRelationRepository
                        ->persistMeinBerlinAddonOrgaRelation($meinBerlinAddonOrgaRelation);
                    $this->logger->info('demosplan-mein-berlin-addon registered a new
                            MeinBerlinOrganisationId relation
                            - check if any Procedures meet all conditions to be made public at MeinBerlin.',
                        [$meinBerlinAddonOrgaRelation]
                    );
                    // The Mandanten-Administration has to set up this organisationId relation before the
                    // meinBerlinProcedureShortName is creatable,
                    // Therefore no necessary communication can be triggert by this step.
                    return [];
                }
            )
        );

        return $configBuilder;
    }

    public function getEntityClass(): string
    {
        return MeinBerlinAddonOrgaRelation::class;
    }

    public function isGetAllowed(): bool
    {
        return $this->isAvailable();
    }

    public function isAvailable(): bool
    {
        return $this->permissionEvaluator->isPermissionEnabled(Features::feature_set_mein_berlin_organisation_id())
            || $this->permissionEvaluator->isPermissionEnabled(Features::feature_get_mein_berlin_organisation_id());
    }

    public function isListAllowed(): bool
    {
        return $this->isGetAllowed();
    }

    public function getTypeName(): string
    {
        return 'MeinBerlinAddonOrganisation';
    }

    public function isUpdateAllowed(): bool
    {
        return $this->isCreateAllowed();
    }
}
