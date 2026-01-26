/**
 * Application-wide constants
 * Centralizes magic numbers and configuration values
 */

export const PAGINATION = {
    INTEGRATIONS_PER_PAGE: 9,
    CUSTOMERS_PER_PAGE: 21,
    DEFAULT_PER_PAGE: 10,
    NOTIFICATIONS_PER_PAGE: 20,
    USERS_PER_PAGE: 10,
    REPORTS_PER_PAGE: 9,
} as const

export const TIMEOUTS = {
    API_REQUEST_MS: 30000,
    SUCCESS_MODAL_MS: 2000,
    SUCCESS_TOAST_MS: 1500,
} as const

export const STORAGE_KEYS = {
    AUTH_TOKEN: 'auth_token',
    USER: 'user',
    COMPANY: 'company',
    USER_ID: 'user_id',
} as const
