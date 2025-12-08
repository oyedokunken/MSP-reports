/**
 * Common API response types
 * Following Laravel API Resource pattern
 */

export interface ApiSuccessResponse<T> {
    status: 'success'
    data: T
    message?: string
}

export interface ApiErrorResponse {
    status: 'error'
    message: string
    errors?: Record<string, string[]>
}

export interface PaginationMeta {
    current_page: number
    per_page: number
    total: number
    last_page: number
    from: number | null
    to: number | null
}

export interface PaginatedResponse<T> {
    status: 'success'
    data: T[]
    pagination: PaginationMeta
}
