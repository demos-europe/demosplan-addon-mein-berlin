<template>
  <dp-input
    v-if="isInput"
    id="addonAdditionalField"
    :data-cy="`${resourceType}:field`"
    :label="{
      text: label,
      tooltip
    }"
    :required="required || (Boolean(initValue) && !isValueRemovable)"
    v-model="currentValue"
    pattern="^.*\S-\S.*$"
    @blur="$emit('addonEvent:emit', { name: 'blur', payload: addonPayload })"
    @focus="handleFocus" />

  <dp-select
    v-else
    id="addonAdditionalField"
    :data-cy="`${resourceType}:field`"
    :label="{
      text: label,
      tooltip
    }"
    :options="options"
    v-model="currentValue"
    @select="$emit('addonEvent:emit', { name: 'selected', payload: addonPayload })" />
</template>

<script>
import { dpApi, DpInput, DpSelect } from '@demos-europe/demosplan-ui'

export default {
  name: 'MeinBerlinAdditionalField',

  components: {
    DpInput,
    DpSelect
  },

  props: {
    additionalFieldOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    isInput: {
      type: Boolean,
      required: false,
      default: false
    },

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
      options: [ /* Organization / Authority ID on mein.berlin.de */
        { label: Translator.trans('mein.berlin.district.office.administration'), value: '14' },
        { label: Translator.trans('mein.berlin.district.office.charlottenburg_wilmersdorf'), value: '27' },
        { label: Translator.trans('mein.berlin.district.office.friedrichshain_kreuzberg'), value: '28' },
        { label: Translator.trans('mein.berlin.district.office.lichtenberg'), value: '29' },
        { label: Translator.trans('mein.berlin.district.office.marzahn_hellersdorf'), value: '25' },
        { label: Translator.trans('mein.berlin.district.office.mitte'), value: '16' },
        { label: Translator.trans('mein.berlin.district.office.neukoelln'), value: '30' },
        { label: Translator.trans('mein.berlin.district.office.pankow'), value: '20' },
        { label: Translator.trans('mein.berlin.district.office.reinickendorf'), value: '31' },
        { label: Translator.trans('mein.berlin.district.office.spandau'), value: '26' },
        { label: Translator.trans('mein.berlin.district.office.steglitz_zehlendorf'), value: '32' },
        { label: Translator.trans('mein.berlin.district.office.tempelhof_schoeneberg'), value: '24' },
        { label: Translator.trans('mein.berlin.district.office.treptow_koepenick'), value: '15' }
      ],
      relationshipKeyMapping: {
        orga: {
          attribute: 'meinBerlinOrganisationId',
          label: Translator.trans('mein.berlin.organisation.id'),
          resourceType: 'MeinBerlinAddonOrganisation',
          tooltip: Translator.trans('mein.berlin.organisation.id.tooltip')
        },
        procedure: {
          attribute: 'procedureShortName',
          label: Translator.trans('mein.berlin.procedure.short.name'),
          resourceType: 'MeinBerlinAddonProcedureData',
          tooltip: Translator.trans('mein.berlin.procedure.short.name.tooltip')
        }
      }
    }
  },

  computed: {
    addonPayload () {
      const attributes = {}

      if (this.attribute) {
        attributes[this.attribute] = this.currentValue
      }

      return {
        attributes,
        id: this.item ? this.item.id : '',
        initValue: this.item ? this.initValue : '',
        resourceType: this.resourceType,
        value: this.currentValue,
        url: this.item ? 'api_resource_update' : 'api_resource_create'
      }
    },

    attribute () {
      return this.relationshipKeyMapping[this.relationshipKey]?.attribute || ''
    },

    label () {
      return this.relationshipKeyMapping[this.relationshipKey]?.label || ''
    },

    resourceType () {
      return this.relationshipKeyMapping[this.relationshipKey]?.resourceType || ''
    },

    tooltip () {
      return this.relationshipKeyMapping[this.relationshipKey]?.tooltip || ''
    }
  },

  methods: {
    fetchResourceList () {
      const url = Routing.generate('api_resource_list', { resourceType: this.resourceType })

      return dpApi.get(url, { include: [this.relationshipKey].join() })
        .then(response => {
          this.list = response.data.data.map(item => {
            const { attributes, id, relationships } = item

            return {
              id,
              attributes,
              relationships
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
    },

    handleFocus () {
      const input = document.getElementById('addonAdditionalField')

      if (input.classList.contains('is-invalid')) {
        input.classList.remove('is-invalid')
      }
    }
  },

  mounted () {
    if (!this.additionalFieldOptions.length) {
      this.fetchResourceList()
        .then(() => {
          this.$emit('addonEvent:emit', { name: 'resourceList:loaded', payload: this.list })
          this.getItemByRelationshipId()
        })
    } else {
      this.list = this.additionalFieldOptions
      this.getItemByRelationshipId()
    }
  }
}
</script>
