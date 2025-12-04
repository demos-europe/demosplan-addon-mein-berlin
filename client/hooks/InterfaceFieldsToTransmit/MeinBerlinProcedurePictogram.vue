<template>
  <div class="my-4">
    <component
      :is="demosplanUi.DpLabel"
      :text="Translator.trans('procedure.pictogram')"
      class="inline-block mb-0"
      for="r_pictogram"
    />

    <!-- Display existing pictogram -->
    <div
      v-if="existingPictogramData"
      class="mt-4"
    >
      <img
        :alt="pictogramAltTextValue || Translator.trans('procedure.pictogram')"
        :src="getPictogramUrl(existingPictogramData.hash)"
        class="layout__item w-1/6 pl-0 mb-4"
      >
      <component
        :is="demosplanUi.DpCheckbox"
        id="r_deletePictogram"
        v-model="deletePictogram"
        :label="{ text: Translator.trans('procedure.pictogram.delete') }"
        class="layout__item w-1/3 cursor-pointer weight--normal"
        name="r_deletePictogram"
      />
      <a
        :href="getFileUrl(existingPictogramData.hash)"
        target="_blank"
        rel="noopener"
      >
        {{ existingPictogramData.name }}
      </a>
    </div>

    <!-- File upload -->
    <div v-else>
      <p class="lbl__hint">
        {{ Translator.trans('text.procedure.edit.external.pictogram') }}
      </p>
      <component
        :is="demosplanUi.DpUploadFiles"
        id="r_pictogram"
        :basic-auth="dplan.settings.basicAuth"
        :get-file-by-hash="hash => Routing.generate('core_file', { hash: hash })"
        :max-file-size="5242880"
        :max-number-of-files="1"
        :translations="{
          dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '5MB' })
        }"
        :tus-endpoint="dplan.paths.tusEndpoint"
        allowed-file-types="img"
        name="r_pictogram"
        needs-hidden-input
        ref="pictogramUpload"
      />
    </div>

    <component
      :is="demosplanUi.DpInput"
      id="r_pictogramCopyright"
      v-model="pictogramCopyrightValue"
      :label="{
        text: Translator.trans('procedure.pictogram.copyright')
      }"
      class="my-2"
      data-cy="procedure:pictogramCopyright"
      name="r_pictogramCopyright"
    />

    <component
      :is="demosplanUi.DpInput"
      id="r_pictogramAltText"
      v-model="pictogramAltTextValue"
      :label="{
        text: Translator.trans('procedure.pictogram.altText'),
        tooltip: Translator.trans('procedure.pictogram.altText.toolTipp')
      }"
      class="my-2"
      data-cy="procedure:pictogramAltText"
      name="r_pictogramAltText"
    />
  </div>
</template>

<script>
export default {
  name: 'MeinBerlinProcedurePictogram',

  props: {
    demosplanUi: {
      type: Object,
      required: true
    },

    existingPictogram: {
      type: Object,
      required: false,
      default: null
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
  },

  data () {
    return {
      deletePictogram: false,
      pictogramAltTextValue: this.pictogramAltText || '',
      pictogramCopyrightValue: this.pictogramCopyright || '',
      existingPictogramData: this.existingPictogram
    }
  },


  methods: {
    getFileUrl (hash) {
      return Routing.generate('core_file_procedure', {
        hash: hash,
        procedureId: this.relationshipId
      })
    },

    getPictogramUrl (hash) {
      return Routing.generate('core_logo', { hash: hash })
    },
  }
}
</script>
