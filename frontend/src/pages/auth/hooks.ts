import { useState, useEffect, type FormEvent } from "react";
import { useNavigate, useSearchParams } from "react-router-dom";
import { useAuth } from "@/contexts/AuthContext";
import { useToast } from "@/components/ui/Toast";
import { api } from "@/lib/api";

interface UseAuthFormOptions {
    redirectTo?: string;
    onSuccess?: () => void;
    onError?: (error: Error) => void;
}

export function useAuthForm(options: UseAuthFormOptions = {}) {
    const { redirectTo = "/dashboard", onSuccess, onError } = options;

    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [error, setError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);

    const navigate = useNavigate();
    const { login } = useAuth();

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();

        // Validation
        if (!email || !password) {
            setError("Email and password are required");
            return;
        }

        setError("");
        setIsSubmitting(true);

        try {
            await login({
                email,
                password,
                auth_method: "local", // Default to local auth
            });

            // Success callback
            onSuccess?.();

            // Navigate to dashboard or specified route
            navigate(redirectTo);
        } catch (err: any) {
            const errorMessage =
                err.response?.data?.message ||
                err.message ||
                "Failed to sign in";
            setError(errorMessage);

            // Error callback
            onError?.(err);
        } finally {
            setIsSubmitting(false);
        }
    };

    const resetForm = () => {
        setEmail("");
        setPassword("");
        setError("");
        setIsSubmitting(false);
    };

    return {
        email,
        password,
        error,
        isSubmitting,
        setEmail,
        setPassword,
        setError,
        handleSubmit,
        resetForm,
    };
}

export const useSignin = () => {
    const toast = useToast();
    const [rememberMe, setRememberMe] = useState(false);
    const [showAuthOptions, setShowAuthOptions] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const authForm = useAuthForm({
        redirectTo: '/dashboard',
        onSuccess: () => {
            toast.success('Login Successful', 'Welcome back!')
        },
        onError: (err) => {
            console.error('Login failed:', err)
        }
    });

    // Lock body scroll while enterprise auth drawer is open
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
        rememberMe,
        showAuthOptions,
        showPassword,
        setRememberMe,
        setShowAuthOptions,
        setShowPassword
    };
}

export const useForgotPassword = () => {
    const [email, setEmail] = useState("");
    const [error, setError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [success, setSuccess] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError("");
        setIsSubmitting(true);

        try {
            await api.post("/auth/password/request", { email });
            setSuccess(true);
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || "Failed to send reset email";
            setError(errorMessage);
        } finally {
            setIsSubmitting(false);
        }
    };

    return {
        email,
        error,
        isSubmitting,
        success,
        setEmail,
        handleSubmit
    };
}

export const useResetPassword = () => {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const token = searchParams.get("token");
    const email = searchParams.get("email");

    const [password, setPassword] = useState("");
    const [confirmPassword, setConfirmPassword] = useState("");
    const [showPassword, setShowPassword] = useState(false);
    const [showConfirmPassword, setShowConfirmPassword] = useState(false);
    const [error, setError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [success, setSuccess] = useState(false);

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setError("");

        if (password.length < 8) {
            setError("Password must be at least 8 characters");
            return;
        }

        if (password !== confirmPassword) {
            setError("Passwords do not match");
            return;
        }

        if (!token || !email) {
            setError("Invalid reset link");
            return;
        }

        setIsSubmitting(true);

        try {
            await api.post("/auth/password/reset", {
                token,
                email,
                password,
                password_confirmation: confirmPassword,
            });
            setSuccess(true);
            setTimeout(() => {
                navigate("/sign-in");
            }, 3000);
        } catch (err: any) {
            const errorMessage = err.response?.data?.message || err.message || "Failed to reset password";
            setError(errorMessage);
        } finally {
            setIsSubmitting(false);
        }
    };

    return {
        password,
        confirmPassword,
        showPassword,
        showConfirmPassword,
        error,
        isSubmitting,
        success,
        setPassword,
        setConfirmPassword,
        setShowPassword,
        setShowConfirmPassword,
        handleSubmit
    };
}
