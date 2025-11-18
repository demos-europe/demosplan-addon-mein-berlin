<template>
  <div class="u-mb">
    <component
      :is="demosplanUi.DpLabel"
      for="r_pictogram"
      :required="isRequired"
      :text="label.text"
      class="inline-block u-mb" />

    <template v-if="hasPictogram && !markedForDeletion">
      <img
        :src="pictogramUrl"
        :alt="pictogramAltText || 'Verfahrenspiktogramm'"
        class="layout__item u-1-of-6 u-pl-0 u-mb">
      <span
        v-if="!isMeinBerlinActive"
        class="layout__item u-1-of-3">
        <component
          :is="demosplanUi.DpButton"
          :text="Translator.trans('delete')"
          icon="trash"
          variant="subtle"
          @click="handleDelete">
        </component>
      </span>
      <span
        v-else
        class="layout__item u-1-of-3">
        <component
          :is="demosplanUi.DpInlineNotification"
          type="info"
          :message="Translator.trans('mein.berlin.pictogram.delete.blocked')">
        </component>
      </span>
      <a
        :href="pictogramDownloadUrl"
        target="_blank"
        rel="noopener"
        class="o-link--default">
        {{ currentPictogramName }}
      </a>
    </template>

    <template v-else>
      <p class="lbl__hint u-mb">
        {{ Translator.trans('mein.berlin.pictogram.requirements.detailed') }}
      </p>

      <component
        :is="demosplanUi.DpUploadFiles"
        ref="uploadComponent"
        id="r_pictogram"
        allowed-file-types="img"
        :max-file-size="5242880"
        :max-number-of-files="1"
        name="r_pictogram"
        needs-hidden-input
        :translations="{ dropHereOr: Translator.trans('form.button.upload.file', { browse: '{browse}', maxUploadSize: '5 MB' }) }"
        :basic-auth="dplan.settings.basicAuth"
        :tus-endpoint="dplan.paths.tusEndpoint"
        @file-remove="handleFileRemoved"
        @upload-success="handleUploadSuccess"
      />

      <component
        :is="demosplanUi.DpInlineNotification"
        v-if="validationError"
        type="error"
        :message="validationError"
        class="u-mt-0_5">
      </component>

      <component
        :is="demosplanUi.DpInlineNotification"
        v-if="validationError"
        type="warning"
        :message="Translator.trans('mein.berlin.pictogram.remove.instruction')"
        class="u-mt-0_5">
      </component>
    </template>

    <input
      v-if="markedForDeletion"
      type="hidden"
      name="r_deletePictogram"
      value="1">
  </div>
</template>

<script>
export default {
  name: 'MeinBerlinProcedurePictogram',

  props: {
    currentPictogram: {
      type: String,
      default: ''
    },

    pictogramHash: {
      type: String,
      default: ''
    },

    pictogramName: {
      type: String,
      default: ''
    },

    pictogramAltText: {
      type: String,
      default: ''
    },

    procedureId: {
      type: String,
      required: true
    },

    isMeinBerlinActive: {
      type: Boolean,
      default: false
    },

    required: {
      type: Boolean,
      default: false
    },

    demosplanUi: {
      type: Object,
      required: true
    }
  },

  data () {
    return {
      markedForDeletion: false,
      validationError: null,
      validFile: null
    }
  },

  computed: {
    label () {
      return {
        text: Translator.trans('procedure.pictogram')
      }
    },

    isRequired () {
      return this.required || this.isMeinBerlinActive
    },

    hasPictogram () {
      return this.currentPictogram && this.currentPictogram !== ''
    },

    currentPictogramName () {
      return this.pictogramName || Translator.trans('procedure.pictogram')
    },

    pictogramUrl () {
      if (!this.pictogramHash) return ''
      return Routing.generate('core_logo', { hash: this.pictogramHash })
    },

    pictogramDownloadUrl () {
      if (!this.pictogramHash) return ''
      return Routing.generate('core_file_procedure', {
        hash: this.pictogramHash,
        procedureId: this.procedureId
      })
    }
  },

  watch: {
    isMeinBerlinActive (newVal) {
      if (newVal && !this.hasPictogram && !this.validFile) {
        this.validationError = Translator.trans('mein.berlin.pictogram.required')
      } else if (!newVal && this.validationError === Translator.trans('mein.berlin.pictogram.required')) {
        this.validationError = null
      }
    }
  },

  methods: {
    handleDelete () {
      if (confirm(Translator.trans('check.procedure.pictogram.delete'))) {
        this.markedForDeletion = true
        this.$emit('pictogram:deleted')
      }
    },

    async handleUploadSuccess (fileInfo) {
      console.log('[MeinBerlin] File uploaded successfully:', fileInfo)

      this.validationError = null

      try {
        const validFormats = ['image/png', 'image/jpeg', 'image/gif']
        if (fileInfo.type && !validFormats.includes(fileInfo.type)) {
          this.validationError = Translator.trans('mein.berlin.pictogram.error.format')
          this.$emit('pictogram:validated', { valid: false })
          return
        }

        const imageUrl = Routing.generate('core_logo', { hash: fileInfo.hash })

        const validation = await this.validateImageDimensions(imageUrl, fileInfo.type)

        if (!validation.valid) {
          this.validationError = validation.error
          this.$emit('pictogram:validated', { valid: false })
        } else {
          this.validFile = fileInfo
          this.$emit('pictogram:validated', { valid: true, file: fileInfo })
        }
      } catch (error) {
        console.error('[MeinBerlin] Validation error:', error)
        this.validationError = Translator.trans('mein.berlin.pictogram.error.invalid')
        this.$emit('pictogram:validated', { valid: false })
      }
    },

    handleFileRemoved () {
      this.validFile = null
      this.validationError = null
      this.$emit('pictogram:validated', { valid: false })
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

        img.onerror = (error) => {
          console.error('[MeinBerlin] Failed to load image:', error)
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
