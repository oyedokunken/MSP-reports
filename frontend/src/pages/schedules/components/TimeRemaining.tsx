import { useEffect, useState } from 'react'
import { getFormattedTimeRemaining } from '../utils/formatters'

interface TimeRemainingProps {
    dateString: string | null
}

export function TimeRemaining({ dateString }: TimeRemainingProps) {
    const [timeRemaining, setTimeRemaining] = useState<string>('')

    useEffect(() => {
        // Initial calculation
        const updateTime = () => {
            const remaining = getFormattedTimeRemaining(dateString)
            setTimeRemaining(remaining === 'Due now' ? remaining : `in ${remaining}`)
        }

        updateTime()

        // Update every second
        const intervalId = setInterval(updateTime, 1000)

        return () => clearInterval(intervalId)
    }, [dateString])

    if (!dateString) return null

    return (
        <span className="text-xs text-muted-foreground block text-nowrap">
            {timeRemaining}
        </span>
    )
}
