import {
    createContext,
    useContext,
    useState,
    useEffect,
    type ReactNode,
} from "react";
import type {
    User,
    Company,
    LoginCredentials,
    LoginResponse,
    AuthState,
} from "@/types/auth";
import { api } from "@/lib/api";
import { useBranding } from "./BrandingContext";
import { useNotificationStore } from "@/stores/useNotificationStore";

interface AuthContextType extends AuthState {
    company: Company | null;
    login: (credentials: LoginCredentials) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
    const { setBranding } = useBranding();
    const listenForNotifications = useNotificationStore(state => state.listenForNotifications);
    const disconnectEcho = useNotificationStore(state => state.disconnectEcho);
    const [state, setState] = useState<AuthState>({
        user: null,
        token: null,
        isAuthenticated: false,
        isLoading: true,
    });
    const [company, setCompany] = useState<Company | null>(null);

    // Initialize auth state from localStorage or sessionStorage
    useEffect(() => {
        const initializeAuth = async () => {
            // Check localStorage first, then sessionStorage
            const storageType = localStorage.getItem("auth_storage_type");
            const storage = storageType === "session" ? sessionStorage : localStorage;

            // Also check sessionStorage if localStorage doesn't have token
            let token = storage.getItem("auth_token");
            let userStr = storage.getItem("user");
            let companyStr = storage.getItem("company");

            // Fallback: check the other storage if primary is empty
            if (!token) {
                const fallbackStorage = storageType === "session" ? localStorage : sessionStorage;
                token = fallbackStorage.getItem("auth_token");
                userStr = fallbackStorage.getItem("user");
                companyStr = fallbackStorage.getItem("company");
            }

            if (token && userStr) {
                try {
                    // Load company data and branding
                    if (companyStr) {
                        const companyData: Company = JSON.parse(companyStr);
                        setCompany(companyData);

                        // Set branding from company settings
                        if (companyData?.settings?.white_label) {
                            setBranding(companyData.settings.white_label);
                        }
                    }

                    // Verify token is still valid with backend BEFORE setting authenticated
                    // This prevents the flash of dashboard content before redirect
                    try {
                        const response = await api.get<User>("/auth/me");
                        if (response.success && response.data) {
                            // Token is valid, set authenticated state
                            setState({
                                user: response.data,
                                token,
                                isAuthenticated: true,
                                isLoading: false,
                            });

                            // Update stored user data with fresh data
                            const storageType = localStorage.getItem("auth_storage_type");
                            const activeStorage = storageType === "session" ? sessionStorage : localStorage;
                            activeStorage.setItem("user", JSON.stringify(response.data));

                            // Start listening for real-time notifications
                            listenForNotifications(response.data.user_id, token);
                        } else {
                            throw new Error("Invalid response");
                        }
                    } catch {
                        // Token is invalid/expired - clear everything and go to login
                        console.warn("Token validation failed - session expired");
                        localStorage.removeItem("auth_token");
                        localStorage.removeItem("user");
                        localStorage.removeItem("company");
                        localStorage.removeItem("auth_storage_type");
                        sessionStorage.removeItem("auth_token");
                        sessionStorage.removeItem("user");
                        sessionStorage.removeItem("company");
                        setBranding(null);
                        setState({
                            user: null,
                            token: null,
                            isAuthenticated: false,
                            isLoading: false,
                        });
                        setCompany(null);
                    }
                } catch (error) {
                    console.error("Failed to parse stored user:", error);
                    setState((prev) => ({ ...prev, isLoading: false }));
                    logout();
                }
            } else {
                setState((prev) => ({ ...prev, isLoading: false }));
            }
        };

        initializeAuth();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    const login = async (credentials: LoginCredentials) => {
        const { rememberMe, ...apiCredentials } = credentials;

        const response = await api.post<LoginResponse>(
            "/auth/login",
            apiCredentials
        );

        if (!response.data) {
            throw new Error("Login failed");
        }

        const user = response.data.user;
        const token = response.data.token;
        const companyData = response.data.company;

        // Use localStorage for "Remember Me", sessionStorage otherwise
        // localStorage persists across browser sessions
        // sessionStorage clears when browser/tab is closed
        const storage = rememberMe ? localStorage : sessionStorage;

        // Clear both storages first to avoid conflicts
        localStorage.removeItem("auth_token");
        localStorage.removeItem("user");
        localStorage.removeItem("company");
        sessionStorage.removeItem("auth_token");
        sessionStorage.removeItem("user");
        sessionStorage.removeItem("company");

        // Store in appropriate storage
        storage.setItem("auth_token", token);
        storage.setItem("user", JSON.stringify(user));
        storage.setItem("company", JSON.stringify(companyData));

        // Also store which storage type was used
        localStorage.setItem("auth_storage_type", rememberMe ? "local" : "session");

        // Set branding from company settings
        if (companyData?.settings?.white_label) {
            setBranding(companyData.settings.white_label);
        }

        setState({
            user,
            token,
            isAuthenticated: true,
            isLoading: false,
        });
        setCompany(companyData);

        // Start listening for real-time notifications
        listenForNotifications(user.user_id, token);
    };

    const logout = async () => {
        try {
            // Call logout endpoint
            await api.post("/auth/logout");
        } catch (error) {
            console.error("Logout error:", error);
        } finally {
            // Clear both storages regardless of API call success
            localStorage.removeItem("auth_token");
            localStorage.removeItem("user");
            localStorage.removeItem("company");
            localStorage.removeItem("auth_storage_type");
            localStorage.removeItem("sidebar_state"); // Reset sidebar to expanded on next login
            localStorage.removeItem("vite-ui-theme"); // Reset theme to system default on next login
            sessionStorage.removeItem("auth_token");
            sessionStorage.removeItem("user");
            sessionStorage.removeItem("company");
            setBranding(null);

            setState({
                user: null,
                token: null,
                isAuthenticated: false,
                isLoading: false,
            });
            setCompany(null);

            // Disconnect notifications
            disconnectEcho();
        }
    };

    const refreshUser = async () => {
        try {
            const response = await api.get<User>("/auth/me");

            if (response.success && response.data) {
                const user = response.data;

                // Save to the correct storage based on how the user logged in
                const storageType = localStorage.getItem("auth_storage_type");
                const storage = storageType === "session" ? sessionStorage : localStorage;
                storage.setItem("user", JSON.stringify(user));

                setState((prev) => ({
                    ...prev,
                    user,
                    isAuthenticated: true,
                }));
            }
        } catch (error) {
            console.error("Failed to refresh user:", error);
            logout();
        }
    };

    return (
        <AuthContext.Provider
            value={{
                ...state,
                company,
                login,
                logout,
                refreshUser,
            }}
        >
            {children}
        </AuthContext.Provider>
    );
}

// eslint-disable-next-line react-refresh/only-export-components
export function useAuth() {
    const context = useContext(AuthContext);
    if (context === undefined) {
        throw new Error("useAuth must be used within an AuthProvider");
    }
    return context;
}
