/**
 * Loads the Berlin districts with their name and mein.berlin organisation ID from the district catalog.
 *
 * @return {Promise<Array<{ districtCode: string, name: string, meinBerlinOrganisationId: string|null }>>}
 */
export async function fetchDistricts (demosplanUi) {
  try {
    const url = Routing.generate('api_resource_list', { resourceType: 'MeinBerlinAddonDistrict' })

    const response = await demosplanUi.dpApi.get(url)

    return (response.data?.data || []).map(({ attributes }) => attributes)
  } catch (err) {
    console.error('[MeinBerlin] Failed to fetch districts:', err)
  }

  return []
}
