import axios, {
    type AxiosInstance,
    type AxiosRequestConfig,
    type AxiosResponse,
} from "axios";
import {
    verifyTenantData,
    logIsolationViolation,
} from "@/utils/tenantIsolation";

/**
 * Multi-Tenant API Client
 * Automatically adds tenant context and verifies data isolation
 */

class TenantApiClient {
    private client: AxiosInstance;
    private companyId: string | null = null;

    constructor() {
        this.client = axios.create({
            baseURL: import.meta.env.VITE_API_BASE_URL || "/api",
            headers: {
                "Content-Type": "application/json",
            },
            withCredentials: true,
        });

        this.setupInterceptors();
    }

    /**
     * Set current tenant context
     */
    setTenant(companyId: string): void {
        this.companyId = companyId;
    }

    /**
     * Clear tenant context
     */
    clearTenant(): void {
        this.companyId = null;
    }

    /**
     * Setup request and response interceptors
     */
    private setupInterceptors(): void {
        // Request interceptor - add tenant headers
        this.client.interceptors.request.use(
            (config) => {
                // Add tenant context headers
                if (this.companyId) {
                    config.headers["X-Company-ID"] = this.companyId;
                }

                // Add authentication token
                const token = localStorage.getItem("auth_token");
                if (token) {
                    config.headers["Authorization"] = `Bearer ${token}`;
                }

                return config;
            },
            (error) => {
                return Promise.reject(error);
            }
        );

        // Response interceptor - verify tenant isolation
        this.client.interceptors.response.use(
            (response) => {
                // Verify data belongs to current tenant
                if (this.companyId && response.data) {
                    const verification = verifyTenantData(
                        response.data,
                        this.companyId
                    );

                    if (!verification.isValid) {
                        // Log isolation violation
                        const userId =
                            localStorage.getItem("user_id") || "unknown";
                        verification.errors.forEach((error) => {
                            console.error(error);
                            logIsolationViolation(
                                response.config.url || "unknown",
                                this.companyId!,
                                "unknown",
                                userId
                            );
                        });

                        // In production, you might want to throw an error
                        if (import.meta.env.PROD) {
                            throw new Error(
                                "Tenant isolation violation detected"
                            );
                        }
                    }
                }

                return response;
            },
            (error) => {
                // Handle authentication errors
                if (error.response?.status === 401) {
                    // Clear auth and redirect to login
                    localStorage.removeItem("auth_token");
                    localStorage.removeItem("user_id");
                    window.location.href = "/login";
                }

                // Handle forbidden errors (wrong tenant)
                if (error.response?.status === 403) {
                    console.error(
                        "Access forbidden - possible tenant mismatch"
                    );
                }

                return Promise.reject(error);
            }
        );
    }

    /**
     * HTTP Methods
     */
    async get<T = any>(
        url: string,
        config?: AxiosRequestConfig
    ): Promise<AxiosResponse<T>> {
        return this.client.get<T>(url, config);
    }

    async post<T = any>(
        url: string,
        data?: any,
        config?: AxiosRequestConfig
    ): Promise<AxiosResponse<T>> {
        return this.client.post<T>(url, data, config);
    }

    async put<T = any>(
        url: string,
        data?: any,
        config?: AxiosRequestConfig
    ): Promise<AxiosResponse<T>> {
        return this.client.put<T>(url, data, config);
    }

    async patch<T = any>(
        url: string,
        data?: any,
        config?: AxiosRequestConfig
    ): Promise<AxiosResponse<T>> {
        return this.client.patch<T>(url, data, config);
    }

    async delete<T = any>(
        url: string,
        config?: AxiosRequestConfig
    ): Promise<AxiosResponse<T>> {
        return this.client.delete<T>(url, config);
    }
}

// Export singleton instance
export const apiClient = new TenantApiClient();

// Export class for testing
export { TenantApiClient };
