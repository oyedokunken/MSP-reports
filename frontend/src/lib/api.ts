import axios, { type AxiosInstance, type AxiosError } from "axios";
import type { ApiResponse, ApiError } from "@/types/api";

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

apiClient.interceptors.response.use(
    (response) => {
        return response;
    },
    (error: AxiosError<ApiError>) => {
        if (error.response?.status === 401) {
            const isLogoutRequest = error.config?.url?.includes("/auth/logout");

            if (!isLogoutRequest) {
                localStorage.removeItem("auth_token");
                localStorage.removeItem("user");
                localStorage.removeItem("company");

                if (window.location.pathname !== "/login") {
                    window.location.replace("/login");
                }
            }
        }

        if (error.response?.status === 403) {
            console.error("Permission denied:", error.response.data);
        }

        if (!error.response) {
            console.error("Network error:", error.message);
        }

        return Promise.reject(error);
    }
);

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
