import {
    createContext,
    useContext,
    useState,
    type ReactNode,
    useCallback,
} from "react";
import { CheckCircle2, XCircle, AlertCircle, Info, X } from "lucide-react";

/**
 * Toast Notification System
 * Provides user feedback for actions
 */

type ToastType = "success" | "error" | "warning" | "info";

interface Toast {
    id: string;
    type: ToastType;
    title: string;
    message?: string;
    duration?: number;
}

interface ToastContextType {
    showToast: (
        type: ToastType,
        title: string,
        message?: string,
        duration?: number
    ) => void;
    success: (title: string, message?: string) => void;
    error: (title: string, message?: string) => void;
    warning: (title: string, message?: string) => void;
    info: (title: string, message?: string) => void;
}

const ToastContext = createContext<ToastContextType | undefined>(undefined);

export function ToastProvider({ children }: { children: ReactNode }) {
    const [toasts, setToasts] = useState<Toast[]>([]);

    const removeToast = useCallback((id: string) => {
        setToasts((prev) => prev.filter((toast) => toast.id !== id));
    }, []);

    const showToast = useCallback(
        (type: ToastType, title: string, message?: string, duration = 5000) => {
            const id = Math.random().toString(36).substring(7);
            const toast: Toast = { id, type, title, message, duration };

            setToasts((prev) => [...prev, toast]);

            if (duration > 0) {
                setTimeout(() => removeToast(id), duration);
            }
        },
        [removeToast]
    );

    const success = useCallback(
        (title: string, message?: string) => {
            showToast("success", title, message);
        },
        [showToast]
    );

    const error = useCallback(
        (title: string, message?: string) => {
            showToast("error", title, message);
        },
        [showToast]
    );

    const warning = useCallback(
        (title: string, message?: string) => {
            showToast("warning", title, message);
        },
        [showToast]
    );

    const info = useCallback(
        (title: string, message?: string) => {
            showToast("info", title, message);
        },
        [showToast]
    );

    return (
        <ToastContext.Provider
            value={{ showToast, success, error, warning, info }}
        >
            {children}
            <ToastContainer toasts={toasts} onRemove={removeToast} />
        </ToastContext.Provider>
    );
}

export function useToast() {
    const context = useContext(ToastContext);
    if (!context) {
        throw new Error("useToast must be used within ToastProvider");
    }
    return context;
}

function ToastContainer({
    toasts,
    onRemove,
}: {
    toasts: Toast[];
    onRemove: (id: string) => void;
}) {
    return (
        <div className="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm">
            {toasts.map((toast) => (
                <ToastItem key={toast.id} toast={toast} onRemove={onRemove} />
            ))}
        </div>
    );
}

function ToastItem({
    toast,
    onRemove,
}: {
    toast: Toast;
    onRemove: (id: string) => void;
}) {
    const icons = {
        success: CheckCircle2,
        error: XCircle,
        warning: AlertCircle,
        info: Info,
    };

    const colors = {
        success:
            "bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200",
        error: "bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200",
        warning:
            "bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-800 dark:text-yellow-200",
        info: "bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-200",
    };

    const Icon = icons[toast.type];

    return (
        <div
            className={`${
                colors[toast.type]
            } rounded-lg border p-4 shadow-lg animate-in slide-in-from-right-full duration-300`}
        >
            <div className="flex items-start gap-3">
                <Icon className="h-5 w-5 flex-shrink-0 mt-0.5" />
                <div className="flex-1 min-w-0">
                    <p className="font-semibold text-sm">{toast.title}</p>
                    {toast.message && (
                        <p className="mt-1 text-xs opacity-90">
                            {toast.message}
                        </p>
                    )}
                </div>
                <button
                    onClick={() => onRemove(toast.id)}
                    className="flex-shrink-0 rounded-md p-1 hover:bg-black/10 dark:hover:bg-white/10 transition-colors"
                >
                    <X className="h-4 w-4" />
                </button>
            </div>
        </div>
    );
}
