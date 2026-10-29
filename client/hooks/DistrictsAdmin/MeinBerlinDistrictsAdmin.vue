<template>
  <div data-cy="MeinBerlinAddonDistrict:page">
    <h1>
      {{ Translator.trans('mein.berlin.districts.heading') }}
    </h1>

    <p class="mb-4">
      {{ Translator.trans('mein.berlin.districts.explanation') }}
    </p>

    <component
      :is="demosplanUi.DpLoading"
      v-if="isLoading"
      class="u-mt"
    />

    <!-- Replace with DpDataTable once the addon no longer bundles its own vue; until then, content passed into slots of
    core components is not updated -->
    <table
      v-if="!isLoading"
      class="c-data-table"
      data-cy="MeinBerlinAddonDistrict:table"
    >
      <colgroup>
        <col class="w-1/3">
        <col class="w-1/3">
        <col>
        <col class="w-[50px]">
      </colgroup>
      <thead>
        <tr>
          <th>
            {{ Translator.trans('mein.berlin.districts.column.organisation') }}
          </th>
          <th>
            {{ Translator.trans('mein.berlin.districts.column.id') }}
          </th>
          <th>
            {{ Translator.trans('mein.berlin.districts.column.orgas') }}
          </th>
          <th>
            <span class="sr-only">{{ Translator.trans('actions') }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in rows"
          :key="row.id"
          :data-cy="`MeinBerlinAddonDistrict:row:${row.districtCode}`"
        >
          <td>
            {{ row.name }}
          </td>
          <td>
            <template v-if="row.id !== editingId">
              {{ row.initValue || '–' }}
            </template>
            <template v-else>
              <component
                :is="demosplanUi.DpInput"
                :id="`meinBerlinDistrictId-${row.districtCode}`"
                :disabled="row.isSaving"
                :invalid="!isValid(row)"
                :label="{ text: row.name, hide: true }"
                :model-value="row.value"
                :placeholder="Translator.trans('mein.berlin.districts.id.empty')"
                pattern="[1-9][0-9]*"
                @update:model-value="value => onInput(row, value)"
              />
              <p
                v-if="!isValid(row)"
                class="mt-1 color-message-severe-text"
              >
                {{ Translator.trans('mein.berlin.error.update.organisation.id.invalid') }}
              </p>
            </template>
          </td>
          <td>
            {{ row.usedByOrganisations }}
          </td>
          <td class="text-right whitespace-nowrap">
            <button
              v-if="row.id !== editingId"
              :aria-label="Translator.trans('item.edit')"
              class="btn--blank o-link--default"
              :data-cy="`MeinBerlinAddonDistrict:edit:${row.districtCode}`"
              type="button"
              @click="edit(row)"
            >
              <component
                :is="demosplanUi.DpIcon"
                aria-hidden="true"
                icon="edit"
              />
            </button>
            <template v-else>
              <button
                :aria-label="Translator.trans('save')"
                class="btn--blank o-link--default inline-block mr-1"
                :data-cy="`MeinBerlinAddonDistrict:save:${row.districtCode}`"
                :disabled="row.isSaving || !isValid(row)"
                type="button"
                @click="save(row)"
              >
                <component
                  :is="demosplanUi.DpIcon"
                  aria-hidden="true"
                  icon="check"
                />
              </button>
              <button
                :aria-label="Translator.trans('abort')"
                class="btn--blank o-link--default inline-block"
                :data-cy="`MeinBerlinAddonDistrict:abort:${row.districtCode}`"
                :disabled="row.isSaving"
                type="button"
                @click="abort(row)"
              >
                <component
                  :is="demosplanUi.DpIcon"
                  aria-hidden="true"
                  icon="xmark"
                />
              </button>
            </template>
          </td>
        </tr>
      </tbody>
    </table>

    <component
      :is="demosplanUi.DpConfirmDialog"
      :confirm-button-text="confirmDialogButtonText"
      data-cy="MeinBerlinAddonDistrict:confirm"
      :decline-button-text="Translator.trans('back.to.edit')"
      allow-html
      :checkbox-text="confirmDialogCheckboxText"
      :header="confirmDialogHeader"
      icon="warning"
      :message="confirmDialogMessage"
      @vue:mounted="({ component }) => confirmDialog = component.proxy"
    />
  </div>
</template>

<script>
import { escapeHtml } from '../escapeHtml'

export default {
  name: 'MeinBerlinDistrictsAdmin',

  props: {
    demosplanUi: {
      type: Object,
      required: true
    }
  },

  data () {
    return {
      // Never empty: DpModal decides once whether it has a header
      confirmDialogHeader: Translator.trans('mein.berlin.districts.change.confirm'),
      confirmDialogButtonText: Translator.trans('mein.berlin.districts.change.button'),
      // Requires a demosplan-ui version with the checkboxText prop of DpConfirmDialog
      confirmDialogCheckboxText: '',
      confirmDialogMessage: '',
      // Only one row is editable at a time
      editingId: null,
      isLoading: true,
      rows: []
    }
  },

  methods: {
    abort (row) {
      this.resetRow(row.id)
      this.editingId = null
    },

    createRow (id, { communicatedProcedures, districtCode, meinBerlinOrganisationId, name, officeName, usedByOrganisations }) {
      return {
        // Only read before saving, as it is only shown in the confirm dialog
        assignedOrganisations: { count: 0, names: [] },
        communicatedProcedures: communicatedProcedures ?? { count: 0, names: [] },
        districtCode,
        id,
        isSaving: false,
        // The saved value is kept to find out if the row was changed
        initValue: meinBerlinOrganisationId ?? '',
        // The ID belongs to the organisation at mein.berlin.de, the district is only where plans are located
        name: officeName || name,
        usedByOrganisations,
        value: meinBerlinOrganisationId ?? ''
      }
    },

    edit (row) {
      if (this.editingId) {
        this.resetRow(this.editingId)
      }

      this.editingId = row.id
    },

    fetchDistricts () {
      // The number of organisations and the procedures that were already communicated are no default fields
      const url = Routing.generate('api_resource_list', {
        fields: {
          MeinBerlinAddonDistrict: [
            'communicatedProcedures',
            'districtCode',
            'meinBerlinOrganisationId',
            'name',
            'officeName',
            'usedByOrganisations'
          ].join()
        },
        resourceType: 'MeinBerlinAddonDistrict',
        sort: 'officeName'
      })

      return this.demosplanUi.dpApi.get(url)
        .then(response => {
          this.rows = response.data.data.map(({ attributes, id }) => this.createRow(id, attributes))
        })
        .catch(err => console.error(err))
        .finally(() => {
          this.isLoading = false
        })
    },

    /**
     * The message is rendered as HTML, so the names are escaped.
     */
    getWarningMessage (row) {
      const count = row.usedByOrganisations
      const isReset = row.value === ''
      const hasProcedures = this.hasCommunicatedProcedures(row)
      const name = escapeHtml(row.name)
      let organisations = isReset
        ? Translator.trans('mein.berlin.districts.change.reset.organisations', { count })
        : Translator.trans('mein.berlin.districts.change.organisations', { count })

      if (hasProcedures) {
        organisations += ' ' + Translator.trans('mein.berlin.districts.change.warning', { count })
      }

      const paragraphs = [
        `<strong>${isReset
          ? Translator.trans('mein.berlin.districts.change.reset', { name })
          : Translator.trans('mein.berlin.districts.change.intro', { name })}</strong>`,
        organisations
      ]

      // Without an ID nothing is transmitted anymore, no matter if procedures were transmitted before
      if (isReset) {
        paragraphs.push(Translator.trans('mein.berlin.districts.change.reset.consequence', { count }))
      } else if (hasProcedures) {
        paragraphs.push(Translator.trans('mein.berlin.districts.change.consequence'))
      }

      const html = paragraphs.map(text => `<p class="mb-2">${text}</p>`)

      // The organisations lose their ID, so the admin has to know which ones to inform or give a new ID
      if (isReset) {
        const { count: organisationCount, names } = row.assignedOrganisations

        html.push(this.getNameList(
          Translator.trans('mein.berlin.districts.reset.organisations.list', { count: organisationCount }),
          names,
          organisationCount
        ))
      }

      if (hasProcedures) {
        const { count: procedureCount, names } = row.communicatedProcedures

        html.push(this.getNameList(
          Translator.trans('mein.berlin.organisation.id.change.procedures', { count: procedureCount, names: '' }).trim(),
          names,
          procedureCount
        ))
      }

      return html.join('')
    },

    /**
     * A bold heading and a list of at most the delivered names, "…" stands for the rest.
     */
    getNameList (heading, names, count) {
      const items = [...names.map(escapeHtml), ...(count > names.length ? ['…'] : [])]

      return `<p class="mb-2"><strong>${heading}</strong></p>`
        + `<ul class="list-disc pl-4 mb-2">${items.map(item => `<li>${item}</li>`).join('')}</ul>`
    },

    isChanged (row) {
      return row.value !== row.initValue
    },

    /**
     * The organisations and procedures that use the saved ID are only affected if the ID is changed.
     */
    needsConfirmation (row) {
      return this.isChanged(row) && (row.usedByOrganisations > 0 || this.hasCommunicatedProcedures(row))
    },

    isValid (row) {
      return /^([1-9]\d*)?$/.test(row.value)
    },

    /**
     * Changing the ID releases the procedures that were already communicated.
     */
    hasCommunicatedProcedures (row) {
      return this.isChanged(row) && row.communicatedProcedures.count > 0
    },

    getRow (id) {
      return this.rows.find(row => row.id === id)
    },

    /**
     * A row is replaced by a changed copy, so the page is drawn again for sure.
     */
    updateRow (id, changes) {
      this.rows = this.rows.map(row => row.id === id ? { ...row, ...changes } : row)
    },

    resetRow (id) {
      this.updateRow(id, { value: this.getRow(id).initValue })
    },

    onInput (row, value) {
      this.updateRow(row.id, { value: value.trim() })
    },

    /**
     * The numbers of organisations and procedures may have changed since the page was loaded, so they are read
     * again before a row is saved. The warning must not depend on outdated data.
     */
    refreshWarningData (id) {
      const url = Routing.generate('api_resource_get', {
        fields: {
          MeinBerlinAddonDistrict: ['assignedOrganisations', 'communicatedProcedures', 'usedByOrganisations'].join()
        },
        resourceId: id,
        resourceType: 'MeinBerlinAddonDistrict'
      })

      return this.demosplanUi.dpApi.get(url).then(response => {
        const { assignedOrganisations, communicatedProcedures, usedByOrganisations } = response.data.data.attributes

        this.updateRow(id, {
          assignedOrganisations: assignedOrganisations ?? { count: 0, names: [] },
          communicatedProcedures: communicatedProcedures ?? { count: 0, names: [] },
          usedByOrganisations: usedByOrganisations ?? 0
        })
      })
    },

    async save (clickedRow) {
      const id = clickedRow.id

      if (!this.isChanged(this.getRow(id))) {
        this.editingId = null

        return
      }

      this.updateRow(id, { isSaving: true })

      try {
        await this.refreshWarningData(id)

        const row = this.getRow(id)
        if (this.needsConfirmation(row)) {
          const isReset = row.value === ''
          this.confirmDialogHeader = isReset
            ? Translator.trans('mein.berlin.districts.reset.confirm')
            : Translator.trans('mein.berlin.districts.change.confirm')
          this.confirmDialogButtonText = isReset
            ? Translator.trans('mein.berlin.districts.reset.button')
            : Translator.trans('mein.berlin.districts.change.button')
          this.confirmDialogMessage = this.getWarningMessage(row)
          this.confirmDialogCheckboxText = this.hasCommunicatedProcedures(row)
            ? Translator.trans('mein.berlin.districts.change.procedures.confirm')
            : ''

          if (!await this.confirmDialog.open()) {
            return
          }
        }

        await this.demosplanUi.dpApi({
          data: {
            data: {
              attributes: { meinBerlinOrganisationId: row.value },
              id,
              type: 'MeinBerlinAddonDistrict'
            }
          },
          headers: {
            ...(dplan.csrfToken && { 'x-csrf-token': dplan.csrfToken })
          },
          method: 'PATCH',
          url: Routing.generate('api_resource_update', {
            resourceId: id,
            resourceType: 'MeinBerlinAddonDistrict'
          })
        })

        dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        this.editingId = null
        // The organisations and procedures that use the ID have to be read again
        await this.fetchDistricts()
      } catch (error) {
        // The reason is shown by the messages of the response, the saved value is restored
        console.error('[MeinBerlin] Failed to save the organisation ID of a district:', error)
        dplan.notify.notify('error', Translator.trans('error.save'))
        this.resetRow(id)
      } finally {
        this.updateRow(id, { isSaving: false })
      }
    }
  },

  created () {
    // Not reactive and not a $ref: refs of the addon's own Vue copy are not resolved by core's Vue
    this.confirmDialog = null
  },

  mounted () {
    this.fetchDistricts()
  }
}
</script>
