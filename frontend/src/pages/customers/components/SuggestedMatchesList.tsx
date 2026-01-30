import { Badge } from '@/components/ui/badge'

import type { SuggestedMatch } from '../types'

interface SuggestedMatchesListProps {
    matches: SuggestedMatch[]
    selectedCustomerId: number | null
    onSelectCustomer: (id: number) => void
}

export function SuggestedMatchesList({
    matches,
    selectedCustomerId,
    onSelectCustomer,
}: SuggestedMatchesListProps) {
    if (matches.length === 0) return null

    return (
        <div className="space-y-2 mt-3">
            {matches.map((match) => (
                <button
                    key={match.customer_id}
                    type="button"
                    onClick={() => onSelectCustomer(match.customer_id)}
                    className={`w-full text-left rounded-lg border p-3 ${selectedCustomerId === match.customer_id
                        ? 'border-primary bg-primary/10'
                        : 'border-border hover:bg-accent/50'
                        }`}
                >
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="font-medium">{match.canonical_name}</p>
                            <p className="text-xs text-muted-foreground">
                                {match.integrations?.length || 0} integrations
                            </p>
                        </div>
                        <Badge>{match.confidence_score}% match</Badge>
                    </div>
                </button>
            ))}
        </div>
    )
}
