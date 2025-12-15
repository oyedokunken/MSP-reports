import { useState, useEffect, useRef } from 'react';

export function useCountdown(targetDate: string | null, onCountdownComplete?: () => void): string {
    const [countdown, setCountdown] = useState<string>('');
    const hasTriggeredCallback = useRef(false);

    useEffect(() => {
        if (!targetDate) {
            setCountdown('Manual only');
            return;
        }

        const updateCountdown = () => {
            const now = new Date().getTime();
            const target = new Date(targetDate).getTime();
            const distance = target - now;

            if (distance < 0) {
                setCountdown('Syncing soon...');

                // Trigger callback once when countdown completes
                if (onCountdownComplete && !hasTriggeredCallback.current) {
                    hasTriggeredCallback.current = true;
                    onCountdownComplete();
                }
                return;
            }

            // Reset callback trigger if countdown is active again (new sync scheduled)
            hasTriggeredCallback.current = false;

            const hours = Math.floor(distance / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            if (hours > 0) {
                setCountdown(`${hours}h ${minutes}m`);
            } else if (minutes > 0) {
                setCountdown(`${minutes}m ${seconds}s`);
            } else {
                setCountdown(`${seconds}s`);
            }
        };

        updateCountdown(); // Initial update
        const interval = setInterval(updateCountdown, 1000); // Update every second

        return () => clearInterval(interval);
    }, [targetDate, onCountdownComplete]);

    return countdown;
}
