const DemosPlanAddon = require('@demos-europe/demosplan-addon-client-builder')

const config = DemosPlanAddon.build(
  'demosplan-addon-mein-berlin',
  {
    MeinBerlinAdditionalField: DemosPlanAddon.resolve(
      'client/hooks/AddonAdditionalField/MeinBerlinAdditionalField.vue'
    ),
    MeinBerlinProcedurePictogram: DemosPlanAddon.resolve(
      'client/hooks/ProcedurePictogram/MeinBerlinProcedurePictogram.vue'
    )
  }
)

module.exports = config
