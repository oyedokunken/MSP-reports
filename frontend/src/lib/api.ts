import axios, { type AxiosInstance, type AxiosError } from "axios";
import type { ApiResponse, ApiError } from "@/types/api";

// Create axios instance with default config
const apiClient: AxiosInstance = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || "/api",
    timeout: 30000,
    headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
    },
    withCredentials: true, // For session-based auth
});

// Request interceptor - Add auth token
apiClient.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem("auth_token");
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor - Handle errors
apiClient.interceptors.response.use(
    (response) => {
        return response;
    },
    (error: AxiosError<ApiError>) => {
        // Handle 401 Unauthorized - redirect to login (except during logout)
        if (error.response?.status === 401) {
            const isLogoutRequest = error.config?.url?.includes("/auth/logout");

            if (!isLogoutRequest) {
                localStorage.removeItem("auth_token");
                localStorage.removeItem("user");
                localStorage.removeItem("company");

                // Use replace to prevent back button issues
                if (window.location.pathname !== "/login") {
                    window.location.replace("/login");
                }
            }
        }

        // Handle 403 Forbidden
        if (error.response?.status === 403) {
            console.error("Permission denied:", error.response.data);
        }

        // Handle network errors
        if (!error.response) {
            console.error("Network error:", error.message);
        }

        return Promise.reject(error);
    }
);

// Generic request wrapper with type safety
export async function apiRequest<T>(
    method: "get" | "post" | "put" | "patch" | "delete",
    url: string,
    data?: unknown,
    params?: Record<string, unknown>
): Promise<ApiResponse<T>> {
    try {
        const response = await apiClient.request<ApiResponse<T>>({
            method,
            url,
            data,
            params,
        });
        return response.data;
    } catch (error) {
        const axiosError = error as AxiosError<ApiError>;
        throw (
            axiosError.response?.data || {
                success: false,
                message: "An unexpected error occurred",
            }
        );
    }
}

// Convenience methods
export const api = {
    get: <T>(url: string, params?: Record<string, unknown>) =>
        apiRequest<T>("get", url, undefined, params),

    post: <T>(url: string, data?: unknown) => apiRequest<T>("post", url, data),

    put: <T>(url: string, data?: unknown) => apiRequest<T>("put", url, data),

    patch: <T>(url: string, data?: unknown) =>
        apiRequest<T>("patch", url, data),

    delete: <T>(url: string) => apiRequest<T>("delete", url),
};

export default apiClient;
