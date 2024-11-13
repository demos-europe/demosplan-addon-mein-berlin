<template>
  <dp-input
    id="meinBerlinProcedureData"
    v-model="procedureShortName"
    :label="{
      text: Translator.trans('procedure.mein.berlin.id')
    }"
    @input="$emit('addonEvent:emit', { name: 'input', payload: procedureShortName })" />
</template>

<script>
import { DpInput } from '@demos-europe/demosplan-ui'

export default {
  name: 'MeinBerlinAddonProcedureData',

  components: {
    DpInput
  },

  data () {
    return {
      procedureShortName: ''
    }
  }
}
</script>
