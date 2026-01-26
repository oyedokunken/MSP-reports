/**
 * Common API response types
 * Re-exports from main api.ts for backward compatibility
 * @deprecated Import from '@/types/api' instead
 */

export type {
    ApiResponse as ApiSuccessResponse,
    ApiError as ApiErrorResponse,
    PaginationMeta,
    PaginatedResponse,
} from '../api'
