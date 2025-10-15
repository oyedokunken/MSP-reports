import FloatingLayout from '@/components/layout/FloatingLayout'
import { CustomersHeader } from './components/Header'
import { CustomersStats } from './components/Stats'
import { CustomersSearchBar } from './components/SearchBar'
import { CustomersTable } from './components/Table'
import { MapDialog } from './components/MapDialog'
import { CustomersPagination } from './components/Pagination'
import type { CanonicalCustomer, MappingRow, Stat } from './types'
import { useCustomerMapping } from './hooks'

export default function CustomerList() {
  const canonicalCustomers: CanonicalCustomer[] = [
    { id: 'techcorp', name: 'TechCorp Solutions' },
    { id: 'global', name: 'Global Industries Inc' },
    { id: 'acme', name: 'ACME Corporation' },
  ]

  // Integration customers (mocked) - matches the screenshot provided
  const initialRows: MappingRow[] = [
    {
      id: 1,
      externalName: 'TechCorp Solutions',
      integration: 'Autotask PSA',
      mappedCustomerId: 'techcorp',
      confidence: 100,
      status: 'mapped' as 'mapped' | 'pending',
    },
    {
      id: 2,
      externalName: 'TechCorp',
      integration: 'Datto RMM',
      mappedCustomerId: 'techcorp',
      confidence: 85,
      status: 'mapped' as 'mapped' | 'pending',
    },
    {
      id: 3,
      externalName: 'Global Industries Inc',
      integration: 'Autotask PSA',
      mappedCustomerId: 'global',
      confidence: 100,
      status: 'mapped' as 'mapped' | 'pending',
    },
    {
      id: 4,
      externalName: 'Unknown Customer ABC',
      integration: 'Veeam Backup',
      mappedCustomerId: '',
      confidence: 0,
      status: 'pending' as 'mapped' | 'pending',
    },
  ]

  const {
    rows,
    isDialogOpen,
    search,
    setSearch,
    selectedCustomerId,
    setSelectedCustomerId,
    filteredCanonical,
    getCanonicalName,
    openMapDialog,
    closeDialog,
    confirmMapping,
  } = useCustomerMapping(initialRows, canonicalCustomers)

  const stats: Stat[] = [
    { label: 'Total Customers', value: '24', color: 'text-blue-600' },
    { label: 'Mapped', value: '19', color: 'text-green-600' },
    { label: 'Unmapped', value: '5', color: 'text-yellow-600' },
    { label: 'Active Reports', value: '35', color: 'text-purple-600' },
  ]

  return (
    <FloatingLayout>
      <div className="space-y-6">
        <CustomersHeader />

        <CustomersStats stats={stats} />

        <CustomersSearchBar />

        <CustomersTable rows={rows} getCanonicalName={getCanonicalName} onMapClick={openMapDialog} />

        <MapDialog
          isOpen={isDialogOpen}
          search={search}
          setSearch={setSearch}
          filteredCanonical={filteredCanonical}
          selectedCustomerId={selectedCustomerId}
          setSelectedCustomerId={setSelectedCustomerId}
          onClose={closeDialog}
          onConfirm={confirmMapping}
        />

        <CustomersPagination />
      </div>
    </FloatingLayout>
  )
}
