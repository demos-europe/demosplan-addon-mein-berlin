<template>
  <div>
    <component
      :is="demosplanUi.DpSelect"
      id="interfaceFieldsToTransmit-orgSelect"
      v-model="currentValue"
      :data-cy="`${resourceType}:field`"
      :label="{
        text: label,
        tooltip
      }"
      :options="options"
      @select="onChange"
    />

    <div v-if="needsConfirmation">
      <component
        :is="demosplanUi.DpInlineNotification"
        class="mt-4"
        :message="warningMessage"
        type="warning"
      />

      <component
        :is="demosplanUi.DpCheckbox"
        id="interfaceFieldsToTransmit-orgIdChangeConfirm"
        :checked="isConfirmed"
        class="mt-2"
        :label="{ text: confirmLabel }"
        @change="isConfirmed = $event"
      />
    </div>
  </div>
</template>

<script>
import { fetchMeinBerlinOrganisationId } from './fetchMeinBerlinOrganisationId'
import { escapeHtml } from '../escapeHtml'
import { fetchDistricts } from './fetchDistricts'

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
      isConfirmed: false,
      initValue: null,
      item: null,
      list: null,
      districts: []
    }
  },

  computed: {
    addonPayload () {
      const attributes = {}

      // A change that is not valid or not yet confirmed is not sent, the saved value stays as it is
      attributes.meinBerlinOrganisationId = this.isChangeBlocked ? this.savedValue : this.effectiveValue

      return {
        attributes,
        id: this.item ? this.item.id : '',
        initValue: this.item ? this.initValue : '',
        resourceType: this.resourceType,
        value: this.currentValue,
        url: this.item ? 'api_resource_update' : 'api_resource_create'
      }
    },

    confirmLabel () {
      return Translator.trans('mein.berlin.organisation.id.change.confirm')
    },

    /**
     * The already communicated procedures of the organisation, as delivered with the organisation relation.
     */
    communicatedProcedures () {
      return this.item?.attributes?.communicatedProcedures ?? { count: 0, names: [] }
    },

    /**
     * The value that is currently chosen. The IDs themselves are changed on the page of the districts.
     */
    effectiveValue () {
      return String(this.currentValue ?? this.savedValue)
    },

    isChangeBlocked () {
      return this.needsConfirmation && !this.isConfirmed
    },

    /**
     * Choosing another ID releases procedures that were already communicated, so the user has to confirm.
     */
    needsConfirmation () {
      return this.communicatedProcedures.count > 0 && this.effectiveValue !== this.savedValue
    },

    savedValue () {
      return String(this.initValue ?? '')
    },

    warningMessage () {
      const { count, names } = this.communicatedProcedures
      const procedures = Translator.trans('mein.berlin.organisation.id.change.procedures', {
        count,
        names: names.map(escapeHtml).join(', ') + (count > names.length ? ', …' : '')
      })

      return [
        Translator.trans('mein.berlin.organisation.id.change.warning'),
        Translator.trans('mein.berlin.organisation.id.change.consequence'),
        procedures
      ].join('<br><br>')
    },

    label () {
      return Translator.trans('mein.berlin.organisation.id')
    },

    /**
     * One option per district that has a mein.berlin.de organisation ID, labelled with the ID in front,
     * e.g. "29 – Lichtenberg". The IDs are maintained in the district catalog.
     */
    options () {
      const options = this.districts
        .filter(({ meinBerlinOrganisationId }) => meinBerlinOrganisationId)
        .sort((a, b) => a.name.localeCompare(b.name))
        .map(({ meinBerlinOrganisationId, name }) => ({
          label: `${meinBerlinOrganisationId} – ${name}`,
          value: meinBerlinOrganisationId
        }))

      // An ID saved for the organisation stays selectable even if no district has it (anymore)
      const savedId = this.initValue
      if (savedId && !options.some(option => option.value === savedId)) {
        options.push({ label: savedId, value: savedId })
      }

      return options
    },

    resourceType () {
      return 'MeinBerlinAddonOrganisation'
    },

    tooltip () {
      return Translator.trans('mein.berlin.organisation.id.tooltip')
    }
  },

  watch: {
    isChangeBlocked () {
      // The payload depends on the confirmation, so the parent is informed when it changes
      this.emitSelected()
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
      // communicatedProcedures is not part of the default fields, as it takes queries for each relation
      const url = Routing.generate('api_resource_list', {
        resourceType: this.resourceType,
        fields: {
          [this.resourceType]: ['meinBerlinOrganisationId', 'communicatedProcedures', this.relationshipKey].join()
        },
        include: this.relationshipKey
      })

      return this.demosplanUi.dpApi.get(url)
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
        el => el.relationships?.[this.relationshipKey]?.data?.id === this.relationshipId
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

        if (select) {
          // Reset to the empty (placeholder) state if no value is chosen
          select.value = (this.currentValue !== null && this.currentValue !== '') ? this.currentValue : ''
        }
      })
    },

    onChange (value) {
      this.currentValue = String(value ?? '').trim()
      // A new value has to be confirmed again
      this.isConfirmed = false
      this.emitSelected()
      this.syncNativeSelect()
    },

    emitSelected () {
      this.$emit('addonEvent:emit', { name: 'selected', payload: this.addonPayload })
    }
  },

  mounted() {
    fetchDistricts(this.demosplanUi).then(districts => { this.districts = districts })

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
