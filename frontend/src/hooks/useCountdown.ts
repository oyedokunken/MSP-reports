import { useState, useEffect, useRef, useCallback } from 'react';

const MS_PER_SECOND = 1000;
const MS_PER_MINUTE = MS_PER_SECOND * 60;
const MS_PER_HOUR = MS_PER_MINUTE * 60;

export function useCountdown(targetDate: string | null, onCountdownComplete?: () => void): string {
    const [countdown, setCountdown] = useState<string>('');
    const hasTriggeredCallback = useRef(false);

    const onCompleteRef = useRef(onCountdownComplete);
    onCompleteRef.current = onCountdownComplete;

    const handleComplete = useCallback(() => {
        if (onCompleteRef.current && !hasTriggeredCallback.current) {
            hasTriggeredCallback.current = true;
            onCompleteRef.current();
        }
    }, []);

    useEffect(() => {
        if (!targetDate) {
            setCountdown('Manual only');
            return;
        }

        const updateCountdown = () => {
            const now = Date.now();
            const target = new Date(targetDate).getTime();
            const distance = target - now;

            if (distance < 0) {
                setCountdown('Syncing soon...');
                handleComplete();
                return;
            }

            hasTriggeredCallback.current = false;

            const hours = Math.floor(distance / MS_PER_HOUR);
            const minutes = Math.floor((distance % MS_PER_HOUR) / MS_PER_MINUTE);
            const seconds = Math.floor((distance % MS_PER_MINUTE) / MS_PER_SECOND);

            if (hours > 0) {
                setCountdown(`${hours}h ${minutes}m`);
            } else if (minutes > 0) {
                setCountdown(`${minutes}m ${seconds}s`);
            } else {
                setCountdown(`${seconds}s`);
            }
        };

        updateCountdown();
        const interval = setInterval(updateCountdown, MS_PER_SECOND);

        return () => clearInterval(interval);
    }, [targetDate, handleComplete]);

    return countdown;
}
