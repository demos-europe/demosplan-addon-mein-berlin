# CHANGELOG

## UNRELEASED
- enable addon only for specific customer, defined by param `mein_berlin_subdomain` 


## v0.2 (2025-01-06)
- add the pictogram to the list of mandatory fields for a meinBerlin communication.
  procedures will now only be created at meinBerlin if a pictogram has also been set.
  documentation of v0.1 has been updated accordingly.
- fix getting procedureShortName from procedure
  the property had to be set at the [MeinBerlinAddonEntity::procedureShortName](./src/Entity/MeinBerlinAddonEntity.php)
  before the getter can be used.
- fix strict type hint to null or string in [MeinBerlinProcedurePictogramFileHandler::checkForPictogramAndGetBase64FileString](./src/Logic/MeinBerlinProcedurePictogramFileHandler.php)
- Add coordinate transfor helper class and implemented its usage for updates and create messages
  [MeinBerlinCoordinateTransformer::getCoordinateAsGeoJSON](./src/Logic/MeinBerlinProcedureSettingsCoordinateHandler.php)

## v0.1 (2024-12-20)
Basic Feature Implementation:
Ticket: https://demoseurope.youtrack.cloud/issue/BEAA2-10/AP-1-Schnittstellenumsetzung-zwischen-DiPlanBeteiligung-und-mein.berlin.de

procedures will be communicated to meinBerlin if:
- An organisationId for mein Berlin has been assigned to an orga by the customer-administration
  [MeinBerlinAddonOrgaRelation::meinBerlinOrganisationId](./src/Entity/MeinBerlinAddonOrgaRelation.php)
- A procedureShortName aka 'Bebauungsplannummer' has been set for a procedure of an orga 
  with meinBerlin organisationId by an FP-A
  [MeinBerlinAddonEntity::procedureShortName](./src/Entity/MeinBerlinAddonEntity.php)
- The procedure is in a public visible phase (premissionset read/write)
  [MeinBerlinCommunicationHelper::checkProcedurePublicPhasePermissionsetNotHidden](./src/Logic/MeinBerlinCommunicationHelper.php)
- The procedure has a pictogram set

When sending a create POST we should get a dplanId in response which:
- signals this procedure has been communicated to meinBerlin
- is required to send updates regarding this procedrue at meinBerlin
  [MeinBerlinAddonEntity::dplanId](./src/Entity/MeinBerlinAddonEntity.php)

after the initial create POST to meinBerlin - an update will be sent for every relevant field that changed.

The procedureShortName aka 'Bebauungsplannummer' will be updated at meinBerlin when
updating the property via resourceType. This update for meinBerlin only contains this field.
[MeinBerlinUpdateProcedureService::updateProcedureShortNameByResourceType](./src/Logic/MeinBerlinUpdateProcedureService.php)
All other relevant fields will be handled by the PostProcedureUpdatedEvent which may update
multiple relevant fields for meinBerlin at once.
[MeinBerlinUpdateProcedureService::updateMeinBerlinProcedureEntry](./src/Logic/MeinBerlinUpdateProcedureService.php)

The organisationId shall be updatable only if the id is not in use.
Meaning no procedures of that orga are communicated to meinBerlin yet. 
(reason the id is used by update-url-requests)
[MeinBerlinAddonOrgaRelationRepository::getProceduresOfOrgaWithExistingDplanId](./src/Repository/MeinBerlinAddonOrgaRelationRepository.php)

The procedureShortName aka 'Bebauungsplannummer' can be updated freely but can not be deleted
as it is a required field for meinBerlin.

**There is no delete method support at myBerlin - so deleting a procedure is not handled  yet !!!**
We can only hide the procedrue publicly at meinBerlin by updating the procedure with is_draft -> false

## v0.1 (2024-11-21)

Technical start of the changelog
