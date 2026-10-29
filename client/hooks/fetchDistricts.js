import { shareRequest } from './shareRequest'

/**
 * Loads the Berlin districts with their name, the name of the office and the mein.berlin organisation ID from the district catalog.
 *
 * @param {Object} demosplanUi
 * @param {Object} [options]
 * @param {Array<string>} [options.fields] replaces the default fields, e.g. to read fields that are not default
 * @param {string} [options.sort]
 * @return {Promise<Array<{ id: string, districtCode: string, name: string, officeName: string, meinBerlinOrganisationId: string|null }>>}
 */
export async function fetchDistricts (demosplanUi, { fields = [], sort = '' } = {}) {
  try {
    const url = Routing.generate('api_resource_list', {
      resourceType: 'MeinBerlinAddonDistrict',
      ...(fields.length > 0 && { fields: { MeinBerlinAddonDistrict: fields.join() } }),
      ...(sort && { sort })
    })

    const response = await shareRequest(url, () => demosplanUi.dpApi.get(url))

    return (response.data?.data || []).map(({ attributes, id }) => ({ id, ...attributes }))
  } catch (err) {
    console.error('[MeinBerlin] Failed to fetch districts:', err)
  }

  return []
}
