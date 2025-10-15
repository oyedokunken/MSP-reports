import { Dialog, DialogTrigger, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from '@/components/ui/dialog'
import { Cable, Plus } from 'lucide-react'

type DashboardHeaderActionsProps = {
  onToastSuccess: (title: string, description?: string) => void
  onToastInfo: (title: string, description?: string) => void
}

export function DashboardHeaderActions ({ onToastSuccess, onToastInfo }: DashboardHeaderActionsProps) {
  return (
    <div className="flex gap-2">
      <Dialog>
        <DialogTrigger asChild>
          <button
            className="flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
          >
            <Plus className="h-4 w-4 text-white" />
            Create Schedule
          </button>
        </DialogTrigger>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Create schedule</DialogTitle>
            <DialogDescription>
              Set up when and how often a report should run. You can change this later.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-4 py-2">
            <div className="grid grid-cols-4 items-center gap-4">
              <label className="col-span-4 text-sm text-muted-foreground">Report</label>
              <input className="col-span-4 rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring" placeholder="e.g., Monthly Service Summary" />
            </div>
            <div className="grid grid-cols-4 items-center gap-4">
              <label className="col-span-4 text-sm text-muted-foreground">Frequency</label>
              <select className="col-span-4 rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-ring">
                <option>Daily</option>
                <option>Weekly</option>
                <option>Monthly</option>
              </select>
            </div>
          </div>
          <DialogFooter>
            <DialogClose asChild>
              <button className="rounded-md border border-border bg-background px-4 py-2 text-sm font-medium hover:bg-muted">Cancel</button>
            </DialogClose>
            <button onClick={() => onToastSuccess('Created', 'Schedule drafted')} className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Continue</button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <button
        className="flex items-center gap-2 rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-muted"
        onClick={() => onToastInfo('Coming Soon', 'Integration setup wizard')}
      >
        <Cable className="h-4 w-4" />
        Add Integration
      </button>
    </div>
  )
}


