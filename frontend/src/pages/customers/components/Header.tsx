import { Users } from 'lucide-react'

export function CustomersHeader () {
  return (
    <div className="flex items-center justify-between">
      <div>
        <h1 className="text-3xl font-bold text-foreground">Customers</h1>
        <p className="mt-2 text-muted-foreground">Manage customer mappings across integrations</p>
      </div>
      <button className="flex items-center text-white gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">
        <Users className="h-4 w-4 text-white" />
        Review Mappings
      </button>
    </div>
  )
}


