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

    <div
      v-for="row in rowsWithWarning"
      :key="`warning-${row.id}`"
      class="mb-4"
      :data-cy="`MeinBerlinAddonDistrict:warning:${row.districtCode}`"
    >
      <p class="font-semibold mb-1">
        {{ row.name }}
      </p>
      <component
        :is="demosplanUi.DpInlineNotification"
        :message="getWarningMessage(row)"
        :type="needsConfirmation(row) ? 'warning' : 'info'"
      />
      <component
        :is="demosplanUi.DpCheckbox"
        v-if="needsConfirmation(row)"
        :id="`meinBerlinDistrictConfirm-${row.districtCode}`"
        :checked="row.isConfirmed"
        class="mt-2"
        :label="{ text: Translator.trans('mein.berlin.organisation.id.change.confirm') }"
        @change="checked => updateRow(row.id, { isConfirmed: checked })"
      />
    </div>

    <table
      v-if="!isLoading"
      class="w-full text-left"
    >
      <thead>
        <tr>
          <th class="p-2 w-1/3">
            {{ Translator.trans('mein.berlin.districts.column.district') }}
          </th>
          <th class="p-2 w-1/3">
            {{ Translator.trans('mein.berlin.districts.column.id') }}
          </th>
          <th class="p-2">
            {{ Translator.trans('mein.berlin.districts.column.orgas') }}
          </th>
          <th class="p-2" />
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="row in rows"
          :key="row.id"
          :data-cy="`MeinBerlinAddonDistrict:row:${row.districtCode}`"
        >
          <td class="p-2">
            {{ row.name }}
          </td>
          <td class="p-2">
            <component
              :is="demosplanUi.DpInput"
              :id="`meinBerlinDistrictId-${row.districtCode}`"
              :invalid="!isValid(row)"
              :label="{ text: row.name, hide: true }"
              :model-value="row.value"
              :placeholder="Translator.trans('mein.berlin.districts.id.empty')"
              pattern="[0-9]*"
              @update:model-value="value => onInput(row, value)"
            />
            <p
              v-if="!isValid(row)"
              class="mt-1 color-message-severe-text"
            >
              {{ Translator.trans('mein.berlin.error.update.organisation.id.invalid') }}
            </p>
          </td>
          <td class="p-2">
            {{ row.usedByOrganisations }}
          </td>
          <td class="p-2">
            <component
              :is="demosplanUi.DpButton"
              :busy="row.isSaving"
              :data-cy="`MeinBerlinAddonDistrict:save:${row.districtCode}`"
              :disabled="!isSavable(row)"
              :text="Translator.trans('save')"
              type="button"
              @click="save(row)"
            />
          </td>
        </tr>
      </tbody>
    </table>
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
      isLoading: true,
      rows: []
    }
  },

  computed: {
    /**
     * The rows whose change affects organisations or procedures. Their warnings are shown above the table.
     */
    rowsWithWarning () {
      return this.rows.filter(row => this.isWarningShown(row))
    }
  },

  methods: {
    createRow (id, { communicatedProcedures, districtCode, meinBerlinOrganisationId, name, usedByOrganisations }) {
      return {
        communicatedProcedures: communicatedProcedures ?? { count: 0, names: [] },
        districtCode,
        id,
        isConfirmed: false,
        isSaving: false,
        // The saved value is kept to find out if the row was changed
        initValue: meinBerlinOrganisationId ?? '',
        name,
        usedByOrganisations,
        value: meinBerlinOrganisationId ?? ''
      }
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
            'usedByOrganisations'
          ].join()
        },
        resourceType: 'MeinBerlinAddonDistrict',
        sort: 'name'
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

    getWarningMessage (row) {
      const count = row.usedByOrganisations
      const parts = [
        row.value === ''
          ? Translator.trans('mein.berlin.districts.change.reset', { count })
          : Translator.trans('mein.berlin.districts.change.organisations', { count })
      ]

      if (this.needsConfirmation(row)) {
        const { count: procedureCount, names } = row.communicatedProcedures

        parts.push(
          Translator.trans('mein.berlin.organisation.id.change.warning'),
          Translator.trans('mein.berlin.organisation.id.change.consequence'),
          Translator.trans('mein.berlin.organisation.id.change.procedures', {
            count: procedureCount,
            names: names.map(escapeHtml).join(', ') + (procedureCount > names.length ? ', …' : '')
          })
        )
      }

      return parts.join('<br><br>')
    },

    isChanged (row) {
      return row.value !== row.initValue
    },

    /**
     * The organisations and procedures that use the saved ID are only affected if the ID is changed.
     */
    isWarningShown (row) {
      return this.isChanged(row) && (row.usedByOrganisations > 0 || this.needsConfirmation(row))
    },

    /**
     * The button is active as long as nothing prevents the save, an unchanged row is answered when it is clicked.
     */
    isSavable (row) {
      return this.isValid(row) && (!this.needsConfirmation(row) || row.isConfirmed)
    },

    isValid (row) {
      return /^\d*$/.test(row.value)
    },

    /**
     * Changing the ID releases the procedures that were already communicated, so the user has to confirm.
     */
    needsConfirmation (row) {
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

    /**
     * Brings the warning of a row into the visible area of the page.
     */
    showWarning (row) {
      this.$nextTick(() => {
        this.$el.querySelector(`[data-cy="MeinBerlinAddonDistrict:warning:${row.districtCode}"]`)
          ?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      })
    },

    onInput (row, value) {
      // A new value has to be confirmed again
      this.updateRow(row.id, { isConfirmed: false, value: value.trim() })
    },

    /**
     * The numbers of organisations and procedures may have changed since the page was loaded, so they are read
     * again before a row is saved. The warning must not depend on outdated data.
     */
    refreshWarningData (id) {
      const url = Routing.generate('api_resource_get', {
        fields: {
          MeinBerlinAddonDistrict: ['communicatedProcedures', 'usedByOrganisations'].join()
        },
        resourceId: id,
        resourceType: 'MeinBerlinAddonDistrict'
      })

      return this.demosplanUi.dpApi.get(url).then(response => {
        const { communicatedProcedures, usedByOrganisations } = response.data.data.attributes

        this.updateRow(id, {
          communicatedProcedures: communicatedProcedures ?? { count: 0, names: [] },
          usedByOrganisations: usedByOrganisations ?? 0
        })
      })
    },

    async save (clickedRow) {
      const id = clickedRow.id

      if (!this.isChanged(this.getRow(id))) {
        dplan.notify.notify('info', Translator.trans('mein.berlin.districts.no.changes'))

        return
      }

      this.updateRow(id, { isSaving: true })

      try {
        await this.refreshWarningData(id)

        // The warning is shown now, nothing is saved before the user confirmed it
        const row = this.getRow(id)
        if (this.needsConfirmation(row) && !row.isConfirmed) {
          dplan.notify.notify('warning', Translator.trans('mein.berlin.districts.confirm.required'))
          this.showWarning(row)

          return
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
        // The organisations and procedures that use the ID have to be read again
        await this.fetchDistricts()
      } catch (error) {
        // The reason is shown by the messages of the response, the saved value is restored
        console.error('[MeinBerlin] Failed to save the organisation ID of a district:', error)
        dplan.notify.notify('error', Translator.trans('error.save'))
        this.updateRow(id, { isConfirmed: false, value: this.getRow(id).initValue })
      } finally {
        this.updateRow(id, { isSaving: false })
      }
    }
  },

  mounted () {
    this.fetchDistricts()
  }
}
</script>
