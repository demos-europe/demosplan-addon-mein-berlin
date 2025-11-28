<template>
  <div :class="prefixClass('mt-4 mb-4')">
    <component
      :is="demosplanUi.DpLabel"
      :class="prefixClass('inline-block mb-0')"
      :text="Translator.trans('procedure.pictogram')"
      for="r_pictogram"
    />

    <!-- Display existing pictogram -->
    <div
      v-if="existingPictogramData && !deletePictogram"
      :class="prefixClass('mt-4')"
    >
      <img
        :alt="pictogramAltTextValue || Translator.trans('procedure.pictogram')"
        :class="prefixClass('layout__item w-1/6 pl-0 mb-4')"
        :src="getPictogramUrl(existingPictogramData.hash)"
      >
      <span :class="prefixClass('layout__item w-1/3')">
        <a
          :href="getFileUrl(existingPictogramData.hash)"
          target="_blank"
          rel="noopener"
          :class="prefixClass('o-link--default')"
        >
          {{ existingPictogramData.name }}
        </a>
        <button
          type="button"
          :class="prefixClass('btn-icns u-m-0 u-ml-0_5')"
          :aria-label="Translator.trans('delete')"
          @click="handleDelete"
        >
          <i
            :class="prefixClass('fa fa-trash')"
            aria-hidden="true"
          />
        </button>
      </span>
    </div>

    <!-- File upload -->
    <div
      v-else
      :class="prefixClass('mt-2')"
    >
      <p :class="prefixClass('lbl__hint mb-3')">
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
        @upload-success="handleUploadSuccess"
        @file-remove="handleFileRemoved"
      />
    </div>

    <component
      :is="demosplanUi.DpInput"
      id="r_pictogramCopyright"
      v-model="pictogramCopyrightValue"
      :class="prefixClass('my-2')"
      :label="{
        text: Translator.trans('procedure.pictogram.copyright')
      }"
      data-cy="procedure:pictogramCopyright"
      name="r_pictogramCopyright"
    />

    <component
      :is="demosplanUi.DpInput"
      id="r_pictogramAltText"
      v-model="pictogramAltTextValue"
      :class="prefixClass('my-2')"
      :label="{
        text: Translator.trans('procedure.pictogram.altText'),
        tooltip: Translator.trans('procedure.pictogram.altText.toolTipp')
      }"
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
import { prefixClassMixin } from '@demos-europe/demosplan-ui'

export default {
  name: 'MeinBerlinProcedurePictogram',

  mixins: [prefixClassMixin],

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
      existingPictogramData: this.existingPictogram,
      validFile: null
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
          this.removeInvalidFile(fileInfo)
          return
        }

        const imageUrl = Routing.generate('core_logo', { hash: fileInfo.hash })
        const validation = await this.validateImageDimensions(imageUrl, fileInfo.type)

        if (validation.valid) {
          this.validFile = fileInfo
          this.deletePictogram = false
        } else {
          dplan.notify.error(validation.error)
          dplan.notify.warning(Translator.trans('mein.berlin.pictogram.remove.instruction'))
          await this.$nextTick()
          this.removeInvalidFile(fileInfo)
        }
      } catch (error) {
        console.error('Pictogram validation error:', error)
        dplan.notify.error(Translator.trans('mein.berlin.pictogram.error.invalid'))
        await this.$nextTick()
        this.removeInvalidFile(fileInfo)
      }
    },

    removeInvalidFile (fileInfo) {
      try {
        // Find the hidden input that stores file hashes for form submission
        const hiddenInput = document.querySelector('input[name="uploadedFiles[r_pictogram]"]')

        if (hiddenInput) {
          hiddenInput.value = ''
        }

        // Remove the visual file display
        const fileListItems = document.querySelectorAll('[data-cy="uploadFile"] .uploaded-file-item, [data-cy="uploadFile"] li')
        fileListItems.forEach(item => item.remove())
      } catch (error) {
        console.error('Error removing invalid file:', error)
      }
    },

    handleFileRemoved () {
      this.validFile = null
    },

    /**
     * Validate image dimensions by loading it from URL
     * fileType is used for additional context in error messages
     */
    async validateImageDimensions (imageUrl, fileType = null) {
      return new Promise((resolve) => {
        const img = new Image()

        img.onload = () => {
          if (img.width < 500 || img.height < 300) {
            resolve({
              valid: false,
              error: Translator.trans('mein.berlin.pictogram.error.dimensions', {
                minWidth: 500,
                minHeight: 300,
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
