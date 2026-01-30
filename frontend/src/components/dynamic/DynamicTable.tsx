import React, { useState, useMemo } from 'react'
import type { TableConfig, ColumnDefinition } from '@/types/displayTemplate'
import { logger } from '@/lib/logger'
import { formatValue, getValueByPath } from '@/utils/formatters'
import { ChevronUp, ChevronDown, FileText } from 'lucide-react'
import { api } from '@/lib/api'
import { useParams } from 'react-router-dom'

interface DynamicTableProps {
  config: TableConfig
  data: Record<string, unknown>[]
  onSelectionChange?: (selectedIds: string[]) => void
}

interface License {
  name: string
  active: number
  consumed: number
  unused: number
  utilization: number
}

export const DynamicTable: React.FC<DynamicTableProps> = ({
  config,
  data,
  onSelectionChange,
}) => {
  const { integrationId } = useParams<{ integrationId: string }>()
  const [sortColumn, setSortColumn] = useState<string | null>(null)
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc')
  const [currentPage, setCurrentPage] = useState(1)
  const [selectedRows, setSelectedRows] = useState<Set<string>>(new Set())
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})
  const [licenseModalData, setLicenseModalData] = useState<{ customerName: string; customerId: number; licenses: License[] | null } | null>(null)

  const pageSize = config.pageSize || 10

  const filteredData = useMemo(() => {
    if (!config.filterable || Object.keys(filterValues).length === 0) {
      return data
    }

    return data.filter((row) => {
      return Object.entries(filterValues).every(([key, filterValue]) => {
        if (!filterValue) return true
        const cellValue = String(getValueByPath(row, key)).toLowerCase()
        return cellValue.includes(filterValue.toLowerCase())
      })
    })
  }, [data, filterValues, config.filterable])

  const sortedData = useMemo(() => {
    if (!sortColumn) return filteredData

    return [...filteredData].sort((a, b) => {
      const aValue = getValueByPath(a, sortColumn)
      const bValue = getValueByPath(b, sortColumn)

      if (aValue === bValue) return 0

      const aStr = String(aValue ?? '')
      const bStr = String(bValue ?? '')
      const comparison = aStr < bStr ? -1 : 1
      return sortDirection === 'asc' ? comparison : -comparison
    })
  }, [filteredData, sortColumn, sortDirection])

  const paginatedData = useMemo(() => {
    if (!config.paginated) return sortedData

    const startIndex = (currentPage - 1) * pageSize
    return sortedData.slice(startIndex, startIndex + pageSize)
  }, [sortedData, currentPage, pageSize, config.paginated])

  const totalPages = Math.ceil(sortedData.length / pageSize)

  const handleSort = (column: ColumnDefinition) => {
    if (!config.sortable || !column.sortable) return

    if (sortColumn === column.key) {
      setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc')
    } else {
      setSortColumn(column.key)
      setSortDirection('asc')
    }
  }

  const handleSelectRow = (rowId: string) => {
    const newSelected = new Set(selectedRows)
    if (newSelected.has(rowId)) {
      newSelected.delete(rowId)
    } else {
      newSelected.add(rowId)
    }
    setSelectedRows(newSelected)
    onSelectionChange?.(Array.from(newSelected))
  }

  const handleSelectAll = () => {
    if (selectedRows.size === paginatedData.length) {
      setSelectedRows(new Set())
      onSelectionChange?.([])
    } else {
      const allIds = new Set(paginatedData.map((row) => String(row.id ?? '')))
      setSelectedRows(allIds)
      onSelectionChange?.(Array.from(allIds))
    }
  }

  const renderCell = (row: Record<string, unknown>, column: ColumnDefinition) => {
    const value = getValueByPath(row, column.key)
    const formatted = formatValue(value, column.format)

    if (column.format === 'status' && column.colorMap) {
      const valueStr = String(value ?? '')
      const colorClass = column.colorMap[valueStr] || 'gray'
      const colorMap: Record<string, string> = {
        success: 'bg-green-100 text-green-800',
        error: 'bg-red-100 text-red-800',
        warning: 'bg-yellow-100 text-yellow-800',
        info: 'bg-blue-100 text-blue-800',
        gray: 'bg-gray-100 text-gray-800',
      }
      return (
        <span className={`px-2 py-1 rounded-full text-xs font-medium ${colorMap[colorClass]}`}>
          {formatted}
        </span>
      )
    }

    if (column.format === 'badge') {
      return (
        <span className="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
          {formatted}
        </span>
      )
    }

    if (column.format === 'license_button') {
      const handleViewLicenses = async () => {
        const customerId = row.id as number
        const customerName = String(row.name || 'Customer')

        setLicenseModalData({
          customerName,
          customerId,
          licenses: null // null means loading
        })

        try {
          const response = await api.get<{ licenses: License[] }>(`/integrations/${integrationId}/customer-licenses`, {
            customer_id: customerId
          })

          setLicenseModalData({
            customerName,
            customerId,
            licenses: response.licenses || []
          })
        } catch (error) {
          logger.error('Failed to fetch licenses:', error)
          setLicenseModalData({
            customerName,
            customerId,
            licenses: []
          })
        }
      }

      return (
        <button
          onClick={handleViewLicenses}
          className="flex items-center gap-1 px-3 py-1 text-sm font-medium text-primary hover:text-primary/80 bg-primary/10 hover:bg-primary/20 rounded-md transition-colors"
        >
          <FileText className="w-4 h-4" />
          View Licenses
        </button>
      )
    }

    return <span>{formatted}</span>
  }

  return (
    <div className="space-y-4">

      <div className="overflow-x-auto border border-border rounded-lg">
        <table className="min-w-full divide-y divide-border">
          <thead className="bg-muted/50">
            <tr>
              {config.selectable && (
                <th className="w-12 px-4 py-3">
                  <input
                    type="checkbox"
                    checked={selectedRows.size === paginatedData.length && paginatedData.length > 0}
                    onChange={handleSelectAll}
                    className="rounded border-border bg-card"
                  />
                </th>
              )}
              {config.columns.map((column) => (
                <th
                  key={column.key}
                  className={`px-4 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider ${config.sortable && column.sortable ? 'cursor-pointer hover:bg-muted/80' : ''
                    }`}
                  style={{ width: column.width }}
                  onClick={() => handleSort(column)}
                >
                  <div className="flex items-center gap-2">
                    {column.label}
                    {config.sortable && column.sortable && sortColumn === column.key && (
                      sortDirection === 'asc' ? (
                        <ChevronUp className="w-4 h-4" />
                      ) : (
                        <ChevronDown className="w-4 h-4" />
                      )
                    )}
                  </div>
                </th>
              ))}
            </tr>
            {config.filterable && (
              <tr className="bg-muted/50">
                {config.selectable && <th className="w-12"></th>}
                {config.columns.map((column) => (
                  <th key={column.key} className="px-4 py-2">
                    {column.filterable && (
                      <input
                        type="text"
                        placeholder={`Filter ${column.label}...`}
                        value={filterValues[column.key] || ''}
                        onChange={(e) =>
                          setFilterValues({ ...filterValues, [column.key]: e.target.value })
                        }
                        className="w-full px-2 py-1 text-sm border border-border rounded bg-card dark:bg-[hsl(var(--auth-card))] text-foreground placeholder:text-muted-foreground"
                      />
                    )}
                  </th>
                ))}
              </tr>
            )}
          </thead>
          <tbody className="bg-card dark:bg-[hsl(var(--auth-card))] divide-y divide-border">
            {paginatedData.length === 0 ? (
              <tr>
                <td
                  colSpan={config.columns.length + (config.selectable ? 1 : 0)}
                  className="px-4 py-8 text-center text-muted-foreground"
                >
                  No data available
                </td>
              </tr>
            ) : (
              paginatedData.map((row, index) => (
                <tr key={String(row.id ?? index)} className="hover:bg-muted/50 transition-colors">
                  {config.selectable && (
                    <td className="px-4 py-3">
                      <input
                        type="checkbox"
                        checked={selectedRows.has(String(row.id ?? ''))}
                        onChange={() => handleSelectRow(String(row.id ?? ''))}
                        className="rounded border-border bg-card"
                      />
                    </td>
                  )}
                  {config.columns.map((column) => (
                    <td
                      key={column.key}
                      className={`px-4 py-3 text-sm text-foreground ${column.align || 'left'}`}
                    >
                      {renderCell(row, column)}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {config.paginated && totalPages > 1 && (
        <div className="flex items-center justify-between">
          <div className="text-sm text-muted-foreground">
            Showing {(currentPage - 1) * pageSize + 1} to{' '}
            {Math.min(currentPage * pageSize, sortedData.length)} of {sortedData.length} results
          </div>
          <div className="flex gap-2">
            <button
              onClick={() => setCurrentPage(currentPage - 1)}
              disabled={currentPage === 1}
              className="px-3 py-1 text-sm border border-border rounded disabled:opacity-50 bg-card dark:bg-[hsl(var(--auth-card))] text-foreground hover:bg-muted/50 transition-colors"
            >
              Previous
            </button>
            <span className="px-3 py-1 text-sm text-foreground">
              Page {currentPage} of {totalPages}
            </span>
            <button
              onClick={() => setCurrentPage(currentPage + 1)}
              disabled={currentPage === totalPages}
              className="px-3 py-1 text-sm border border-border rounded disabled:opacity-50 bg-card dark:bg-[hsl(var(--auth-card))] text-foreground hover:bg-muted/50 transition-colors"
            >
              Next
            </button>
          </div>
        </div>
      )}

      {licenseModalData && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setLicenseModalData(null)}>
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 max-w-3xl w-full mx-4 max-h-[80vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                Licenses for {licenseModalData.customerName}
              </h3>
              <button
                onClick={() => setLicenseModalData(null)}
                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
              >
                ✕
              </button>
            </div>

            {licenseModalData.licenses === null ? (
              <div className="flex items-center justify-center py-12">
                <div className="text-center">
                  <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                  <p className="text-gray-600 dark:text-gray-400">Loading licenses...</p>
                </div>
              </div>
            ) : licenseModalData.licenses.length === 0 ? (
              <div className="text-center py-12">
                <p className="text-gray-600 dark:text-gray-400">No licenses found for this customer.</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                  <thead className="bg-gray-50 dark:bg-gray-900">
                    <tr>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">License Name</th>
                      <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Active</th>
                      <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Consumed</th>
                      <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Unused</th>
                      <th className="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Utilization</th>
                    </tr>
                  </thead>
                  <tbody className="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    {licenseModalData.licenses.map((license, index) => (
                      <tr key={index} className="hover:bg-gray-50 dark:hover:bg-gray-700">
                        <td className="px-4 py-3 text-sm text-gray-900 dark:text-white">{license.name}</td>
                        <td className="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">{license.active}</td>
                        <td className="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">{license.consumed}</td>
                        <td className="px-4 py-3 text-sm text-center text-gray-900 dark:text-white">{license.unused}</td>
                        <td className="px-4 py-3 text-sm text-center">
                          <span className={`px-2 py-1 rounded-full text-xs font-medium ${license.utilization > 90 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' :
                            license.utilization > 75 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' :
                              'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                            }`}>
                            {license.utilization}%
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            <div className="mt-4 flex justify-end">
              <button
                onClick={() => setLicenseModalData(null)}
                className="px-4 py-2 text-sm font-medium text-white bg-primary hover:bg-primary/90 rounded-lg transition-colors"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
