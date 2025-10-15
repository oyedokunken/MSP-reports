import { AlertCircle, CheckCircle } from 'lucide-react'
import type { MappingRow } from '../types'

type CustomersTableProps = {
  rows: MappingRow[]
  getCanonicalName: (id?: string) => string
  onMapClick: (rowId: number, preselect?: string) => void
}

export function CustomersTable ({ rows, getCanonicalName, onMapClick }: CustomersTableProps) {
  return (
    <div className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] shadow-sm overflow-hidden">
      <div className="overflow-x-auto">
        <table className="w-full">
          <thead className="bg-muted/50 border-b border-border">
            <tr>
              <th className="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">External Name</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Integration</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Mapped Customer</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Confidence</th>
              <th className="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Status</th>
              <th className="px-6 py-3 text-right text-xs font-medium text-muted-foreground uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {rows.map((row) => {
              const confidenceColor = row.confidence >= 95 ? 'text-green-600' : row.confidence >= 70 ? 'text-orange-500' : 'text-red-600'
              const isPending = row.status === 'pending'
              return (
                <tr key={row.id} className="hover:bg-muted/50 transition-colors">
                  <td className="px-6 py-4 whitespace-nowrap">
                    <p className="text-sm font-medium text-foreground">{row.externalName}</p>
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-sm text-muted-foreground">{row.integration}</td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    <p className="text-sm text-foreground">{row.mappedCustomerId ? getCanonicalName(row.mappedCustomerId) : '—'}</p>
                  </td>
                  <td className={`px-6 py-4 whitespace-nowrap text-sm font-medium ${confidenceColor}`}>{row.confidence}%</td>
                  <td className="px-6 py-4 whitespace-nowrap">
                    {isPending ? (
                      <span className="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                        <AlertCircle className="h-3 w-3" />
                        Pending
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700">
                        <CheckCircle className="h-3 w-3" />
                        Mapped
                      </span>
                    )}
                  </td>
                  <td className="px-6 py-4 whitespace-nowrap text-right text-sm">
                    {isPending ? (
                      <div className="inline-flex items-center gap-3">
                        <button className="rounded-md border border-border bg-background px-3 py-1.5 text-sm font-medium text-foreground hover:bg-muted" onClick={() => onMapClick(row.id, row.mappedCustomerId)}>
                          Map
                        </button>
                        <button className="text-foreground/70 hover:text-foreground">Ignore</button>
                      </div>
                    ) : (
                      <span className="text-muted-foreground">—</span>
                    )}
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </div>
  )
}


