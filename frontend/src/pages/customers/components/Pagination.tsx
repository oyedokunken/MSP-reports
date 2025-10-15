export function CustomersPagination () {
  return (
    <div className="flex items-center justify-between">
      <p className="text-sm text-muted-foreground">
        Showing <span className="font-medium">1</span> to <span className="font-medium">4</span> of <span className="font-medium">24</span> customers
      </p>
      <div className="flex gap-2">
        <button className="rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted disabled:opacity-50">Previous</button>
        <button className="rounded-md border border-border bg-background px-3 py-2 text-sm font-medium text-foreground hover:bg-muted">Next</button>
      </div>
    </div>
  )
}


