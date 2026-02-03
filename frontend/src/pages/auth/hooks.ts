import { z } from "zod";
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { useState, useEffect } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { logger } from '@/lib/logger';
import { useAuth } from "@/contexts/AuthContext";
import { useToast } from "@/components/ui/Toast";
import { api } from "@/lib/api";

export const loginSchema = z.object({
    email: z.string().min(1, "Email is required").email("Invalid email address"),
    password: z.string().min(6, "Password must be at least 6 characters"),
    rememberMe: z.boolean(),
});

export type LoginFormData = z.infer<typeof loginSchema>;

export interface AuthConfig {
    local: boolean;
    oauth: {
        enabled: boolean;
        provider: string;
    };
    saml: {
        enabled: boolean;
    };
    ldap: {
        enabled: boolean;
    };
}

export function useAuthConfig() {
    const [config, setConfig] = useState<AuthConfig | null>(null);
    const [isLoading, setIsLoading] = useState(true);

    useEffect(() => {
        const fetchConfig = async () => {
            try {
                const response = await api.get<{ status: string; data: AuthConfig }>('/auth/config');
                setConfig(response.data);
            } catch (err) {
                logger.error('Failed to fetch auth config:', err);
                setConfig({
                    local: true,
                    oauth: { enabled: false, provider: 'microsoft' },
                    saml: { enabled: false },
                    ldap: { enabled: false },
                });
            } finally {
                setIsLoading(false);
            }
        };

        fetchConfig();
    }, []);

    return { config, isLoading };
}

interface UseAuthFormOptions {
    redirectTo?: string;
    onSuccess?: () => void;
    onError?: (error: Error) => void;
}

export function useAuthForm(options: UseAuthFormOptions = {}) {
    const { redirectTo = "/dashboard", onSuccess, onError } = options;
    const [authError, setAuthError] = useState("");

    const navigate = useNavigate();
    const { login } = useAuth();

    const form = useForm<LoginFormData>({
        resolver: zodResolver(loginSchema),
        defaultValues: {
            email: "",
            password: "",
            rememberMe: false,
        },
    });

    const handleFormSubmit = async (data: LoginFormData) => {
        setAuthError("");

        try {
            await login({
                email: data.email,
                password: data.password,
                auth_method: "local",
                rememberMe: data.rememberMe,
            });

            onSuccess?.();
            navigate(redirectTo);
        } catch (err: unknown) {
            const error = err as { message?: string };
            const errorMessage = error.message || "Failed to sign in";
            setAuthError(errorMessage);
            onError?.(error as Error);
        }
    };

    return {
        form,
        error: authError,
        isSubmitting: form.formState.isSubmitting,
        handleSubmit: form.handleSubmit(handleFormSubmit),
    };
}

export const useSignin = () => {
    const toast = useToast();
    const [showAuthOptions, setShowAuthOptions] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const authForm = useAuthForm({
        redirectTo: '/platform/dashboard',
        onSuccess: () => {
            toast.success('Login Successful', 'Welcome back!')
        },
        onError: (err) => {
            console.error('Login failed:', err)
        }
    });

    useEffect(() => {
        if (!showAuthOptions) return;
        const prevOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = prevOverflow || '';
        };
    }, [showAuthOptions]);

    return {
        ...authForm,
        showAuthOptions,
        showPassword,
        setShowAuthOptions,
        setShowPassword
    };
}

export const forgotPasswordSchema = z.object({
    email: z.string().min(1, "Email is required").email("Invalid email address"),
});

export type ForgotPasswordFormData = z.infer<typeof forgotPasswordSchema>;

export const useForgotPassword = () => {
    const [authError, setAuthError] = useState("");
    const [success, setSuccess] = useState(false);

    const form = useForm<ForgotPasswordFormData>({
        resolver: zodResolver(forgotPasswordSchema),
        defaultValues: { email: "" },
    });

    const handleFormSubmit = async (data: ForgotPasswordFormData) => {
        setAuthError("");
        try {
            await api.post("/auth/password/request", { email: data.email });
            setSuccess(true);
        } catch (err: unknown) {
            const error = err as { message?: string };
            setAuthError(error.message || "Failed to send reset email");
        }
    };

    return {
        form,
        email: form.watch("email"),
        error: authError,
        isSubmitting: form.formState.isSubmitting,
        success,
        handleSubmit: form.handleSubmit(handleFormSubmit)
    };
}

export const resetPasswordSchema = z.object({
    password: z.string().min(8, "Password must be at least 8 characters"),
    confirmPassword: z.string().min(8, "Password must be at least 8 characters"),
}).refine((data) => data.password === data.confirmPassword, {
    message: "Passwords do not match",
    path: ["confirmPassword"],
});

export type ResetPasswordFormData = z.infer<typeof resetPasswordSchema>;

export const useResetPassword = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const token = searchParams.get("token");
    const email = searchParams.get("email");

    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [authError, setAuthError] = useState("");
    const [success, setSuccess] = useState(false);

    const form = useForm<ResetPasswordFormData>({
        resolver: zodResolver(resetPasswordSchema),
        defaultValues: {
            password: "",
            confirmPassword: "",
        },
    });

    const handleFormSubmit = async (data: ResetPasswordFormData) => {
        setAuthError("");

        if (!token || !email) {
            setAuthError("Invalid reset link");
            return;
        }

        try {
            await api.post("/auth/password/reset", {
                token,
                email,
                password: data.password,
                password_confirmation: data.confirmPassword,
            });
            setSuccess(true);
            setTimeout(() => {
                navigate("/sign-in");
            }, 3000);
        } catch (err: unknown) {
            const error = err as { message?: string };
            setAuthError(error.message || "Failed to reset password");
        }
    };

    return {
        form,
        showPassword,
        showConfirmPassword,
        error: authError,
        isSubmitting: form.formState.isSubmitting,
        success,
        setShowPassword,
        setShowConfirmPassword,
        handleSubmit: form.handleSubmit(handleFormSubmit)
    };
}
