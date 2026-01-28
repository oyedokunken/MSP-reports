/**
 * Centralized logging utility with environment-aware logging
 * and optional integration with error tracking services
 */

interface LogContext {
    [key: string]: unknown;
}

class Logger {
    private isDevelopment = import.meta.env.DEV;

    /**
     * Log informational messages (only in development)
     */
    info(message: string, context?: LogContext): void {
        if (this.isDevelopment) {
            // eslint-disable-next-line no-console
            console.log(`[INFO] ${message}`, context || '');
        }
    }

    /**
     * Log warning messages (only in development)
     */
    warn(message: string, context?: LogContext): void {
        if (this.isDevelopment) {
            console.warn(`[WARN] ${message}`, context || '');
        }
    }

    /**
     * Log error messages (always logged)
     * In production, this could send to error tracking service
     */
    error(message: string, error: unknown, context?: LogContext): void {
        const errorMessage = `[ERROR] ${message}`;

        if (this.isDevelopment) {
            console.error(errorMessage, { error, ...context });
        } else {
            console.error(errorMessage);

        }
    }

    /**
     * Log debug messages (only in development)
     */
    debug(message: string, data?: unknown): void {
        if (this.isDevelopment) {
            // eslint-disable-next-line no-console
            console.debug(`[DEBUG] ${message}`, data || '');
        }
    }

    /**
     * Log API errors with structured data
     */
    apiError(endpoint: string, error: unknown, context?: LogContext): void {
        this.error(`API call failed: ${endpoint}`, error, context);
    }
}

export const logger = new Logger();

export default logger;
