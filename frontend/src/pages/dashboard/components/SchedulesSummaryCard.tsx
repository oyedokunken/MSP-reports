import { AnimatedCard } from "@/components/ui/AnimatedCard";
import { Calendar } from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";

type SchedulesSummaryCardProps = {
    isLoading: boolean;
    totalSchedules: number;
    activeSchedules: number;
    className?: string;
};

export function SchedulesSummaryCard({
    isLoading,
    totalSchedules,
    activeSchedules,
    className = "",
}: SchedulesSummaryCardProps) {

    if (isLoading) {
        return (
            <AnimatedCard 
                className={`p-6 h-full overflow-hidden relative ${className}`}
                hoverLift={false}
            >
                <div className="flex flex-col h-full justify-between">
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <Skeleton className="h-5 w-5 rounded" />
                            <Skeleton className="h-5 w-32" />
                        </div>
                        <Skeleton className="mt-2 h-9 w-12" />
                    </div>
                    <Skeleton className="h-4 w-40" />
                </div>
                <div className="absolute right-6 top-1/2 -translate-y-1/2">
                    <Skeleton className="h-12 w-12 rounded-lg" />
                </div>
            </AnimatedCard>
        );
    }

    return (
        <AnimatedCard 
            className={`p-6 h-full overflow-hidden relative group ${className}`}
            hoverLift={false}
        >
            <style>{`
                @keyframes dna-spin {
                    0%, 100% { transform: translateX(-15px) scale(0.8); z-index: 1; opacity: 0.3; }
                    50% { transform: translateX(15px) scale(1.2); z-index: 10; opacity: 1; }
                }
                @keyframes dna-spin-reverse {
                    0%, 100% { transform: translateX(15px) scale(1.2); z-index: 10; opacity: 1; }
                    50% { transform: translateX(-15px) scale(0.8); z-index: 1; opacity: 0.3; }
                }
                .dna-node {
                    width: 6px;
                    height: 6px;
                    border-radius: 50%;
                    position: absolute;
                }
                .dna-bar {
                    height: 1px;
                    background: currentColor;
                    position: absolute;
                    left: 50%;
                    transform: translateX(-50%);
                    opacity: 0.2;
                    width: 20px;
                    animation: bar-scale 2s ease-in-out infinite;
                }
                @keyframes bar-scale {
                    0%, 100% { width: 0px; opacity: 0; }
                    50% { width: 30px; opacity: 0.2; }
                }
            `}</style>

            {/* THE DNA SPIRAL BACKGROUND EFFECT - MOVED TO FAR RIGHT */}
            <div className="absolute top-0 right-0 bottom-0 w-24 z-0 opacity-60 dark:opacity-40 pointer-events-none flex flex-col justify-around py-4">
                {[...Array(8)].map((_, i) => (
                    <div key={i} className="relative h-4 w-full flex items-center justify-center">
                        <div 
                            className="dna-node bg-blue-950 dark:bg-blue-400" 
                            style={{ animation: `dna-spin 2s ease-in-out infinite`, animationDelay: `${i * 0.2}s` }}
                        />
                        <div 
                            className="dna-node bg-blue-900/60 dark:bg-blue-300/60" 
                            style={{ animation: `dna-spin-reverse 2s ease-in-out infinite`, animationDelay: `${i * 0.2}s` }}
                        />
                        <div 
                            className="dna-bar text-blue-950 dark:text-blue-400" 
                            style={{ animationDelay: `${i * 0.2}s`, top: '50%' }}
                        />
                    </div>
                ))}
            </div>

            <div className="relative z-10 flex flex-col h-full justify-between">
                <div>
                    <div className="flex items-center gap-2 mb-2">
                        <Calendar className="h-5 w-5 text-primary" />
                        <p className="text-base font-medium text-foreground flex items-center gap-2">
                            Report Schedules
                        </p>
                    </div>
                    <p className="text-3xl font-bold text-foreground">
                        {isLoading ? "..." : activeSchedules}
                    </p>
                </div>
                
                <div className="flex items-center gap-2">
                    <span className="relative flex h-2 w-2">
                        <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                    </span>
                    <p className="text-xs font-medium text-green-600 dark:text-green-400">
                        {activeSchedules} Active / {totalSchedules} Total
                    </p>
                </div>
            </div>
        </AnimatedCard>
    );
}
