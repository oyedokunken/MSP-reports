import React, { useState, useMemo } from 'react'
import type { TableConfig, ColumnDefinition } from '@/types/displayTemplate'
import { formatValue, getValueByPath } from '@/utils/formatters'
import { ChevronUp, ChevronDown, Download } from 'lucide-react'

interface DynamicTableProps {
  config: TableConfig
  data: any[]
  onSelectionChange?: (selectedIds: string[]) => void
}

export const DynamicTable: React.FC<DynamicTableProps> = ({
  config,
  data,
  onSelectionChange,
}) => {
  const [sortColumn, setSortColumn] = useState<string | null>(null)
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('asc')
  const [currentPage, setCurrentPage] = useState(1)
  const [selectedRows, setSelectedRows] = useState<Set<string>>(new Set())
  const [filterValues, setFilterValues] = useState<Record<string, string>>({})

  const pageSize = config.pageSize || 10

  // Filter data
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

  // Sort data
  const sortedData = useMemo(() => {
    if (!sortColumn) return filteredData

    return [...filteredData].sort((a, b) => {
      const aValue = getValueByPath(a, sortColumn)
      const bValue = getValueByPath(b, sortColumn)

      if (aValue === bValue) return 0

      const comparison = aValue < bValue ? -1 : 1
      return sortDirection === 'asc' ? comparison : -comparison
    })
  }, [filteredData, sortColumn, sortDirection])

  // Paginate data
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
      const allIds = new Set(paginatedData.map((row) => row.id))
      setSelectedRows(allIds)
      onSelectionChange?.(Array.from(allIds))
    }
  }

  const handleExport = () => {
    // Convert data to CSV
    const headers = config.columns.map((col) => col.label).join(',')
    const rows = sortedData.map((row) =>
      config.columns
        .map((col) => {
          const value = getValueByPath(row, col.key)
          return `"${formatValue(value, col.format)}"`
        })
        .join(',')
    )
    const csv = [headers, ...rows].join('\n')

    // Download
    const blob = new Blob([csv], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = 'export.csv'
    a.click()
    URL.revokeObjectURL(url)
  }

  const renderCell = (row: any, column: ColumnDefinition) => {
    const value = getValueByPath(row, column.key)
    const formatted = formatValue(value, column.format)

    if (column.format === 'status' && column.colorMap) {
      const colorClass = column.colorMap[value] || 'gray'
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

    return <span>{formatted}</span>
  }

  return (
    <div className="space-y-4">
      {config.exportable && (
        <div className="flex justify-end">
          <button
            onClick={handleExport}
            className="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
          >
            <Download className="w-4 h-4" />
            Export CSV
          </button>
        </div>
      )}

      <div className="overflow-x-auto border border-gray-200 rounded-lg">
        <table className="min-w-full divide-y divide-gray-200">
          <thead className="bg-gray-50">
            <tr>
              {config.selectable && (
                <th className="w-12 px-4 py-3">
                  <input
                    type="checkbox"
                    checked={selectedRows.size === paginatedData.length && paginatedData.length > 0}
                    onChange={handleSelectAll}
                    className="rounded border-gray-300"
                  />
                </th>
              )}
              {config.columns.map((column) => (
                <th
                  key={column.key}
                  className={`px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider ${
                    config.sortable && column.sortable ? 'cursor-pointer hover:bg-gray-100' : ''
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
              <tr className="bg-gray-50">
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
                        className="w-full px-2 py-1 text-sm border border-gray-300 rounded"
                      />
                    )}
                  </th>
                ))}
              </tr>
            )}
          </thead>
          <tbody className="bg-white divide-y divide-gray-200">
            {paginatedData.length === 0 ? (
              <tr>
                <td
                  colSpan={config.columns.length + (config.selectable ? 1 : 0)}
                  className="px-4 py-8 text-center text-gray-500"
                >
                  No data available
                </td>
              </tr>
            ) : (
              paginatedData.map((row, index) => (
                <tr key={row.id || index} className="hover:bg-gray-50">
                  {config.selectable && (
                    <td className="px-4 py-3">
                      <input
                        type="checkbox"
                        checked={selectedRows.has(row.id)}
                        onChange={() => handleSelectRow(row.id)}
                        className="rounded border-gray-300"
                      />
                    </td>
                  )}
                  {config.columns.map((column) => (
                    <td
                      key={column.key}
                      className={`px-4 py-3 text-sm text-gray-900 ${column.align || 'left'}`}
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
          <div className="text-sm text-gray-700">
            Showing {(currentPage - 1) * pageSize + 1} to{' '}
            {Math.min(currentPage * pageSize, sortedData.length)} of {sortedData.length} results
          </div>
          <div className="flex gap-2">
            <button
              onClick={() => setCurrentPage(currentPage - 1)}
              disabled={currentPage === 1}
              className="px-3 py-1 text-sm border border-gray-300 rounded disabled:opacity-50"
            >
              Previous
            </button>
            <span className="px-3 py-1 text-sm">
              Page {currentPage} of {totalPages}
            </span>
            <button
              onClick={() => setCurrentPage(currentPage + 1)}
              disabled={currentPage === totalPages}
              className="px-3 py-1 text-sm border border-gray-300 rounded disabled:opacity-50"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
