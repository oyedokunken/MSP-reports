import { useCallback, useRef, useState } from "react";

export const useQuickSync = (
    onSuccess: (title: string, description?: string) => void,
    onError: (title: string, description?: string) => void
) => {
    const [syncingId, setSyncingId] = useState<number | null>(null);
    const [progress, setProgress] = useState<number>(0);
    const intervalRef = useRef<number | null>(null);

    const handleQuickSync = useCallback(
        async (integrationId: number, name: string) => {
            setSyncingId(integrationId);
            setProgress(0);
            try {
                intervalRef.current = window.setInterval(() => {
                    setProgress((p) => Math.min(p + Math.random() * 20, 90));
                }, 250);
                await new Promise((r) => setTimeout(r, 1500));
                if (intervalRef.current)
                    window.clearInterval(intervalRef.current);
                setProgress(100);
                await new Promise((r) => setTimeout(r, 250));
                onSuccess("Sync Complete", `${name} synced successfully`);
            } catch (e) {
                onError("Sync Failed", `Failed to sync ${name}`);
            } finally {
                setSyncingId(null);
                setProgress(0);
            }
        },
        [onSuccess, onError]
    );

    return { syncingId, progress, handleQuickSync };
};
