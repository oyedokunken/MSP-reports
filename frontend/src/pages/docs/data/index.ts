import type { DocCategory } from '../types'
import { gettingStartedCategory } from './gettingStarted'
import { integrationsCategory } from './integrations'
import { customersCategory } from './customers'
import { reportsCategory } from './reports'
import { administrationCategory } from './administration'

export const documentationData: DocCategory[] = [
  gettingStartedCategory,
  integrationsCategory,
  customersCategory,
  reportsCategory,
  administrationCategory,
]

export { gettingStartedCategory, integrationsCategory, customersCategory, reportsCategory, administrationCategory }
