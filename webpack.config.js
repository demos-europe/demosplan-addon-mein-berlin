const DemosPlanAddon = require('@demos-europe/demosplan-addon-client-builder')

const config = DemosPlanAddon.build(
  'demosplan-addon-mein-berlin',
  {
    MeinBerlinAdditionalFields: DemosPlanAddon.resolve(
      'client/hooks/InterfaceFieldsToTransmit/MeinBerlinAdditionalFields.vue'
    ),
    MeinBerlinDistrictsAdmin: DemosPlanAddon.resolve(
      'client/hooks/DistrictsAdmin/MeinBerlinDistrictsAdmin.vue'
    )
  }
)

module.exports = config
