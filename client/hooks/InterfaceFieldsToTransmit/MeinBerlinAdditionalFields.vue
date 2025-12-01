<template>
  <div v-if="isProcedureSettingsPage">
    <h3>
      {{ Translator.trans('mein.berlin.interface') }}
    </h3>

    <component
      :is="demosplanUi.DpInlineNotification"
      v-if="!isCheckingBerlinOrgaId && (isProcedureTransmitted || !hasBerlinOrgaId)"
      :message="isProcedureTransmitted ? Translator.trans('mein.berlin.procedure.already.transmitted') : Translator.trans('mein.berlin.orga.id.missing.transmission.not.possible')"
      class="mb-4"
      type="info"
    />

    <component
      :is="demosplanUi.DpCheckbox"
      id="interfaceFieldsToTransmit-checkbox"
      v-model="isInterfaceActivated"
      :checked="isInterfaceActivated"
      :disabled="isProcedureTransmitted || !hasBerlinOrgaId"
      :label="{ text: Translator.trans('mein.berlin.interface.activation.label') }"
      class="mt-4 mb-4"
      @change="onCheckboxChange"
    />

    <component
      :is="demosplanUi.DpInput"
      id="interfaceFieldsToTransmit-input"
      v-model="currentValue"
      :data-cy="`${resourceType}:field`"
      :label="{
        text: label,
        tooltip
      }"
      :required="isInterfaceActivated"
      pattern="^.*\S-\S.*$"
      @blur="$emit('addonEvent:emit', { name: 'blur', payload: addonPayload })"
      @focus="handleFocus"
    />

    <!-- Pictogram section -->
    <component
      :is="$.components.MeinBerlinProcedurePictogram"
      :demosplan-ui="demosplanUi"
      :existing-pictogram="existingPictogram"
      :pictogram-alt-text="pictogramAltText"
      :pictogram-copyright="pictogramCopyright"
      :relationship-id="relationshipId"
    />
  </div>

  <component
    :is="demosplanUi.DpSelect"
    v-else
    id="interfaceFieldsToTransmit-select"
    v-model="currentValue"
    :data-cy="`${resourceType}:field`"
    :label="{
      text: label,
      tooltip
    }"
    :options="selectOptions"
    @select="onChange"
  />
</template>

<script>
import MeinBerlinProcedurePictogram from './MeinBerlinProcedurePictogram.vue'

