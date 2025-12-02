<template>
  <component
    :is="demosplanUi.DpSelect"
    id="meinBerlinOrga-orgSelect"
    v-model="currentValue"
    :data-cy="`${resourceType}:field`"
    :label="{
      text: label,
      tooltip
    }"
    :options="options"
    @select="onChange"
  />
</template>

<script>
import { fetchMeinBerlinOrganisationId } from './fetchMeinBerlinOrganisationId'

export default {
  name: 'MeinBerlinOrgaField',

  emits: ['addonEvent:emit'],

  props: {
    additionalFieldOptions: {
      type: Array,
      required: false,
      default: () => []
    },

    demosplanUi: {
      type: Object,
      required: true
    },

    relationshipId: {
      type: String,
      required: false,
      default: ''
    },

    relationshipKey: {
      type: String,
      required: true,
      validator: (prop) => prop === 'orga'
    },

    userMeinBerlinOrgId: {
      type: [String, Number],
      required: false,
      default: ''
    },

    userOrgaId: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      currentValue: null,
      initValue: null,
      item: null,
      list: null,

      // Organization / Authority ID on mein.berlin.de
      options: [
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
      ]
    }
  },

  computed: {
    addonPayload () {
      const attributes = {}

      // Only send a value if it's actually set
      if (this.currentValue !== null && this.currentValue !== '') {
        attributes.meinBerlinOrganisationId = this.currentValue.toString()
      } else if (this.initValue !== null && this.initValue !== '') {
        attributes.meinBerlinOrganisationId = this.initValue.toString()
      } else {
        attributes.meinBerlinOrganisationId = ''
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

    label () {
      return Translator.trans('mein.berlin.organisation.id')
    },

    resourceType () {
      return 'MeinBerlinAddonOrganisation'
    },

    tooltip () {
      return Translator.trans('mein.berlin.organisation.id.tooltip')
    }
  },

  methods: {
    async autoSelectOrga () {
      let meinBerlinOrgId = this.userMeinBerlinOrgId

      if (!meinBerlinOrgId && this.userOrgaId) {
        meinBerlinOrgId = await fetchMeinBerlinOrganisationId(
          this.demosplanUi,
          this.userOrgaId
        )
      }

      if (!meinBerlinOrgId) {
        return
      }

      const value = String(meinBerlinOrgId)

      this.$nextTick(() => {
        this.onChange(value)
      })
    },

    fetchResourceList () {
      const url = Routing.generate('api_resource_list', { resourceType: this.resourceType })

      return this.demosplanUi.dpApi.get(url, { include: [this.relationshipKey].join() })
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
      this.item = Object.values(this.list || []).find(
        el => el.relationships[this.relationshipKey].data.id === this.relationshipId
      ) || null

      // Reset if no item
      this.currentValue = ''
      this.initValue = null

      // Only set a value if one exists
      if (this.item?.attributes?.meinBerlinOrganisationId) {
        const storedValue = this.item.attributes.meinBerlinOrganisationId
        this.currentValue = storedValue
        this.initValue = storedValue

        // Make sure the underlying <select> reflects the restored value
        this.syncNativeSelect()
      }
    },

    syncNativeSelect () {
      this.$nextTick(() => {
        const select = this.$el.querySelector('select')

        if (select && this.currentValue !== null && this.currentValue !== '') {
          select.value = this.currentValue
        }
      })
    },

    onChange (value) {
      this.currentValue = value
      this.$emit('addonEvent:emit', { name: 'selected', payload: this.addonPayload })
      this.syncNativeSelect()
    }
  },

  mounted() {
    const hasProvidedOptions = this.additionalFieldOptions.length > 0
    const hasNoCurrentValue =
      this.currentValue === null ||
      this.currentValue === ''

    // Case: options already provided
    if (hasProvidedOptions) {
      this.list = this.additionalFieldOptions
      this.getItemByRelationshipId()

      if (hasNoCurrentValue) {
        this.autoSelectOrga()
      }

      return
    }

    // Case: options NOT provided → fetch list
    this.fetchResourceList().then(() => {
      this.$emit('addonEvent:emit', {
        name: 'resourceList:loaded',
        payload: this.list
      })

      this.getItemByRelationshipId()

      if (hasNoCurrentValue) {
        this.autoSelectOrga()
      }
    })
  }
}
</script>
