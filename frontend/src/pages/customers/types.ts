export type CanonicalCustomer = {
  id: string
  name: string
}

export type MappingRow = {
  id: number
  externalName: string
  integration: string
  mappedCustomerId: string
  confidence: number
  status: 'mapped' | 'pending'
}

export type Stat = {
  label: string
  value: string
  color: string
}


