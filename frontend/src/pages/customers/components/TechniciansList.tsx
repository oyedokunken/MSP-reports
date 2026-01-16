import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion'
import { Badge } from '@/components/ui/badge'
import { Users } from 'lucide-react'

type TechniciansListProps = {
    technicians: Array<{
        name: string
        ticket_count: number
    }>
}

export function TechniciansList({ technicians }: TechniciansListProps) {
    if (!technicians || technicians.length === 0) return null

    return (
        <Accordion type="multiple" className="w-full">
            <AccordionItem value="technicians" className="border-b-0 bg-transparent">
                <AccordionTrigger className="hover:no-underline">
                    <div className="flex items-center gap-2">
                        <Users className="h-4 w-4 text-primary" />
                        <span className="font-semibold">Assigned Technicians</span>
                        <Badge variant="secondary" className="ml-2">
                            {technicians.length}
                        </Badge>
                    </div>
                </AccordionTrigger>
                <AccordionContent className="bg-transparent">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                        {technicians.map((tech, idx) => (
                            <div key={idx} className="flex items-center gap-3 p-3 rounded-lg bg-muted/50 border">
                                <Users className="h-4 w-4 text-primary flex-shrink-0" />
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm font-medium truncate">{tech.name}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {tech.ticket_count} {tech.ticket_count === 1 ? 'ticket' : 'tickets'}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    )
}
