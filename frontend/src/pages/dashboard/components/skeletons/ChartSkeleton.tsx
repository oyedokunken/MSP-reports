import { Skeleton } from "@/components/ui/skeleton";
import { Card, CardContent, CardHeader } from "@/components/ui/card";

export function ChartSkeleton() {
    return (
        <Card className="h-full border-border/50 flex flex-col">
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Skeleton className="h-4 w-4 rounded" />
                        <Skeleton className="h-5 w-40" />
                    </div>
                </div>
                <Skeleton className="h-3 w-48 mt-2" />
            </CardHeader>
            <CardContent className="flex-1 flex items-center justify-center">
                <div className="w-full h-full min-h-[200px] flex items-center justify-center">
                    <Skeleton className="h-full w-full rounded-lg" />
                </div>
            </CardContent>
        </Card>
    );
}
