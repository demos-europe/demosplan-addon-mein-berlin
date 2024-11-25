<template>
  <dp-select
    id="addonAdditionalField"
    name="addonAdditionalField"
    :data-cy="`${resourceType}:field`"
    :label="{
      text: label,
      hint: hint,
      tooltip: tooltip
    }"
    :options="options"
    v-model="currentValue"
    @selected="$emit('addonEvent:emit', { name: 'blur', payload: addonPayload })" />
</template>

<script>
import { dpApi, DpSelect } from '@demos-europe/demosplan-ui'

export default {
  name: 'AddonAdditionalField',

  components: {
    DpSelect
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
      options: [
        { label: 'Senatsverwaltung für Stadtentwicklung, Bauen und Wohnen', value: '14' },
        { label: 'Bezirksamt Charlottenburg-Wilmersdorf', value: '27' },
        { label: 'Bezirksamt Friedrichshain-Kreuzberg', value: '28' },
        { label: 'Bezirksamt Lichtenberg ', value: '29' },
        { label: 'Bezirksamt Marzahn-Hellersdorf ', value: '25' },
        { label: 'Bezirksamt Mitte', value: '16' },
        { label: 'Bezirksamt Neukölln', value: '30' },
        { label: 'Bezirksamt Pankow', value: '20' },
        { label: 'Bezirksamt Reinickendorf', value: '31' },
        { label: 'Bezirksamt Spandau', value: '26' },
        { label: 'Bezirksamt Steglitz-Zehlendorf', value: '32' },
        { label: 'Bezirksamt Tempelhof-Schöneberg', value: '24' },
        { label: 'Bezirksamt Treptow-Köpenick', value: '15' }
      ],
      relationshipKeyMapping: {
        'orga': {
          attribute: 'meinBerlinOrganisationId',
          hint: Translator.trans(''),
          label: Translator.trans('mein.berlin.organisation.id'),
          resourceType: 'MeinBerlinAddonOrganisation',
          tooltip: Translator.trans('mein.berlin.organisation.id.tooltip')
        },
        'procedure': {
          attribute: 'procedureShortName',
          hint: Translator.trans(''),
          label: Translator.trans('mein.berlin.procedure.short.name'),
          resourceType: 'MeinBerlinAddonProcedureData',
          tooltip: Translator.trans('mein.berlin.procedure.short.name.tooltip')
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

    hint () {
      return this.relationshipKeyMapping[this.relationshipKey]?.hint || ''
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
    fetchResourceList() {
      const url = Routing.generate('api_resource_list', {resourceType: this.resourceType})

      return dpApi.get(url, {include: [this.relationshipKey].join()})
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

    getItemByRelationshipId() {
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
