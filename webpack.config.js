const DemosPlanAddon = require('@demos-europe/demosplan-addon-client-builder')

const config = DemosPlanAddon.build(
  'demosplan-addon-mein-berlin',
  {
    MeinBerlinAdditionalField: DemosPlanAddon.resolve(
      'client/hooks/AddonAdditionalField/MeinBerlinAdditionalField.vue'
    )
  }
)

module.exports = config
