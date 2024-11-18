<template>
  <dp-input
    id="meinBerlinAdditionalField"
    :data-cy="`${resourceType}:field`"
    :label="{
      text: label,
      hint: hint,
    }"
    :required="required || (hasValueBeenRemoved && !isValueRemovable)"
    v-model="currentValue"
    @blur="$emit('addonEvent:emit', { name: 'blur', payload: addonPayload })" />
</template>

<script>
import { dpApi, DpInput} from '@demos-europe/demosplan-ui'

export default {
  name: 'AddonAdditionalField',

  components: {
    DpInput
  },

  props: {
    isValueRemovable: {
      type: Boolean,
      required: false,
      default: false
    },

    relationshipId: {
      type: String,
      required: false,
      default: ''
    },

    relationshipKey: {
      type: String,
      required: true,
      validator: (prop) => ['orga', 'procedure'].includes(prop)
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
      initValue: '',
      item: null,
      list: null,
      relationshipKeyMapping: {
        'orga': {
          attribute: 'meinBerlinOrganisationId',
          hint: Translator.trans(''),
          label: Translator.trans('mein.berlin.organisation.id'),
          resourceType: 'MeinBerlinAddonOrganisation'
        },
        'procedure': {
          attribute: 'procedureShortName',
          hint: Translator.trans(''),
          label: Translator.trans('mein.berlin.procedure.short.name'),
          resourceType: 'MeinBerlinAddonProcedureData'
        }
      }
    }
  },

  computed: {
    addonPayload () {
      return {
        attributes: {
          [this.attribute]: this.currentValue
        },
        id: this.item ? this.item.id : '',
        initValue: this.item ? this.initValue : '',
        resourceType: this.resourceType,
        value: this.currentValue,
        url: this.item ? 'api_resource_update' : 'api_resource_create'
      }
    },

    attribute () {
      return this.relationshipKeyMapping[this.relationshipKey]?.attribute || undefined
    },

    hasValueBeenRemoved () {
      if (!this.item) {
        return false
      }

      return this.initValue && !this.currentValue
    },

    hint () {
      return this.relationshipKeyMapping[this.relationshipKey]?.hint || ''
    },

    label () {
      return this.relationshipKeyMapping[this.relationshipKey]?.label || ''
    },

    resourceType () {
      return this.relationshipKeyMapping[this.relationshipKey]?.resourceType || ''
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
        this.initValue = this.item.attributes[this.attribute]
      }
    }
  },

  mounted () {
    this.fetchResourceList().then(this.getItemByRelationshipId)
  }
}
</script>
