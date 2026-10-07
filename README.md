# demosplan Addon: meinBerlin.de Integration

## Overview

The **demosplan-addon-mein-berlin** is a [demosplan](https://github.com/demos-europe/demosplan-core) addon that integrates the mein.berlin API into the DEMOS plan platform. This addon enables seamless communication between DemosPlan procedures and the mein.berlin participation portal, automatically synchronizing procedure data and managing pictogram requirements.

## Features

- **Bidirectional Procedure Synchronization**: Automatically creates and updates procedures on mein.berlin when specific conditions are met
- **RSS Feed Generation**: Provides RSS feeds for mein.berlin procedure data
- **Pictogram Management**: Handles procedure pictogram validation with size and resolution restrictions
- **Organization Mapping**: Maps DemosPlan organizations to mein.berlin organization IDs
- **Event-Driven Updates**: Automatically propagates procedure changes to mein.berlin

## Configuration

### Required Parameters

Configure the following parameters in your project configuration:

```yaml
parameters:
    # mein.berlin API endpoints
    mein_berlin_procedure_create_url: 'https://mein.berlin.de/api/organisations/<organisation-id>/bplan/'
    mein_berlin_procedure_update_url: 'https://mein.berlin.de/api/organisations/<organisation-id>/bplan/<bplan-id>/'
    
    # Authentication
    mein_berlin_authorization: 'your-api-key'
    
    # Customer subdomain (addon only active for this subdomain)
    mein_berlin_subdomain: 'be'
    
    # Pictogram restrictions
    mein_berlin_pictogram_max_file_size: 5242880 # 5MB
    
    # Host configuration
    mein_berlin_dplan_hostname: 'your-domain.com'
    mein_berlin_public_procedure_route: 'core_procedure_slug'
```

## Core Components

### Entities

- **MeinBerlinAddonEntity**: Stores procedure-specific data for mein.berlin communication
- **MeinBerlinAddonOrgaRelation**: Maps DemosPlan organizations to mein.berlin organization IDs

### Services

- **MeinBerlinCreateProcedureService**: Handles initial procedure creation on mein.berlin
- **MeinBerlinUpdateProcedureService**: Manages procedure updates
- **MeinBerlinProcedureCommunicator**: Core communication with mein.berlin API
- **MeinBerlinAddonRelationService**: Manages organization relationships

### Event Listeners

- **MeinBerlinPostProcedureUpdatedEventSubscriber**: Automatically syncs procedure changes to mein.berlin

### Controllers

- **RssFeedController**: Generates RSS feeds for mein.berlin integration

## Procedure Communication Conditions

A procedure is communicated to mein.berlin when **ALL** of the following conditions are met:

1. **Organization ID**: An mein.berlin organization ID has been assigned to the procedure's organization
2. **District**: A Berlin district has been selected for the procedure
3. **Public Phase**: The procedure is in a publicly visible phase (permission set read/write, not hidden)
4. **Pictogram**: A valid pictogram has been uploaded and meets size/resolution requirements

### Pictogram Requirements

- Maximum file size: 5MB
- Supported resolution restrictions: 300x500px
- Format validation through base64 encoding

## API Integration Flow

### Initial Creation
1. Procedure meets all communication conditions
2. `MeinBerlinCreateProcedureService` sends POST request to mein.berlin
3. mein.berlin returns a `bplanId` which is stored for future updates
4. Procedure is marked as communicated

### Updates
1. Relevant procedure fields are modified
2. `MeinBerlinPostProcedureUpdatedEventSubscriber` detects changes
3. `MeinBerlinUpdateProcedureService` sends UPDATE request to mein.berlin
4. Changes are synchronized

### Supported Update Fields
- Procedure short name ("Bebauungsplannummer")
- Procedure settings and coordinates
- Phase information
- Pictogram changes
- Public visibility status

## RSS Feed Functionality

The addon provides RSS feeds accessible via specific routes for mein.berlin to consume procedure information. RSS feeds include:
- Procedure metadata
- Phase information
- Links to public participation
- Encoding and entry link configuration

The feed is available at `/mein_berlin/rss/<mein.berlin organisation id>` and lists all procedures of the
organisations with that id whose public phase is visible (permission set `read` or `write`). It is cached for one hour.

### Filtering by phase (Verfahrensschritt)

The feed can be restricted to procedures in certain public phases with the query parameter `VERFAHRENSSCHRITT`.
The value is the name of the phase as it is shown in the feed (compared ignoring case and surplus whitespace).
Without the parameter, the feed is unchanged. A filter only narrows the list, procedures that are not visible
never appear. If no procedure matches, the feed is valid but has no entries.

| Notation | Example (not URL encoded) |
|---|---|
| single name | `?VERFAHRENSSCHRITT=Auswertung Öffentlichkeit` |
| single quoted name | `?VERFAHRENSSCHRITT="Auswertung Öffentlichkeit"` |
| list in parentheses | `?VERFAHRENSSCHRITT=("Beteiligung Öffentlichkeit","Frühzeitige Beteiligung Öffentlichkeit - § 3 (1) BauGB")` |
| comma separated list | `?VERFAHRENSSCHRITT=Beteiligung Öffentlichkeit,Auswertung Öffentlichkeit` |
| array notation | `?VERFAHRENSSCHRITT[]=Beteiligung Öffentlichkeit&VERFAHRENSSCHRITT[]=Auswertung Öffentlichkeit` |

Notes:
- The parameter belongs to the query string and starts with `?`. Appending it with `&` directly to the organisation id
  does not work, as everything after the slash is then read as the organisation id.
- Names containing a comma have to be quoted or use the array notation.
- Special characters have to be URL encoded when the URL is embedded, e.g. `?VERFAHRENSSCHRITT=%22Auswertung%20%C3%96ffentlichkeit%22`.
- A repeated parameter without brackets can not be used for several names, only the last value reaches the application.
- The names are those of the customer's phase definitions. If a phase is renamed, the URLs have to be adjusted.

## Security & Permissions

### Permission Management
- Integrates with DemosPlan's permission system
- Dynamically enables pictogram-related permissions
- Access control for organization-specific settings

### API Security
- Secure communication with mein.berlin API
- Authorization header management
- Error handling and logging

## Database Schema

### Tables Created
- `mein_berlin_addon_entity`: Stores procedure-specific mein.berlin data
- `mein_berlin_addon_orga_relation`: Maps organizations to mein.berlin IDs

### Migrations
Located in `src/DoctrineMigrations/` with versioned migration files.

## Limitations

- **No Delete Support**: mein.berlin API does not support procedure deletion
- **Draft Hiding**: Procedures can only be hidden by updating `is_draft` status
- **Organization ID Constraints**: Organization IDs cannot be changed if procedures are already communicated

## Error Handling

The addon includes comprehensive error handling:
- `MeinBerlinCommunicationException`: API communication errors
- `MeinBerlinAccessControlPermissionException`: Permission-related errors  
- `MeinBerlinRssFeedException`: RSS feed generation errors

## Logging

Detailed logging is implemented throughout the addon for debugging and monitoring API communications and procedure synchronization.

## Changelog

See [CHANGELOG](CHANGELOG.md) for detailed version history and feature changes.


## License

This addon is licensed under the EuPL 1.2. See the [LICENSE](LICENSE) file for more details.
