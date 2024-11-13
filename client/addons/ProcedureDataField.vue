<template>
  <dp-input
    id="meinBerlinProcedureData"
    v-model="currentValue"
    :label="{
      text: Translator.trans('procedure.mein.berlin.id')
    }"
    @blur="$emit('addonEvent:emit', { name: 'blur', payload: addonPayload })" />
</template>

<script>
import { DpInput } from '@demos-europe/demosplan-ui'
import { mapActions, mapState } from 'vuex'

export default {
  name: 'MeinBerlinAddonProcedureData',

  components: {
    DpInput
  },

  props: {
    procedureId: {
      type: String,
      required: true
    }
  },

  data () {
    return {
      currentValue: '',
      meinBerlinProcedure: null,
      meinBerlinProcedureId: ''
    }
  },

  computed: {
    ...mapState('MeinBerlinAddonProcedureData', {
      meinBerlinAddonProcedureData: 'items'
    }),

    addonPayload () {
      return {
        id: this.meinBerlinProcedureId,
        resourceType: 'MeinBerlinAddonProcedureData',
        attributes: {
          procedureShortName: this.currentValue
        },
        request: this.meinBerlinProcedure ? 'PATCH' : 'POST'
      }
    }
  },

  methods: {
    ...mapActions('MeinBerlinAddonProcedureData', {
      fetchBerlinAddonProcedureData: 'list'
    })
  },

  mounted () {
    this.fetchBerlinAddonProcedureData({ include: ['procedure'].join() })
      .then((response) => {
        if (response) {
          this.meinBerlinProcedure = Object.values(response.data?.MeinBerlinAddonProcedureData).find(el => el.relationships.procedure.data.id === this.procedureId) || null

          if (this.meinBerlinProcedure) {
            this.meinBerlinProcedureId = this.meinBerlinProcedure.id
            this.currentValue = this.meinBerlinProcedure.attributes.procedueShortName || ''
          }
        }
      })
  }
}
</script>