export default {
  name: 'MeinBerlinAdditionalFields',

  components: {
    MeinBerlinProcedurePictogram
  },

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

    existingPictogram: {
      type: Object,
      required: false,
      default: null
    },

    isProcedureSettingsPage: {
      type: Boolean,
      required: false,
      default: false
    },

    organisationId: {
      type: String,
      required: false,
      default: ''
    },

    pictogramAltText: {
      type: String,
      required: false,
      default: ''
    },

    pictogramCopyright: {
      type: String,
      required: false,
      default: ''
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
      // Initialize without a default value
      currentValue: null,
      hasBerlinOrgaId: false,
      initValue: null,
      isCheckingBerlinOrgaId: true,
      isInterfaceActivated: false,
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

      // District codes for procedure mode
      districtOptions: [
        { label: Translator.trans('mein.berlin.district.gesamtstädtisch'), value: 'be' },
        { label: Translator.trans('mein.berlin.district.mitte'), value: 'mi' },
        { label: Translator.trans('mein.berlin.district.friedrichshain_kreuzberg'), value: 'fk' },
        { label: Translator.trans('mein.berlin.district.pankow'), value: 'pa' },
        { label: Translator.trans('mein.berlin.district.charlottenburg_wilmersdorf'), value: 'cw' },
        { label: Translator.trans('mein.berlin.district.spandau'), value: 'sp' },
        { label: Translator.trans('mein.berlin.district.steglitz_zehlendorf'), value: 'sz' },
        { label: Translator.trans('mein.berlin.district.tempelhof_schoeneberg'), value: 'ts' },
        { label: Translator.trans('mein.berlin.district.neukoelln'), value: 'nk' },
        { label: Translator.trans('mein.berlin.district.treptow_koepenick'), value: 'tk' },
        { label: Translator.trans('mein.berlin.district.marzahn_hellersdorf'), value: 'mh' },
        { label: Translator.trans('mein.berlin.district.lichtenberg'), value: 'li' },
        { label: Translator.trans('mein.berlin.district.reinickendorf'), value: 'rd' }
      ],

      // Mapping mein.berlin org ID -> district short code
      orgIdToDistrictCode: {
        '16': 'mi',
        '28': 'fk',
        '20': 'pa',
        '27': 'cw',
        '26': 'sp',
        '32': 'sz',
        '24': 'ts',
        '30': 'nk',
        '15': 'tk',
        '25': 'mh',
        '29': 'li',
        '31': 'rd'
      },

      relationshipKeyMapping: {
        orga: {
          attribute: 'meinBerlinOrganisationId',
          label: Translator.trans('mein.berlin.organisation.id'),
          resourceType: 'MeinBerlinAddonOrganisation',
          tooltip: Translator.trans('mein.berlin.organisation.id.tooltip')
        },
        procedure: {
          attribute: 'district',
          label: Translator.trans('mein.berlin.district.label'),
          resourceType: 'MeinBerlinAddonProcedureData',
          tooltip: Translator.trans('mein.berlin.district.tooltip')
        }
      }
    }
  },

  computed: {
    addonPayload () {
      const attributes = {}

      if (this.attribute) {
        // Only send a value if it's actually set
        if (this.currentValue !== null && this.currentValue !== '') {
          attributes[this.attribute] = this.currentValue.toString()
        } else if (this.initValue !== null && this.initValue !== '') {
          attributes[this.attribute] = this.initValue.toString()
        } else {
          // Don't set a value if nothing is selected
          attributes[this.attribute] = ''
        }
      }

      // Add isInterfaceActivated attribute for procedure relationship
      if (this.relationshipKey === 'procedure') {
        attributes.isInterfaceActivated = this.isInterfaceActivated
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

    isProcedureTransmitted () {
      const dplanId = this.item?.attributes?.dplanId
      return Boolean(dplanId)
    },

    label () {
      return this.relationshipKeyMapping[this.relationshipKey]?.label || ''
    },

    resourceType () {
      return this.relationshipKeyMapping[this.relationshipKey]?.resourceType || ''
    },

    tooltip () {
      return this.relationshipKeyMapping[this.relationshipKey]?.tooltip || ''
    },

    selectOptions () {
      return this.relationshipKey === 'procedure'
        ? this.districtOptions
        : this.options
    }
  },

  methods: {
    /**
     * Check if the organisation has a Berlin org ID configured
     * Only relevant for procedure settings page
     */
    async checkBerlinOrgaId () {
      // Skip check if not procedure page or no orga ID
      if (this.relationshipKey !== 'procedure' || !this.organisationId) {
        this.hasBerlinOrgaId = false
        this.isCheckingBerlinOrgaId = false
        return
      }

      try {
        const url = Routing.generate('api_resource_list', {
          resourceType: 'MeinBerlinAddonOrganisation'
        })

        const response = await this.demosplanUi.dpApi.get(url, {
          include: 'orga'
        })

        // Find the addon data for this organisation
        const orgaAddon = response.data.data.find(
          item => item.relationships?.orga?.data?.id === this.organisationId
        )

        // Check if Berlin orga ID is set (not null/empty)
        this.hasBerlinOrgaId = Boolean(
          orgaAddon?.attributes?.meinBerlinOrganisationId
        )
      } catch (error) {
        console.error('Error checking addon organisation ID:', error)
        this.hasBerlinOrgaId = false
      } finally {
        this.isCheckingBerlinOrgaId = false
      }
    },

    async autoSelectDistrict () {
      let meinBerlinOrgId = this.userMeinBerlinOrgId

      if (!meinBerlinOrgId && this.userOrgaId) {
        meinBerlinOrgId = await this.fetchMeinBerlinOrgId(this.userOrgaId)
      }

      if (!meinBerlinOrgId) {
        return
      }

      const key = String(meinBerlinOrgId).trim()
      const districtCode = this.orgIdToDistrictCode[key]

      if (districtCode) {
        this.$nextTick(() => {
          this.onChange(districtCode)
        })
      }
    },

    async autoSelectOrga () {
      let meinBerlinOrgId = this.userMeinBerlinOrgId

      if (!meinBerlinOrgId && this.userOrgaId) {
        meinBerlinOrgId = await this.fetchMeinBerlinOrgId(this.userOrgaId)
      }

      if (!meinBerlinOrgId) {
        return
      }

      const value = String(meinBerlinOrgId)

      this.$nextTick(() => {
        this.onChange(value)
      })
    },

    async fetchMeinBerlinOrgId (orgaId) {
      try {
        const url = Routing.generate('api_resource_list', {
          resourceType: 'MeinBerlinAddonOrganisation'
        })

        const response = await this.demosplanUi.dpApi.get(url, { include: 'orga' })

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
      this.isInterfaceActivated = false

      // Only set a value if one exists, otherwise keep it null/empty
      if (this.item?.attributes[this.attribute]) {
        const storedValue = this.item.attributes[this.attribute]
        this.currentValue = storedValue
        this.initValue = storedValue

        // Make sure the underlying <select> reflects the restored value
        this.syncNativeSelect()
      }

      // Load checkbox state for procedure relationship
      if (this.relationshipKey === 'procedure') {
        if (this.item?.attributes?.isInterfaceActivated !== undefined) {
          this.isInterfaceActivated = this.item.attributes.isInterfaceActivated
        }
      }
    },

    handleFocus () {
      const input = document.getElementById('interfaceFieldsToTransmit-input')

      if (input && input.classList.contains('is-invalid')) {
        input.classList.remove('is-invalid')
      }
    },
    /**
     * Force DpSelect to display the correct value via DOM manipulation
     *
     * This method directly manipulates the native <select> element to ensure the correct
     * option is displayed. This workaround is necessary because:
     *
     * 1. Addons cannot import @demos-europe/demosplan-ui components directly (externalized)
     * 2. Must use dynamic components: <component :is="demosplanUi.DpSelect">
     * 3. Vue's v-model with dynamic components has known reactivity issues
     * 4. DpSelect maintains internal state that gets out of sync when values are set programmatically
     * 5. No amount of prop/event manipulation fixes this due to runtime component resolution
     *
     * Alternative approaches that were tried and failed:
     * - Controlled component pattern (:value + @update:modelValue)
     * - Conditional rendering with v-if and readiness flags
     * - Various event handling strategies
     *
     * TODO: This can be removed if/when DpSelect's internal state management is improved
     * to handle programmatic value updates with dynamic component resolution.
     */
    syncNativeSelect () {
      this.$nextTick(() => {
        const select = this.$el.querySelector('select')

        if (select && this.currentValue !== null && this.currentValue !== '') {
          select.value = this.currentValue
        }
      })
    },

    onChange (value) {
      // Explicitly update currentValue when input changes
      this.currentValue = value
      this.$emit('addonEvent:emit', { name: 'selected', payload: this.addonPayload })
      this.syncNativeSelect()
    },

    onCheckboxChange (value) {
      this.isInterfaceActivated = value
      this.$emit('addonEvent:emit', { name: 'change', payload: this.addonPayload })
    }
  },

  mounted () {
    if (!this.additionalFieldOptions.length) {
      // Fetch resource list AND check Berlin org ID in parallel
      Promise.all([
        this.fetchResourceList(),
        this.checkBerlinOrgaId()
      ]).then(() => {
          this.$emit('addonEvent:emit', { name: 'resourceList:loaded', payload: this.list })
          this.getItemByRelationshipId()

          if (!this.currentValue && this.relationshipKey === 'procedure') {
            this.autoSelectDistrict()
          } else if (!this.currentValue && this.relationshipKey === 'orga') {
            this.autoSelectOrga()
          }
        })
    } else {
      this.list = this.additionalFieldOptions
      this.getItemByRelationshipId()
      this.checkBerlinOrgaId()

      if (!this.currentValue && this.relationshipKey === 'procedure') {
        this.autoSelectDistrict()
      } else if (!this.currentValue && this.relationshipKey === 'orga') {
        this.autoSelectOrga()
      }
    }
  }
}
</script>
