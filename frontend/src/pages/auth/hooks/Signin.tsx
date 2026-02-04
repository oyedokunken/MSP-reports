import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Eye, EyeOff } from "lucide-react";
import { Spinner } from "@/components/ui/shadcn-io/spinner";
import { Link } from "react-router-dom";
import { useAuthForm } from "../hooks";

const Signin = () => {
    const [showPassword, setShowPassword] = useState(false);

    const {
        form,
        error,
        isSubmitting,
        handleSubmit,
    } = useAuthForm({
        redirectTo: "/dashboard",
        onSuccess: () => {
        },
        onError: (err) => {
            console.error("Login failed:", err);
        },
    });

    return (
        <div className="flex min-h-screen w-full items-center justify-center bg-gray-50 p-4">
            <div className="w-full max-w-md">
                <div className="bg-white rounded-lg border border-gray-200 shadow-xl p-8">
                    <div className="flex justify-center mb-10">
                        <img
                            src="/logo.png"
                            alt="Logo"
                            className="h-8 w-auto opacity-90"
                        />
                    </div>

                    <div className="text-center space-y-2 mb-10">
                        <h1 className="text-3xl font-bold text-gray-900">
                            Welcome Back
                        </h1>
                        <p className="text-base text-gray-600">
                            Sign in to continue to Reporting Platform
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div className="space-y-2">
                            <label
                                htmlFor="email"
                                className="block text-sm font-medium text-gray-700"
                            >
                                Email Address
                            </label>
                            <Input
                                id="email"
                                type="email"
                                placeholder="your.email@company.com"
                                {...form.register("email")}
                                className="h-11 bg-white border border-gray-300 text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                required
                                disabled={isSubmitting}
                            />
                        </div>

                        <div className="space-y-2">
                            <label
                                htmlFor="password"
                                className="block text-sm font-medium text-gray-700"
                            >
                                Password
                            </label>
                            <div className="relative">
                                <Input
                                    id="password"
                                    type={showPassword ? "text" : "password"}
                                    placeholder="Enter your password"
                                    {...form.register("password")}
                                    className="h-11 pr-10 bg-white border border-gray-300 text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500"
                                    required
                                    disabled={isSubmitting}
                                />
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowPassword(!showPassword)
                                    }
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                                    tabIndex={-1}
                                >
                                    {showPassword ? (
                                        <EyeOff className="h-5 w-5" />
                                    ) : (
                                        <Eye className="h-5 w-5" />
                                    )}
                                </button>
                            </div>
                            <div className="text-right pt-1">
                                <Link
                                    to="/forgot-password"
                                    className="text-sm text-blue-600 hover:text-blue-700 hover:underline"
                                >
                                    Forgot password?
                                </Link>
                            </div>
                        </div>

                        {error && (
                            <div className="rounded-md bg-red-50 border border-red-200 p-3">
                                <p className="text-sm text-red-800 text-center">
                                    {error}
                                </p>
                            </div>
                        )}

                        <Button
                            type="submit"
                            className="w-full h-12 text-base font-semibold bg-blue-600 hover:bg-blue-700 text-white shadow-md hover:shadow-lg transition-all"
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? (
                                <>
                                    <Spinner
                                        variant="circle"
                                        size={16}
                                        className="mr-2"
                                    />
                                    Signing in...
                                </>
                            ) : (
                                "Sign In"
                            )}
                        </Button>

                        <div className="text-xs text-gray-500 text-center pt-2">
                            By continuing, you agree to our{" "}
                            <Link
                                to="/privacy-policy"
                                className="text-blue-600 hover:underline"
                            >
                                Privacy Policy
                            </Link>
                        </div>
                    </form>

                    <div className="mt-6 space-y-4">
                        <div className="text-center text-sm text-gray-600">
                            Don't have an account?{" "}
                            <Link
                                to="/contact-admin"
                                className="text-blue-600 hover:text-blue-700 hover:underline font-medium"
                            >
                                Contact your administrator
                            </Link>
                        </div>

                        <div className="relative">
                            <div className="absolute inset-0 flex items-center">
                                <div className="w-full border-t border-gray-200"></div>
                            </div>
                            <div className="relative flex justify-center text-xs">
                                <span className="px-3 bg-white text-gray-500 uppercase tracking-wide">
                                    Or
                                </span>
                            </div>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            className="w-full h-11 text-sm font-medium border border-gray-300 hover:bg-gray-50 shadow-sm transition-colors"
                            disabled
                        >
                            <svg
                                className="w-5 h-5 mr-2"
                                viewBox="0 0 21 21"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <rect
                                    x="0.5"
                                    y="0.5"
                                    width="9"
                                    height="9"
                                    fill="#F25022"
                                />
                                <rect
                                    x="11.5"
                                    y="0.5"
                                    width="9"
                                    height="9"
                                    fill="#7FBA00"
                                />
                                <rect
                                    x="0.5"
                                    y="11.5"
                                    width="9"
                                    height="9"
                                    fill="#00A4EF"
                                />
                                <rect
                                    x="11.5"
                                    y="11.5"
                                    width="9"
                                    height="9"
                                    fill="#FFB900"
                                />
                            </svg>
                            Sign in with Microsoft
                        </Button>
                    </div>
                </div>

                <div className="mt-6 text-center">
                    <p className="text-xs text-gray-500">
                        Secured by{" "}
                        <span className="font-semibold text-gray-700">
                            {import.meta.env.VITE_COMPANY_NAME ||
                                "Athena Networks"}
                        </span>
                    </p>
                </div>
            </div>
        </div>
    );
};

export default Signin;
