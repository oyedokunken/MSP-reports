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

interface AuthContextType extends AuthState {
    company: Company | null;
    login: (credentials: LoginCredentials) => Promise<void>;
    logout: () => Promise<void>;
    refreshUser: () => Promise<void>;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
    const { setBranding } = useBranding();
    const [state, setState] = useState<AuthState>({
        user: null,
        token: null,
        isAuthenticated: false,
        isLoading: true,
    });
    const [company, setCompany] = useState<Company | null>(null);

    // Initialize auth state from localStorage
    useEffect(() => {
        const initializeAuth = async () => {
            const token = localStorage.getItem("auth_token");
            const userStr = localStorage.getItem("user");

            if (token && userStr) {
                try {
                    const user: User = JSON.parse(userStr);
                    setState({
                        user,
                        token,
                        isAuthenticated: true,
                        isLoading: false,
                    });

                    // Optionally verify token with backend (non-blocking)
                    refreshUser().catch(() => {
                        // Silently fail - user can still use the app with cached data
                        console.warn("Failed to refresh user data");
                    });
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
        try {
            const response = await api.post<LoginResponse>(
                "/auth/login",
                credentials
            );

            if (!response.data) {
                throw new Error("Login failed");
            }

            const user = response.data.user;
            const token = response.data.token;
            const companyData = response.data.company;

            // Store in localStorage
            localStorage.setItem("auth_token", token);
            localStorage.setItem("user", JSON.stringify(user));
            localStorage.setItem("company", JSON.stringify(companyData));

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
        } catch (error) {
            throw error;
        }
    };

    const logout = async () => {
        try {
            // Call logout endpoint
            await api.post("/auth/logout");
        } catch (error) {
            console.error("Logout error:", error);
        } finally {
            // Clear local state regardless of API call success
            localStorage.removeItem("auth_token");
            localStorage.removeItem("user");
            localStorage.removeItem("company");
            setBranding(null);

            setState({
                user: null,
                token: null,
                isAuthenticated: false,
                isLoading: false,
            });
            setCompany(null);
        }
    };

    const refreshUser = async () => {
        try {
            const response = await api.get<User>("/auth/me");

            if (response.success && response.data) {
                const user = response.data;
                localStorage.setItem("user", JSON.stringify(user));

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

export function useAuth() {
    const context = useContext(AuthContext);
    if (context === undefined) {
        throw new Error("useAuth must be used within an AuthProvider");
    }
    return context;
}
