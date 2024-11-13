<template>
  <div class="flex flex-row">
    <dp-input
      id="meinBerlinOrganisationId"
      v-model="currentValue"
      :label="{
        text: Translator.trans('organisation.mein.berlin.id')
      }"
      @blur="$emit('addonEvent:emit', { name: 'blur', payload: addonPayload })"
      required />
  </div>
</template>

<script>
import { dpApi, DpInput} from '@demos-europe/demosplan-ui'

export default {
  name: 'MeinBerlinAddonOrganisationId',

  components: {
    DpInput
  },

  props: {
    orgaId: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      currentValue: '',
      meinBerlinOrganisation: null,
      meinBerlinOrganisations: null,
      meinBerlinOrganisationId: ''
    }
  },

  computed: {
    addonPayload () {
      return {
        id: this.meinBerlinOrganisationId,
        resourceType: 'MeinBerlinAddonOrganisation',
        attributes: {
          meinBerlinOrganisationId: this.currentValue
        },
        request: this.meinBerlinOrganisation ? 'PATCH' : 'POST'
      }
    }
  },

  methods: {
    fetchMeinBerlinOrganisations () {
      const url = Routing.generate('api_resource_list', { resourceType: 'MeinBerlinAddonOrganisation' })

      return dpApi.get(url, { include: ['orga'].join() })
        .then(response => {
          this.meinBerlinOrganisations = response.data.data.map(organisation => {
            return {
              id: organisation.id,
              attributes: organisation.attributes,
              relationships: organisation.relationships
            }
          })
        })
        .catch(err => console.error(err))
    },
  },

  mounted () {
    this.fetchMeinBerlinOrganisations()
      .then(() => {
        this.meinBerlinOrganisation = Object.values(this.meinBerlinOrganisations).find(el => el.relationships.orga.data.id === this.orgaId) || null

        if (this.meinBerlinOrganisation) {
          this.meinBerlinOrganisationId = this.meinBerlinOrganisation.id
          this.currentValue = this.meinBerlinOrganisation.attributes.meinBerlinOrganisationId
        }
      })
  }
}
</script>
