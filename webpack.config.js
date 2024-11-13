const DemosPlanAddon = require('@demos-europe/demosplan-addon-client-builder')

const config = DemosPlanAddon.build(
  'demosplan-addon-mein-berlin',
  {
    OrganisationIdField: DemosPlanAddon.resolve(
      'client/addons/OrganisationIdField.vue'
    ),
    ProcedureDataField: DemosPlanAddon.resolve(
      'client/addons/ProcedureDataField.vue'
    )
  }
)

module.exports = config
