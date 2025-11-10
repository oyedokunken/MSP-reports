import { useState, type FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/contexts/AuthContext";

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
        // Form state
        email,
        password,
        error,
        isSubmitting,

        // Form handlers
        setEmail,
        setPassword,
        setError,
        handleSubmit,
        resetForm,
    };
}
