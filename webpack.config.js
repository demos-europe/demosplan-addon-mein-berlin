const DemosPlanAddon = require('@demos-europe/demosplan-addon-client-builder')

const config = DemosPlanAddon.build(
  'demosplan-addon-mein-berlin',
  {
    AddonAdditionalField: DemosPlanAddon.resolve(
      'client/addons/MeinBerlinAdditionalField.vue'
    ),
  }
)

module.exports = config
