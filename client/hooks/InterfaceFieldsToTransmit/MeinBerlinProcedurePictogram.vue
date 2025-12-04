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
      v-if="existingPictogramData && !deletePictogram"
      class="mt-4"
    >
      <img
        :alt="pictogramAltTextValue || Translator.trans('procedure.pictogram')"
        :src="getPictogramUrl(existingPictogramData.hash)"
        class="layout__item w-1/6 pl-0 mb-4"
      >
      <span class="layout__item w-1/3">
        <a
          :href="getFileUrl(existingPictogramData.hash)"
          target="_blank"
          rel="noopener"
          class="o-link--default"
        >
          {{ existingPictogramData.name }}
        </a>
        <button
          type="button"
          class="btn-icns m-0 ml-2"
          :aria-label="Translator.trans('delete')"
          @click="handleDelete"
        >
          <i
            class="fa fa-trash"
            aria-hidden="true"
          />
        </button>
      </span>
    </div>

    <!-- File upload -->
    <div v-else>
      <p class="lbl__hint">
        {{ Translator.trans('text.procedure.edit.external.pictogram') }}
      </p>
      <component
        :key="uploadKey"
        :is="demosplanUi.DpUploadFiles"
        id="r_pictogram"
        :basic-auth="dplan.settings.basicAuth"
        :get-file-by-hash="hash => Routing.generate('core_file', { hash: hash })"
        :max-file-size="maxFileSize"
        :max-number-of-files="1"
        :translations="{
          dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '5MB' })
        }"
        :tus-endpoint="dplan.paths.tusEndpoint"
        allowed-file-types="img"
        name="r_pictogram"
        needs-hidden-input
        @upload-success="handleUploadSuccess"
        @file-remove="handleFileRemoved"
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

    <!-- Hidden input for form submission when pictogram is marked for deletion -->
    <input
      v-if="deletePictogram"
      type="hidden"
      name="r_deletePictogram"
      value="1"
    >
  </div>
</template>

<script>
// Pictogram validation constants
const MIN_WIDTH = 500
const MIN_HEIGHT = 300
const MAX_FILE_SIZE = 5242880

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
      existingPictogramData: this.existingPictogram,
      maxFileSize: MAX_FILE_SIZE,
      pictogramAltTextValue: this.pictogramAltText || '',
      pictogramCopyrightValue: this.pictogramCopyright || '',
      validFile: null,
      uploadKey: 0
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

    handleDelete () {
      if (confirm(Translator.trans('check.procedure.pictogram.delete'))) {
        this.deletePictogram = true
        this.existingPictogramData = null
      }
    },

    async handleUploadSuccess (fileInfo) {
      try {
        // First check if the file type is valid
        const validFormats = ['image/png', 'image/jpeg', 'image/gif']
        if (fileInfo.type && !validFormats.includes(fileInfo.type)) {
          dplan.notify.error(Translator.trans('mein.berlin.pictogram.error.format'))
          dplan.notify.warning(Translator.trans('mein.berlin.pictogram.remove.instruction'))
          await this.$nextTick()
          this.removeInvalidFile()
          return
        }

        const imageUrl = Routing.generate('core_logo', { hash: fileInfo.hash })
        const validation = await this.validateImageDimensions(imageUrl)

        if (validation.valid) {
          this.validFile = fileInfo
          this.deletePictogram = false
        } else {
          dplan.notify.error(validation.error)
          dplan.notify.warning(Translator.trans('mein.berlin.pictogram.remove.instruction'))
          await this.$nextTick()
          this.removeInvalidFile()
        }
      } catch (error) {
        console.error('Pictogram validation error:', error)
        dplan.notify.error(Translator.trans('mein.berlin.pictogram.error.invalid'))
        await this.$nextTick()
        this.removeInvalidFile()
      }
    },

    removeInvalidFile () {
      try {
        this.uploadKey++
        this.validFile = null
      } catch (error) {
        console.error('Error removing invalid file:', error)
      }
    },

    handleFileRemoved () {
      this.validFile = null
    },

    /**
     * Validate image dimensions by loading it from URL
     */
    async validateImageDimensions (imageUrl) {
      return new Promise((resolve) => {
        const img = new Image()

        img.onload = () => {
          if (img.width < MIN_WIDTH || img.height < MIN_HEIGHT) {
            resolve({
              valid: false,
              error: Translator.trans('mein.berlin.pictogram.error.dimensions', {
                minWidth: MIN_WIDTH,
                minHeight: MIN_HEIGHT,
                actualWidth: img.width,
                actualHeight: img.height
              })
            })
          } else {
            resolve({ valid: true })
          }
        }

        img.onerror = () => {
          resolve({
            valid: false,
            error: Translator.trans('mein.berlin.pictogram.error.invalid')
          })
        }

        img.src = imageUrl
      })
    },
  }
}
</script>
