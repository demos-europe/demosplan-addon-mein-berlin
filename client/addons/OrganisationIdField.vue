<template>
  <div class="flex flex-row">
    <dp-input
      id="meinBerlinOrganisationId"
      v-model="currentValue"
      :label="{
        text: label
      }"
      @blur="$emit('addonEvent:emit', { name: 'blur', payload: addonPayload })"
      :required="required" />
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
    relationshipId: {
      type: String,
      required: false,
      default: ''
    },

    resourceType: {
      type: String,
      required: true,
      validator: (prop) => ['MeinBerlinAddonOrganisation', 'MeinBerlinAddonProcedureData'].includes(prop)
    },

    required: {
      type: Boolean,
      required: false,
      default: false
    },

    label: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      currentValue: '',
      item: null,
      list: null,
      resourceTypeMappings: {
        'MeinBerlinAddonOrganisation': {
          attribute: 'meinBerlinOrganisationId',
          relationshipKey: 'orga'
        },
        'MeinBerlinAddonProcedureData': {
          attribute: 'procedureShortName',
          relationshipKey: 'procedure'
        }
      },
    }
  },

  computed: {
    addonPayload () {
      return {
        id: this.item ? this.item.id : '',
        resourceType: this.resourceType,
        attributes: {
          [this.attribute]: this.currentValue
        },
        request: this.item ? 'PATCH' : 'POST'
      }
    },

    attribute () {
      return this.resourceTypeMappings[this.resourceType]?.attribute || undefined
    },

    relationshipKey () {
      return this.resourceTypeMappings[this.resourceType]?.relationshipKey || undefined
    }
  },

  methods: {
    fetchResourceList () {
      const url = Routing.generate('api_resource_list', { resourceType: this.resourceType })

      return dpApi.get(url, { include: [this.relationshipKey].join() })
        .then(response => {
          this.list = response.data.data.map(item => {
            return {
              id: item.id,
              attributes: item.attributes,
              relationships: item.relationships
            }
          })
        })
        .catch(err => console.error(err))
    },

    getItemByRelationshipId () {
      this.item = Object.values(this.list).find(el => el.relationships[this.relationshipKey].data.id === this.relationshipId) || null

      if (this.item) {
        this.currentValue = this.item.attributes[this.attribute]
      }
    }
  },

  mounted () {
    this.fetchResourceList().then(this.getItemByRelationshipId)
  }
}
</script>
