<template>
  <div class="flex flex-row">
    <dp-input
      id="meinBerlinOrganisationId"
      v-model="currentValue"
      :label="{
        text: Translator.trans('organisation.mein.berlin.id')
      }"
      @input="$emit('addonEvent:emit', { name: 'input', payload: currentValue })"
      required />
    <div class="w-1/2 self-end ml-2">
      <dp-button
        data-cy=""
        :text="Translator.trans('save')"
        @click="save" />
    </div>
  </div>
</template>

<script>
import { checkResponse, dpApi, DpButton, DpInput } from '@demos-europe/demosplan-ui'
import { mapState, mapActions } from 'vuex'
export default {
  name: 'MeinBerlinAddonOrganisationId',

  components: {
    DpInput,
    DpButton
  },

  props: {
    orgaId: {
      type: String,
      required: false,
      default: ''
    }
  },

  data () {
    return {
      currentValue: '',
      meinBerlinOrganisation: null,
      meinBerlinOrganisationId: ''
    }
  },

  computed: {
    ...mapState('MeinBerlinAddonOrganisation', {
      meinBerlinAddonOrganisation: 'items'
    })
  },
  methods: {
    ...mapActions('MeinBerlinAddonOrganisation', {
      meinBerlinAddonOrganisationList: 'list'
    }),

    save () {
      const payload = this.createPayload()

      const apiCall = this.meinBerlinOrganisation
        ? dpApi.patch(Routing.generate('api_resource_update', { resourceType: 'MeinBerlinAddonOrganisation', resourceId: this.meinBerlinOrganisationId }), {}, { data: payload })
        : dpApi.post(Routing.generate('api_resource_create', { resourceType: 'MeinBerlinAddonOrganisation' }), {}, { data: payload })

      apiCall
        .then(checkResponse)
        .then(() => {
          dplan.notify.notify('confirm', Translator.trans('confirm.saved'))
        })
        .catch(() => {
          dplan.notify.error(Translator.trans('error.changes.not.saved'))
        })
    },

    createPayload () {
      return {
        type: 'MeinBerlinAddonOrganisation',
        attributes: {
          meinBerlinOrganisationId: this.currentValue
        },
        relationships: this.meinBerlinOrganisation ? undefined : {
          orga: {
            data: {
              type: 'Orga',
              id: this.orgaId
            }
          }
        },
        ...(this.meinBerlinOrganisation ? { id: this.meinBerlinOrganisation.id } : {}),
      }
    }
  },

  mounted () {
    this.meinBerlinAddonOrganisationList({ include: ['orga'].join() }) // // TODO: get it outside this field (too many requests)
      .then(response => {
        this.meinBerlinOrganisation = Object.values(response.data?.MeinBerlinAddonOrganisation).find(el => el.relationships.orga.data.id === this.orgaId) || null

        if (this.meinBerlinOrganisation) {
          this.meinBerlinOrganisationId = this.meinBerlinOrganisation.id
          this.currentValue = this.meinBerlinOrganisation.attributes.meinBerlinOrganisationId
        }
    })
  }
}
</script>
