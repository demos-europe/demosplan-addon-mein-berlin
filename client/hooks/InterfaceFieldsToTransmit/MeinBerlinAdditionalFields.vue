<template>
  <component
    :is="selectedComponent"
    v-bind="componentProps"
    @addonEvent:emit="$emit('addonEvent:emit', $event)"
  />
</template>

<script>
import MeinBerlinProcedureFields from './MeinBerlinProcedureFields.vue'
import MeinBerlinOrgaField from './MeinBerlinOrgaField.vue'

export default {
  name: 'MeinBerlinAdditionalFields',

  components: {
    MeinBerlinProcedureFields,
    MeinBerlinOrgaField
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

  computed: {
    selectedComponent () {
      return this.isProcedureSettingsPage ? MeinBerlinProcedureFields : MeinBerlinOrgaField
    },

    componentProps () {
      if (this.isProcedureSettingsPage) {
        return {
          additionalFieldOptions: this.additionalFieldOptions,
          demosplanUi: this.demosplanUi,
          existingPictogram: this.existingPictogram,
          organisationId: this.organisationId,
          pictogramAltText: this.pictogramAltText,
          pictogramCopyright: this.pictogramCopyright,
          relationshipId: this.relationshipId,
          userMeinBerlinOrgId: this.userMeinBerlinOrgId,
          userOrgaId: this.userOrgaId
        }
      } else {
        return {
          additionalFieldOptions: this.additionalFieldOptions,
          demosplanUi: this.demosplanUi,
          relationshipId: this.relationshipId,
          relationshipKey: this.relationshipKey,
          userMeinBerlinOrgId: this.userMeinBerlinOrgId,
          userOrgaId: this.userOrgaId
        }
      }
    }
  }
}
</script>
