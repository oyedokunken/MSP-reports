import type { CanonicalCustomer } from '../types'

type MapDialogProps = {
  isOpen: boolean
  search: string
  setSearch: (v: string) => void
  filteredCanonical: CanonicalCustomer[]
  selectedCustomerId: string
  setSelectedCustomerId: (v: string) => void
  onClose: () => void
  onConfirm: () => void
}

export function MapDialog ({ isOpen, search, setSearch, filteredCanonical, selectedCustomerId, setSelectedCustomerId, onClose, onConfirm }: MapDialogProps) {
  if (!isOpen) return null
  return (
    <div className="fixed inset-0 z-50">
      <div className="absolute inset-0 bg-black/50" onClick={onClose} />
      <div role="dialog" aria-modal="true" className="absolute inset-0 flex items-center justify-center p-4">
        <div className="w-full max-w-lg rounded-lg border border-border bg-background shadow-lg">
          <div className="flex items-center justify-between border-b border-border px-4 py-3">
            <h3 className="text-sm font-semibold text-foreground">Map Customer</h3>
            <button className="text-muted-foreground hover:text-foreground" onClick={onClose}>✕</button>
          </div>
          <div className="px-4 py-4 space-y-4">
            <p className="text-sm text-muted-foreground">Select the canonical customer to map this integration record to.</p>
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder="Search customers..."
              className="w-full rounded-md border border-input bg-background dark:bg-muted/20 px-3 py-2 text-sm text-foreground placeholder-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring"
            />
            <div className="max-h-56 overflow-auto rounded-md border border-border">
              <ul>
                {filteredCanonical.map((c) => (
                  <li
                    key={c.id}
                    className={`flex cursor-pointer items-center justify-between px-3 py-2 text-sm hover:bg-muted ${selectedCustomerId === c.id ? 'bg-muted' : ''}`}
                    onClick={() => setSelectedCustomerId(c.id)}
                  >
                    <span>{c.name}</span>
                    <span className={`h-2 w-2 rounded-full ${selectedCustomerId === c.id ? 'bg-primary' : 'bg-transparent border border-muted-foreground/40'}`} />
                  </li>
                ))}
                {filteredCanonical.length === 0 && (
                  <li className="px-3 py-6 text-center text-sm text-muted-foreground">No customers found</li>
                )}
              </ul>
            </div>
          </div>
          <div className="flex items-center justify-end gap-2 border-t border-border px-4 py-3">
            <button className="rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted" onClick={onClose}>Cancel</button>
            <button className="rounded-md bg-primary px-3 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50" onClick={onConfirm} disabled={!selectedCustomerId}>Map Customer</button>
          </div>
        </div>
      </div>
    </div>
  )
}


