<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace DemosEurope\DemosplanAddon\DemosMeinBerlin\Configuration\Permissions;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\Permission\PermissionConditionBuilder;
use DemosEurope\DemosplanAddon\Permission\PermissionInitializerInterface;
use DemosEurope\DemosplanAddon\Permission\ResolvablePermissionCollectionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PermissionInitializer implements PermissionInitializerInterface
{
    private const PLANNING_ROLES_SET = [
        RoleInterface::PLANNING_AGENCY_ADMIN,
        RoleInterface::PLANNING_AGENCY_WORKER,
        RoleInterface::PRIVATE_PLANNING_AGENCY,
    ];

    private bool $procedureRestricedAccess;
    public function __construct(
        private readonly GlobalConfigInterface $globalConfig,
        private readonly ParameterBagInterface $parameterBag) {
        $this->procedureRestricedAccess = $globalConfig->hasProcedureUserRestrictedAccess();
    }


    public function configurePermissions(ResolvablePermissionCollectionInterface $permissionCollection): void
    {
        $permissionCollection->configurePermissionInstance(
            Features::feature_set_mein_berlin_organisation_id(),
            PermissionConditionBuilder::start()->enableIfUserHasRole(RoleInterface::CUSTOMER_MASTER_USER)
        );

        $permissionCollection->configurePermissionInstance(
            Features::feature_get_mein_berlin_organisation_id(),
            PermissionConditionBuilder::start()
                ->enableIfProcedureOwnedViaOrganisation(
                    self::PLANNING_ROLES_SET, $this->procedureRestricedAccess
                )
                ->enableIfProcedureOwnedViaPlanningAgency(
                    self::PLANNING_ROLES_SET
                )
                ->enableIfUserHasRole(RoleInterface::CUSTOMER_MASTER_USER)
        );

        $permissionCollection->configurePermissionInstance(
            Features::feature_set_mein_berlin_procedure_short_name(),
            PermissionConditionBuilder::start()
                ->enableIfProcedureOwnedViaOrganisation(
                self::PLANNING_ROLES_SET, $this->procedureRestricedAccess
                )
                ->enableIfProcedureOwnedViaPlanningAgency(
                    self::PLANNING_ROLES_SET
                )
        );
    }

    public function isEnabled(): bool
    {
        // when called from CLI, the addon needs to be enabled as we do not have a subdomain
        if (PHP_SAPI === 'cli') {
            return true;
        }
        return $this->parameterBag->get('mein_berlin_subdomain') === $this->globalConfig->getSubdomain();
    }
}
