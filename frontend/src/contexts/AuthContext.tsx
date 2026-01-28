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
import { STORAGE_KEYS } from "@/lib/constants";
import { logger } from "@/lib/logger";
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

    useEffect(() => {
        const initializeAuth = async () => {
            // Determine which browser storage to use based on the user's "Remember Me" preference.
            const storageType = localStorage.getItem("auth_storage_type");
            const storage = storageType === "session" ? sessionStorage : localStorage;

            let token = storage.getItem(STORAGE_KEYS.AUTH_TOKEN);
            let userStr = storage.getItem(STORAGE_KEYS.USER);
            let companyStr = storage.getItem(STORAGE_KEYS.COMPANY);

            if (!token) {
                const fallbackStorage = storageType === "session" ? localStorage : sessionStorage;
                token = fallbackStorage.getItem(STORAGE_KEYS.AUTH_TOKEN);
                userStr = fallbackStorage.getItem(STORAGE_KEYS.USER);
                companyStr = fallbackStorage.getItem(STORAGE_KEYS.COMPANY);
            }

            if (token && userStr) {
                try {
                    if (companyStr) {
                        const companyData: Company = JSON.parse(companyStr);
                        setCompany(companyData);

                        if (companyData?.settings?.white_label) {
                            setBranding(companyData.settings.white_label);
                        }
                    }

                    try {
                        // Validate existing token by fetching the latest user data from the server.
                        const response = await api.get<{ data: User }>("/auth/me");
                        const user = response.data;
                        setState({
                            user,
                            token,
                            isAuthenticated: true,
                            isLoading: false,
                        });

                        const storageType = localStorage.getItem("auth_storage_type");
                        const activeStorage = storageType === "session" ? sessionStorage : localStorage;
                        activeStorage.setItem(STORAGE_KEYS.USER, JSON.stringify(user));

                        listenForNotifications(user.user_id, token);
                    } catch {
                        logger.warn("Token validation failed - session expired");
                        localStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
                        localStorage.removeItem(STORAGE_KEYS.USER);
                        localStorage.removeItem(STORAGE_KEYS.COMPANY);
                        localStorage.removeItem("auth_storage_type");
                        sessionStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
                        sessionStorage.removeItem(STORAGE_KEYS.USER);
                        sessionStorage.removeItem(STORAGE_KEYS.COMPANY);
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
                    logger.error("Failed to parse stored user", error);
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

        const response = await api.post<{ data: LoginResponse }>(
            "/auth/login",
            apiCredentials
        );

        const user = response.data.user;
        const token = response.data.token;
        const companyData = response.data.company;

        const storage = rememberMe ? localStorage : sessionStorage;

        localStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
        localStorage.removeItem(STORAGE_KEYS.USER);
        localStorage.removeItem(STORAGE_KEYS.COMPANY);
        sessionStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
        sessionStorage.removeItem(STORAGE_KEYS.USER);
        sessionStorage.removeItem(STORAGE_KEYS.COMPANY);

        storage.setItem(STORAGE_KEYS.AUTH_TOKEN, token);
        storage.setItem(STORAGE_KEYS.USER, JSON.stringify(user));
        storage.setItem(STORAGE_KEYS.COMPANY, JSON.stringify(companyData));

        localStorage.setItem("auth_storage_type", rememberMe ? "local" : "session");

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

        listenForNotifications(user.user_id, token);
    };

    const logout = async () => {
        try {
            await api.post("/auth/logout");
        } catch (error) {
            logger.error("Logout error", error);
        } finally {
            localStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
            localStorage.removeItem(STORAGE_KEYS.USER);
            localStorage.removeItem(STORAGE_KEYS.COMPANY);
            localStorage.removeItem("auth_storage_type");
            localStorage.removeItem("sidebar_state"); // Reset sidebar to expanded on next login
            localStorage.removeItem("vite-ui-theme"); // Reset theme to system default on next login
            sessionStorage.removeItem(STORAGE_KEYS.AUTH_TOKEN);
            sessionStorage.removeItem(STORAGE_KEYS.USER);
            sessionStorage.removeItem(STORAGE_KEYS.COMPANY);
            setBranding(null);

            setState({
                user: null,
                token: null,
                isAuthenticated: false,
                isLoading: false,
            });
            setCompany(null);

            disconnectEcho();
        }
    };

    const refreshUser = async () => {
        try {
            const response = await api.get<{ data: User }>("/auth/me");
            const user = response.data;

            const storageType = localStorage.getItem("auth_storage_type");
            const storage = storageType === "session" ? sessionStorage : localStorage;
            storage.setItem(STORAGE_KEYS.USER, JSON.stringify(user));

            setState((prev) => ({
                ...prev,
                user,
                isAuthenticated: true,
            }));
        } catch (error) {
            logger.error("Failed to refresh user", error);
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
