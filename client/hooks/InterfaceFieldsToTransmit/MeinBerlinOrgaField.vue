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
      :show-placeholder="!isValueRemovable"
      @select="onChange"
    />

    <component
      :is="demosplanUi.DpConfirmDialog"
      :checkbox-text="confirmation.checkboxText"
      :confirm-button-text="confirmation.confirmButtonText"
      :decline-button-text="Translator.trans('back.to.edit')"
      :header="confirmation.header"
      :message="confirmation.message"
      data-cy="MeinBerlinAddonOrganisation:confirm"
      icon="warning"
      allow-html
      @vue:mounted="({ component }) => confirmDialog = component.proxy"
    />
  </div>
</template>

<script>
import { fetchMeinBerlinOrganisationId } from './fetchMeinBerlinOrganisationId'
import { nameListHtml } from '../nameListHtml'
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

    /**
     * Offers an empty entry, so the ID of the organisation can be removed
     */
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
      districts: []
    }
  },

  computed: {
    addonPayload () {
      return {
        attributes: { meinBerlinOrganisationId: this.effectiveValue },
        // Core awaits it before saving the organisation, false cancels the save
        beforeSave: this.confirmChange,
        id: this.item ? this.item.id : '',
        initValue: this.item ? this.initValue : '',
        resourceType: this.resourceType,
        value: this.currentValue,
        url: this.item ? 'api_resource_update' : 'api_resource_create'
      }
    },

    /**
     * The already communicated procedures of the organization, only known after refreshItem.
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

    /**
     * Choosing another ID releases procedures that were already communicated, so the user has to confirm.
     */
    needsConfirmation () {
      return this.communicatedProcedures.count > 0 && this.effectiveValue !== this.savedValue
    },

    savedValue () {
      return String(this.initValue ?? '')
    },

    /**
     * Same texts as the confirmation on the districts page. The message is rendered as HTML, so the names are escaped.
     * Never without a header: DpModal decides once whether it has one.
     */
    confirmation () {
      const { count, names } = this.communicatedProcedures
      const isReset = this.effectiveValue === ''
      const paragraphs = [
        `<strong>${Translator.trans(isReset ? 'mein.berlin.organisation.id.reset.intro' : 'mein.berlin.organisation.id.change.intro')}</strong>`,
        Translator.trans('mein.berlin.districts.change.warning', { count: 1 }),
        isReset
          ? Translator.trans('mein.berlin.districts.change.reset.consequence', { count: 1 })
          : Translator.trans('mein.berlin.organisation.id.change.consequence')
      ]
      const procedureList = nameListHtml(
        Translator.trans('mein.berlin.organisation.id.change.procedures', { count, names: '' }).trim(),
        names,
        count
      )

      return {
        checkboxText: Translator.trans('mein.berlin.districts.change.procedures.confirm'),
        confirmButtonText: Translator.trans(isReset ? 'mein.berlin.districts.reset.button' : 'mein.berlin.districts.change.button'),
        header: Translator.trans(isReset ? 'mein.berlin.districts.reset.confirm' : 'mein.berlin.districts.change.confirm'),
        message: paragraphs.map(text => `<p class="mb-2">${text}</p>`).join('') + procedureList
      }
    },

    label () {
      return Translator.trans('mein.berlin.organisation.id')
    },

    /**
     * One option per district that has a mein.berlin.de organisation ID, labelled with the ID in front and the name
     * of the office, e.g. "29 – Bezirksamt Lichtenberg". The IDs are maintained in the district catalog.
     */
    options () {
      const options = this.districts
        .filter(({ meinBerlinOrganisationId }) => meinBerlinOrganisationId)
        .map(({ meinBerlinOrganisationId, name, officeName }) => ({
          displayName: officeName || name,
          id: meinBerlinOrganisationId
        }))
        // Sorted by the displayed name, the office name differs from the district name (e.g. the Senatsverwaltung)
        .sort((a, b) => a.displayName.localeCompare(b.displayName, 'de'))
        .map(({ displayName, id }) => ({ label: `${id} – ${displayName}`, value: id }))

      // An ID saved for the organisation stays selectable even if no district has it (anymore)
      const savedId = this.initValue
      if (savedId && !options.some(option => option.value === savedId)) {
        options.push({ label: savedId, value: savedId })
      }

      if (this.isValueRemovable) {
        options.unshift({ label: Translator.trans('mein.berlin.districts.id.empty'), value: '' })
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
      // communicatedProcedures takes queries for each relation, so it is only read for the saved organization (refreshItem)
      const url = Routing.generate('api_resource_list', {
        resourceType: this.resourceType,
        fields: {
          [this.resourceType]: ['meinBerlinOrganisationId', this.relationshipKey].join()
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
      this.emitSelected()
      this.syncNativeSelect()
    },

    /**
     * The saved ID and the communicated procedures are read right before asking, they are not part of the list and may
     * have changed, e.g. by an earlier save. Without them the warning can't be shown, so the save is canceled.
     *
     * @return {Promise<boolean>} whether the organization may be saved
     */
    async confirmChange () {
      try {
        await this.refreshItem()
      } catch (error) {
        console.error('[MeinBerlin] Failed to refresh the organisation:', error)
        dplan.notify.notify('error', Translator.trans('error.api.generic'))

        return false
      }

      // Core skips the addon request if the payload has neither a value nor a saved value, so it gets the reloaded saved value
      this.emitSelected()

      return this.needsConfirmation ? this.confirmDialog.open() : true
    },

    refreshItem () {
      if (!this.item) {
        return Promise.resolve()
      }

      const url = Routing.generate('api_resource_get', {
        fields: {
          [this.resourceType]: ['communicatedProcedures', 'meinBerlinOrganisationId'].join()
        },
        resourceId: this.item.id,
        resourceType: this.resourceType
      })

      return this.demosplanUi.dpApi.get(url)
        .then(response => {
          const { attributes } = response.data.data

          this.item = { ...this.item, attributes: { ...this.item.attributes, ...attributes } }
          this.initValue = attributes.meinBerlinOrganisationId ?? null
        })
    },

    emitSelected () {
      this.$emit('addonEvent:emit', { name: 'selected', payload: this.addonPayload })
    }
  },

  created () {
    // Not reactive and not a $ref: refs of the addon's own Vue copy are not resolved by core's Vue
    this.confirmDialog = null
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
