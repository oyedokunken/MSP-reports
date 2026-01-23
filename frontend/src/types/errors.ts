/**
 * Shared error types for consistent error handling across the application
 */

/**
 * Standard API error response structure from backend
 */
export interface ApiErrorResponse {
    response?: {
        data?: {
            message?: string
            errors?: Record<string, string[]>
        }
        status?: number
    }
    message?: string
}

/**
 * Extract error message from API error response
 * @param error - The caught error object
 * @param fallbackMessage - Default message if extraction fails
 */
export function getErrorMessage(error: unknown, fallbackMessage = 'An unexpected error occurred'): string {
    const apiError = error as ApiErrorResponse
    return apiError.response?.data?.message || apiError.message || fallbackMessage
}

/**
 * Check if error is a network error (no response from server)
 */
export function isNetworkError(error: unknown): boolean {
    const apiError = error as ApiErrorResponse
    return !apiError.response && !!apiError.message
}

/**
 * Check if error is an authentication error (401)
 */
export function isAuthError(error: unknown): boolean {
    const apiError = error as ApiErrorResponse
    return apiError.response?.status === 401
}

/**
 * Check if error is a forbidden error (403)
 */
export function isForbiddenError(error: unknown): boolean {
    const apiError = error as ApiErrorResponse
    return apiError.response?.status === 403
}
