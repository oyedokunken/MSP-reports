import axios, { type AxiosInstance, type AxiosError } from "axios";
import type { ApiError } from "@/types/api";
import { STORAGE_KEYS } from "@/lib/constants";
import { logger } from "@/lib/logger";

const getBaseURL = () => {
    if (import.meta.env.VITE_API_BASE_URL) {
        return import.meta.env.VITE_API_BASE_URL;
    }

    return `${window.location.origin}/api`;
};

const apiClient: AxiosInstance = axios.create({
    baseURL: getBaseURL(),
    timeout: 30000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
    withCredentials: true,
});

apiClient.interceptors.request.use(
    (config) => {
        // Retrieve authentication token with priority: localStorage (persistent) > sessionStorage (session-only)
        let token = localStorage.getItem(STORAGE_KEYS.AUTH_TOKEN);
        if (!token) {
            token = sessionStorage.getItem(STORAGE_KEYS.AUTH_TOKEN);
        }

        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

apiClient.interceptors.response.use(
    (response) => {
        return response;
    },
    (error: AxiosError<ApiError>) => {
        if (error.response?.status === 401) {
            // Handle unauthorized access: clear session/local storage and redirect to login,
            // unless it's a logout request or public share link access.
            const isLogoutRequest = error.config?.url?.includes("/auth/logout");
            const isShareRequest = error.config?.url?.includes("/share");

            if (!isLogoutRequest && !isShareRequest) {
                localStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
                localStorage.removeItem(STORAGE_KEYS.USER);
                localStorage.removeItem(STORAGE_KEYS.COMPANY);
                sessionStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
                sessionStorage.removeItem(STORAGE_KEYS.USER);
                sessionStorage.removeItem(STORAGE_KEYS.COMPANY);

                if (window.location.pathname !== "/login") {
                    window.location.replace("/login");
                }
            }
        }

        if (error.response?.status === 403) {
            logger.warn("Permission denied", { data: error.response.data });
        }

        if (!error.response) {
            logger.warn("Network error", { message: error.message });
        }

        return Promise.reject(error);
    }
);

/**
 * Generic API request function with proper typing
 * Returns the response data directly (unwrapped from axios response)
 */
export async function apiRequest<T>(
    method: "get" | "post" | "put" | "patch" | "delete",
    url: string,
    data?: unknown,
    params?: Record<string, unknown>
): Promise<T> {
    try {
        const response = await apiClient.request<T>({
            method,
            url,
            data,
            params,
        });
        return response.data;
    } catch (error) {
        const axiosError = error as AxiosError<ApiError>;

        // Extract the error data from the response if available
        const apiError: ApiError = axiosError.response?.data || {
            success: false,
            message: axiosError.message || "An unexpected error occurred",
        };

        // Ensure we always have a message
        if (!apiError.message && axiosError.message) {
            apiError.message = axiosError.message;
        }

        throw apiError;
    }
}

/**
 * Type-safe API client
 * All methods return the response type directly - no need for `as unknown as` casts
 */
export const api = {
    get: <T>(url: string, params?: Record<string, unknown>): Promise<T> =>
        apiRequest<T>("get", url, undefined, params),

    post: <T>(url: string, data?: unknown): Promise<T> =>
        apiRequest<T>("post", url, data),

    put: <T>(url: string, data?: unknown): Promise<T> =>
        apiRequest<T>("put", url, data),

    patch: <T>(url: string, data?: unknown): Promise<T> =>
        apiRequest<T>("patch", url, data),

    delete: <T>(url: string): Promise<T> =>
        apiRequest<T>("delete", url),
};

export default apiClient;
