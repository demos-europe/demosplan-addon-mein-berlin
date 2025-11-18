<template>
  <div class="mein-berlin-pictogram u-mb">
    <label class="inline-block u-mb-0">
      {{ label.text }}
      <span v-if="isRequired" class="color--warning">*</span>
    </label>

    <div v-if="hasPictogram && !markedForDeletion" class="u-mt-0_5">
      <div class="flex items-start space-x-2 u-mb-0_5">
        <img
          :src="pictogramUrl"
          :alt="pictogramAltText || 'Verfahrenspiktogramm'"
          class="layout__item u-1-of-6 u-pl-0 u-mb">

        <button
          v-if="!isMeinBerlinActive"
          type="button"
          class="btn btn--blank o-link--default"
          :title="Translator.trans('procedure.pictogram.delete')"
          @click="handleDelete">
          <i class="fa fa-trash" aria-hidden="true"></i>
          {{ Translator.trans('delete') }}
        </button>

        <p v-else class="flash flash-info inline-block u-mb-0 u-ml-0_5">
          <i class="fa fa-info-circle" aria-hidden="true"></i>
          {{ Translator.trans('mein.berlin.pictogram.delete.blocked') }}
        </p>
      </div>

      <a
        :href="pictogramDownloadUrl"
        target="_blank"
        rel="noopener"
        class="o-link--default">
        {{ currentPictogramName }}
      </a>
    </div>

    <div v-else class="u-mt-0_5">
      <p class="lbl__hint">
        {{ Translator.trans('mein.berlin.pictogram.requirements') }}
      </p>

      <input
        type="file"
        name="r_pictogram"
        accept="image/png,image/jpeg,image/gif"
        :required="isRequired"
        @change="handleFileSelect"
        class="o-form__control-input">

      <div v-if="validationError" class="flash flash-error u-mt-0_5">
        <i class="fa fa-exclamation-triangle" aria-hidden="true"></i>
        {{ validationError }}
      </div>

      <div v-if="uploadPreview" class="u-mt-0_5">
        <p class="weight--bold">{{ Translator.trans('preview') }}:</p>
        <img :src="uploadPreview" alt="Preview" class="layout__item u-1-of-6 u-mb-0_5">
        <p class="color--grey u-mb-0">
          {{ uploadFileName }} ({{ formatFileSize(uploadFileSize) }})
        </p>
      </div>
    </div>

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
      uploadPreview: null,
      uploadFileName: null,
      uploadFileSize: null,
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

    async handleFileSelect (event) {
      const file = event.target.files[0]

      if (!file) {
        this.clearUpload()
        return
      }

      // Reset previous state
      this.validationError = null
      this.uploadPreview = null

      // Validate file
      const validation = await this.validateBerlinPictogram(file)

      if (!validation.valid) {
        this.validationError = validation.error
        this.clearUpload()
        // Clear the file input
        event.target.value = ''
        this.$emit('pictogram:validated', { valid: false })
        return
      }

      // File is valid - create preview using FileReader (CSP-safe data URL)
      this.validFile = file
      this.uploadFileName = file.name
      this.uploadFileSize = file.size

      // Use FileReader to create data URL (allowed by CSP)
      const reader = new FileReader()
      reader.onload = (e) => {
        this.uploadPreview = e.target.result
      }
      reader.readAsDataURL(file)

      this.$emit('pictogram:validated', { valid: true, file })
    },

    /**
     * Requirements:
     * - Minimum dimensions: 500×300 px
     * - Formats: PNG, JPEG, GIF
     * - Max file size: 5 MB
     */
    async validateBerlinPictogram (file) {
      // 1. Check format
      const validFormats = ['image/png', 'image/jpeg', 'image/gif']
      if (!validFormats.includes(file.type)) {
        return {
          valid: false,
          error: Translator.trans('mein.berlin.pictogram.error.format')
        }
      }

      // 2. Check file size (5MB = 5 * 1024 * 1024)
      const maxSize = 5 * 1024 * 1024
      if (file.size > maxSize) {
        return {
          valid: false,
          error: Translator.trans('mein.berlin.pictogram.error.filesize')
        }
      }

      // 3. Check dimensions (async - need to load image)
      return new Promise((resolve) => {
        const reader = new FileReader()
        const img = new Image()

        reader.onload = (e) => {
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

          // Use data URL instead of blob URL (CSP-safe)
          img.src = e.target.result
        }

        reader.onerror = () => {
          resolve({
            valid: false,
            error: Translator.trans('mein.berlin.pictogram.error.invalid')
          })
        }

        reader.readAsDataURL(file)
      })
    },

    clearUpload () {
      this.uploadPreview = null
      this.uploadFileName = null
      this.uploadFileSize = null
      this.validFile = null
    },

    formatFileSize (bytes) {
      if (bytes === 0) return '0 Bytes'
      const k = 1024
      const sizes = ['Bytes', 'KB', 'MB']
      const i = Math.floor(Math.log(bytes) / Math.log(k))
      return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
    }
  }
}
</script>
