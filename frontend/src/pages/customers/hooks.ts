import { useCallback, useMemo, useState } from 'react'
import type { CanonicalCustomer, MappingRow } from './types'

export const useCustomerMapping = (initialRows: MappingRow[], canonical: CanonicalCustomer[]) => {
  const [rows, setRows] = useState<MappingRow[]>(initialRows)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [activeRowId, setActiveRowId] = useState<number | null>(null)
  const [search, setSearch] = useState('')
  const [selectedCustomerId, setSelectedCustomerId] = useState('')

  const filteredCanonical = useMemo(() => {
    const q = search.trim().toLowerCase()
    if (!q) return canonical
    return canonical.filter((c) => c.name.toLowerCase().includes(q))
  }, [search, canonical])

  const getCanonicalName = useCallback((id?: string) => {
    const found = canonical.find((c) => c.id === id)
    return found ? found.name : '—'
  }, [canonical])

  const openMapDialog = useCallback((rowId: number, preselect?: string) => {
    setActiveRowId(rowId)
    setSelectedCustomerId(preselect || '')
    setSearch('')
    setIsDialogOpen(true)
  }, [])

  const closeDialog = useCallback(() => {
    setIsDialogOpen(false)
    setActiveRowId(null)
    setSelectedCustomerId('')
    setSearch('')
  }, [])

  const confirmMapping = useCallback(() => {
    if (activeRowId == null || !selectedCustomerId) return
    setRows((prev) => prev.map((r) => (r.id === activeRowId ? { ...r, mappedCustomerId: selectedCustomerId, status: 'mapped' } : r)))
    closeDialog()
  }, [activeRowId, selectedCustomerId, closeDialog])

  return {
    rows,
    setRows,
    isDialogOpen,
    activeRowId,
    search,
    setSearch,
    selectedCustomerId,
    setSelectedCustomerId,
    filteredCanonical,
    getCanonicalName,
    openMapDialog,
    closeDialog,
    confirmMapping,
  }
}


