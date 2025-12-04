export async function fetchMeinBerlinOrganisationId (demosplanUi, orgaId) {
  try {
    const url = Routing.generate('api_resource_list', {
      resourceType: 'MeinBerlinAddonOrganisation'
    })

    const response = await demosplanUi.dpApi.get(url, { include: 'orga' })

    if (response.data?.data) {
      const orgaRelation = response.data.data.find(item =>
        item.relationships?.orga?.data?.id === orgaId
      )

      return orgaRelation?.attributes?.meinBerlinOrganisationId || null
    }
  } catch (err) {
    console.error('[MeinBerlin] Failed to fetch mein.berlin organisation ID:', err)
  }

  return null
}
