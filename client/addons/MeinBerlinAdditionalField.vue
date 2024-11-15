<template>
  <dp-input
    :id="resourceType"
    :data-cy="`${resourceType}:field`"
    v-model="currentValue"
    :label="{
      text: label,
      hint: hint,
    }"
    @blur="$emit('addonEvent:emit', { name: 'blur', payload: addonPayload })"
    @input="$emit('addonEvent:emit', { name: 'input', payload: currentValue })"
    :required="required" />
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
          hint: Translator.trans(''),
          label: Translator.trans('mein.berlin.organisation.id'),
          relationshipKey: 'orga'
        },
        'MeinBerlinAddonProcedureData': {
          attribute: 'procedureShortName',
          hint: Translator.trans(''),
          label: Translator.trans('mein.berlin.procedure.short.name'),
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
        request: this.item ? 'PATCH' : 'POST',
        value: this.currentValue,
        initValue: this.item ? this.item.attributes[this.attribute] : ''
      }
    },

    attribute () {
      return this.resourceTypeMappings[this.resourceType]?.attribute || undefined
    },

    hint () {
      return this.resourceTypeMappings[this.resourceType]?.hint || ''
    },

    label () {
      return this.resourceTypeMappings[this.resourceType]?.label || ''
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
