import { logger } from '@/lib/logger';

/**
 * Performance monitoring utilities for tracking dashboard load times
 */

interface PerformanceMetrics {
    name: string;
    duration: number;
    startTime: number;
    endTime: number;
}

class PerformanceMonitor {
    private marks: Map<string, number> = new Map();
    private metrics: PerformanceMetrics[] = [];

    /**
     * Mark the start of a performance measurement
     */
    mark(name: string): void {
        const timestamp = performance.now();
        this.marks.set(name, timestamp);

        // Use native Performance API if available
        if (typeof performance.mark === 'function') {
            performance.mark(name);
        }
    }

    /**
     * Measure the duration between two marks
     */
    measure(name: string, startMark: string, endMark?: string): number | null {
        const startTime = this.marks.get(startMark);

        if (!startTime) {
            logger.warn(`Performance: Start mark "${startMark}" not found`);
            return null;
        }

        const endTime = endMark ? this.marks.get(endMark) : performance.now();

        if (endMark && !endTime) {
            logger.warn(`Performance: End mark "${endMark}" not found`);
            return null;
        }

        const duration = (endTime || performance.now()) - startTime;

        const metric: PerformanceMetrics = {
            name,
            duration,
            startTime,
            endTime: endTime || performance.now(),
        };

        this.metrics.push(metric);

        // Use native Performance API if available
        if (typeof performance.measure === 'function' && endMark) {
            try {
                performance.measure(name, startMark, endMark);
            } catch {
                // Silently fail if marks don't exist
            }
        }

        // Log to console in development
        if (import.meta.env.DEV) {
            logger.info(`⚡ Performance: ${name} took ${duration.toFixed(2)}ms`);
        }

        return duration;
    }

    /**
     * Get all recorded metrics
     */
    getMetrics(): PerformanceMetrics[] {
        return [...this.metrics];
    }

    /**
     * Get a specific metric by name
     */
    getMetric(name: string): PerformanceMetrics | undefined {
        return this.metrics.find(m => m.name === name);
    }

    /**
     * Clear all marks and metrics
     */
    clear(): void {
        this.marks.clear();
        this.metrics = [];

        if (typeof performance.clearMarks === 'function') {
            performance.clearMarks();
        }
        if (typeof performance.clearMeasures === 'function') {
            performance.clearMeasures();
        }
    }

    /**
     * Log a summary of all metrics
     */
    logSummary(): void {
        if (this.metrics.length === 0) {
            logger.info('No performance metrics recorded');
            return;
        }

        logger.info('📊 Performance Summary:');
        this.metrics.forEach(metric => {
            logger.info(`  ${metric.name}: ${metric.duration.toFixed(2)}ms`);
        });
    }

    /**
     * Get Time to First Byte (TTFB) for a resource
     */
    getTTFB(resourceUrl: string): number | null {
        if (typeof performance.getEntriesByName !== 'function') {
            return null;
        }

        const entries = performance.getEntriesByName(resourceUrl, 'resource') as PerformanceResourceTiming[];

        if (entries.length === 0) {
            return null;
        }

        const entry = entries[0];
        return entry.responseStart - entry.requestStart;
    }
}

// Export singleton instance
export const performanceMonitor = new PerformanceMonitor();

// Convenience functions
export const markPerformance = (name: string) => performanceMonitor.mark(name);
export const measurePerformance = (name: string, startMark: string, endMark?: string) =>
    performanceMonitor.measure(name, startMark, endMark);
export const clearPerformance = () => performanceMonitor.clear();
export const logPerformanceSummary = () => performanceMonitor.logSummary();
