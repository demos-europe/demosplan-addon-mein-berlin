# CHANGELOG

## UNRELEASED
- **fix BEAA2-33**: Revert tile_image optional fix - allow empty tile_image in API requests
  - MeinBerlin API will be updated to accept empty tile_image values
  - tile_image field is now always sent, even if empty (reverts previous fix)
  - Affects both CREATE (POST) and UPDATE (PATCH) requests to mein.berlin API

## v0.22 (2026-01-23)
- bump demosplan-addon version from v0.64 to v0.65

## v0.21 (2026-01-16)
- fix: Typo in translation key for district label (Kierzadar --> Kiezradar)
- **feat BEAA2-37**: Activate interface through warning modal
    - Click on 'activate interface'-button in the modal checks the interface checkbox
    - Success notification
- **fix BEAA2-33**: Make tile_image parameter optional in MeinBerlin API requests
  - Only send tile_image when a pictogram is actually uploaded
  - Remove tile_image from payload if empty to prevent "tile_image darf nicht leer sein" error
  - Affects both CREATE (POST) and UPDATE (PATCH) requests to mein.berlin API

## v0.20 (2026-01-08)
- **fix BEAA2-33**: Rename district parameter to administrative_district in MeinBerlin API
  - Update MEIN_BERLIN_DISTRICT constant from 'district' to 'administrative_district'
  - Affects both CREATE (POST) and UPDATE (PATCH) requests to mein.berlin API

## v0.19 (2025-12-17)
- **feat BEAA2-28**: Add district mapping migration for molkenmarkt procedure
  - Map molkenmarkt procedure (Bebauungsplan 1-14-1) to Mitte district (mi)
  - Add migration Version20251216175007 with up/down methods

## v0.18 (2025-12-09)
- **feat BEAA2-28**: Add district mapping migration for alte-gaertnerei procedure
  - Map alte-gaertnerei procedure to Pankow district (pa)
  - Add migration Version20251209123738 with up/down methods

## v0.17 (2025-12-05)
- **feat BEAA2-29**: Validation for the mein.berlin pictogram upload feature
  - Validate dimensions and format: only PNG, JPEG, and GIF files are accepted, min. 500x300px
  - Validate in real-time: Checks happen immediately after upload via @upload-success event
  - Load Image: Uses new Image() API to validate actual image dimensions from the server
  - Remove invalid file, add error and warning notifications
  - Prevent uploading pictogram, copyright- and alt text if Berlin oragId missing, add error notification

- **feat BEAA2-28**: Replace B-Plan number field with district dropdown
  - Replace `procedureShortName` (VARCHAR 255) with `district` (VARCHAR 2) field
  - Add migration to rename column and populate district from organisation mapping
  - Map 12 Berlin districts + 1 city-wide option using organisation IDs
  - Add special migration for 3 production procedures requiring manual district mapping
  - Rename `dplanId` to `bplanId` for API consistency with mein.berlin URL parameter
  - Update Vue component to use district field instead of procedureShortName
  - Remove obsolete translation keys and update to district-based translations
  - Fix test suite bootstrap configuration and update tests for new field names
  - Block district selection in onChange() when !hasBerlinOrgaIdand reset dropdown to placeholder after
    invalid selection attempt

- **feat BEAA2-27**: Add explicit interface activation control for mein.berlin.de data transmission
  - Add `isInterfaceActivated` boolean field to `MeinBerlinAddonEntity` to control data transmission
  - Replace pictogram requirement with explicit activation flag
  - Migration automatically activates interface for already transmitted procedures (with dplan_id)
  - Group mein-berlin fields together in the 'Verfahrensschritt Öffentlichkeit' section
  - Add UI checkbox with German translations for activation control
  - Disable checkbox if procedure has already been transmitted, add notification 
  - Disable checkbox if Berlin OrgaId is missing, add notification 
  - Add pictogram-Vue component (not a required field anymore)
  - Rename the addon.additional.field hook to interface.fields.to.transmit
  - Rename MeinBerlinAdditionalField to MeinBerlinAdditionalFields (there are more than 1 field now)

- **chore**: Add release.sh script to automate version releases
  - Updates CHANGELOG.md, composer.json, and package.json with new version
  - Creates git tag and pushes to remote

- added/merged minor version content of v0.15.1

## v0.16 (2025-10-15)
- update demos-europe/demosplan-addon to v0.64

## v0.15.1 (2025-11-07)
- change public participation start and end dates to include the time. (Y-m-d\TH:i ISO 8601 without seconds)
  The endDate time is per default (ProcedureRepository::generateObjectValues) at 23:59
- The startDate time is per default (CoreRepository::convertUserInputDate) at 02:00

## v0.15 (2025-09-03)
- update demos-europe/demosplan-addon to v0.60

## v0.14 (2025-08-04)
- update demos-europe/demosplan-addon to v0.59

## v0.13.1 (2025-08-07)
- fix: public participation phase in rss feed

## v0.13 (2025-05-21)
- fix: save meinBerlin organisationId at the orga 

## v0.12 (2025-05-20)
- Make Addon work with Tailwind 4

## v0.11 (2025-05-14)
- add missing test namespaces
- update to symfony 6
- do not duplicate link to procedure

## v0.10 (2025-04-01)
- dynamically set real project url
- do not fail request when procedure is already saved in database

## v0.9 (2025-03-29)
- do not send anything if no relevant changes have been found
- add logger calls
- fix tests

## v0.8 (2025-02-12)
 - migration to add missing access control permission for pictogram restrictions
   for already established meinBerlin orgaRelations.

## v0.7 (2025-02-11)
- Enable core permission 'field_procedure_pictogram_resolution_restriction' dynamically to be
  able to check for pictogram related restrictions regarding this addon within the core.
  Restrictions are 300x500px and 5MB max size.
  [MeinBerlinAddonOrganisationResourceType::addAccessControlPictogramPermission](./src/ResourceType/MeinBerlinAddonOrganisationResourceType.php)

## v0.6 (2025-02-06)
- fix: avoid unnecessary requests to fetch dropdown options

## v0.5 (2025-01-29)
- always enable addon when called via cli as subdomain is never set

## v0.4 (2025-01-28)
- add entry link and encoding

## v0.3 (2025-01-17)
- remove unused params
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
