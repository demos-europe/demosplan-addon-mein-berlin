<template>
  <div>
    <h3>
      {{ Translator.trans('mein.berlin.interface') }}
    </h3>

    <component
      :is="demosplanUi.DpInlineNotification"
      v-if="!isCheckingBerlinOrgaId && (isProcedureTransmitted || !hasBerlinOrgaId)"
      :message="isProcedureTransmitted
        ? Translator.trans('mein.berlin.procedure.already.transmitted')
        : Translator.trans('mein.berlin.organisation.id.missing.transmission.not.possible')"
      class="mb-4"
      type="info"
    />

    <component
      :is="demosplanUi.DpCheckbox"
      id="interfaceFieldsToTransmit-checkbox"
      :checked="isInterfaceActivated"
      :disabled="isProcedureTransmitted || !hasBerlinOrgaId"
      :label="{ text: Translator.trans('mein.berlin.interface.activation') }"
      class="mt-4 mb-4"
      @change="onCheckboxChange"
    />

    <component
      :is="demosplanUi.DpSelect"
      id="interfaceFieldsToTransmit-districtSelect"
      v-model="currentValue"
      :label="{ text: label, tooltip }"
      :options="districtOptions"
      :required="isInterfaceActivated"
      @select="onChange"
    />

    <component
      :is="$options.components.MeinBerlinProcedurePictogram"
      :demosplan-ui="demosplanUi"
      :existing-pictogram="existingPictogram"
      :pictogram-alt-text="pictogramAltText"
      :pictogram-copyright="pictogramCopyright"
      :relationship-id="relationshipId"
    />
  </div>
</template>

<script>
import MeinBerlinProcedurePictogram from './MeinBerlinProcedurePictogram.vue'
import { fetchMeinBerlinOrganisationId } from './fetchMeinBerlinOrganisationId'

// District options for Berlin districts
const DISTRICT_OPTIONS = [
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
]

// Mapping mein.berlin organization ID to district short code
const ORG_ID_TO_DISTRICT_CODE = {
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
}

export default {
  name: 'MeinBerlinProcedureFields',

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
      hasBerlinOrgaId: false,
      initValue: null,
      isCheckingBerlinOrgaId: true,
      isInterfaceActivated: false,
      item: null,
      list: null,
      districtOptions: DISTRICT_OPTIONS,
      orgIdToDistrictCode: ORG_ID_TO_DISTRICT_CODE
    }
  },

  computed: {
    addonPayload () {
      const attributes = {}

      // Use current value, fallback to init value, or empty string
      const value = this.currentValue ?? this.initValue ?? ''
      attributes.district = value ? String(value) : ''

      // Add isInterfaceActivated attribute for procedure relationship
      attributes.isInterfaceActivated = this.isInterfaceActivated

      return {
        attributes,
        id: this.item ? this.item.id : '',
        initValue: this.item ? this.initValue : '',
        resourceType: 'MeinBerlinAddonProcedureData',
        value: this.currentValue,
        url: this.item ? 'api_resource_update' : 'api_resource_create'
      }
    },

    isProcedureTransmitted () {
      const bplanId = this.item?.attributes?.bplanId
      return Boolean(bplanId)
    },

    label () {
      return Translator.trans('mein.berlin.district.label')
    },

    tooltip () {
      return Translator.trans('mein.berlin.district.tooltip')
    }
  },

  methods: {
    /**
     * Check if the organisation has a Berlin org ID configured
     */
    async checkBerlinOrgaId () {
      if (!this.organisationId) {
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

        this.hasBerlinOrgaId = Boolean(orgaAddon?.attributes?.meinBerlinOrganisationId)
      } catch (e) {
        console.error(e)
        this.hasBerlinOrgaId = false
      } finally {
        this.isCheckingBerlinOrgaId = false
      }
    },

    async autoSelectDistrict () {
      let meinBerlinOrgId = this.userMeinBerlinOrgId

      if (!meinBerlinOrgId && this.userOrgaId) {
        meinBerlinOrgId = await fetchMeinBerlinOrganisationId(
          this.demosplanUi,
          this.userOrgaId
        )
      }

      if (!meinBerlinOrgId) return

      const code = this.orgIdToDistrictCode[String(meinBerlinOrgId).trim()]

      if (code) {
        this.$nextTick(() => this.onChange(code))
      }
    },

    fetchResourceList () {
      const url = Routing.generate('api_resource_list', { resourceType: 'MeinBerlinAddonProcedureData' })

      return this.demosplanUi.dpApi.get(url, { include: 'procedure' })
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
        el => el.relationships.procedure.data.id === this.relationshipId
      ) || null

      // Reset if no item
      this.currentValue = ''
      this.initValue = null
      this.isInterfaceActivated = false

      // Only set a value if one exists
      if (this.item?.attributes?.district) {
        const storedValue = this.item.attributes.district
        this.currentValue = storedValue
        this.initValue = storedValue

        // Make sure the underlying <select> reflects the restored value
        this.syncNativeSelect()
      }

      // Load checkbox state
      if (this.item?.attributes?.isInterfaceActivated !== undefined) {
        this.isInterfaceActivated = this.item.attributes.isInterfaceActivated
      }
    },

    syncNativeSelect () {
      this.$nextTick(() => {
        const select = this.$el.querySelector('select')

        if (select) {
          // Set value if exists, otherwise reset to empty (placeholder)
          select.value = (this.currentValue !== null && this.currentValue !== '') ? this.currentValue : ''
        }
      })
    },

    onChange (value) {
      // Prevent selection if user doesn't have Berlin org ID
      if (!this.hasBerlinOrgaId) {
        dplan.notify.error(Translator.trans('mein.berlin.organisation.id.missing'))
        this.currentValue = null
        this.$nextTick(() => this.syncNativeSelect())
        return
      }

      this.currentValue = value
      this.$emit('addonEvent:emit', { name: 'selected', payload: this.addonPayload })
      this.syncNativeSelect()
    },

    onCheckboxChange (value) {
      this.isInterfaceActivated = value
      this.$emit('addonEvent:emit', { name: 'change', payload: this.addonPayload })
    }
  },

  mounted() {
    // Case: options are already provided → no need to fetch from backend
    if (this.additionalFieldOptions.length > 0) {
      this.list = this.additionalFieldOptions
      this.getItemByRelationshipId()
      this.checkBerlinOrgaId()

      if (!this.currentValue) {
        this.autoSelectDistrict()
      }

      return
    }

    // Case: options NOT provided → fetch list + orgaId state
    Promise.all([
      this.fetchResourceList(),
      this.checkBerlinOrgaId()
    ]).then(() => {
      this.$emit('addonEvent:emit', {
        name: 'resourceList:loaded',
        payload: this.list
      })

      this.getItemByRelationshipId()

      if (!this.currentValue) {
        this.autoSelectDistrict()
      }
    })
  }
}
</script>
