import { Eye, EyeOff } from 'lucide-react';
import { Spinner } from "@/components/ui/shadcn-io/spinner";
import { Link } from "react-router-dom";

interface SigninFormProps {
  email: string;
  password: string;
  error: string;
  isSubmitting: boolean;
  rememberMe: boolean;
  showPassword: boolean;
  onEmailChange: (email: string) => void;
  onPasswordChange: (password: string) => void;
  onRememberMeChange: (checked: boolean) => void;
  onShowPasswordToggle: () => void;
  onSubmit: (e: React.FormEvent) => void;
}

export function SigninForm({
  email,
  password,
  error,
  isSubmitting,
  rememberMe,
  showPassword,
  onEmailChange,
  onPasswordChange,
  onRememberMeChange,
  onShowPasswordToggle,
  onSubmit,
}: SigninFormProps) {
  return (
    <form className="space-y-6" onSubmit={onSubmit}>
      {/* Email Field */}
      <div>
        <label htmlFor="email" className="block text-sm text-left font-medium text-gray-700 mb-2 font-athletics">
          Email address
        </label>
        <input
          id="email"
          name="email"
          type="email"
          autoComplete="email"
          required
          value={email}
          onChange={(e) => onEmailChange(e.target.value)}
          disabled={isSubmitting}
          className="appearance-none relative block w-full px-4 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150 ease-in-out"
          placeholder="admin@example.com"
        />
      </div>

      {/* Password Field */}
      <div>
        <label htmlFor="password" className="block text-sm text-left font-medium text-gray-700 mb-2 font-athletics">
          Password
        </label>
        <div className="relative">
          <input
            id="password"
            name="password"
            type={showPassword ? "text" : "password"}
            autoComplete="current-password"
            required
            value={password}
            onChange={(e) => onPasswordChange(e.target.value)}
            disabled={isSubmitting}
            className="appearance-none relative block w-full px-4 py-3 pr-12 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150 ease-in-out"
            placeholder="*******"
          />
          <button
            type="button"
            onClick={onShowPasswordToggle}
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
      </div>

      {/* Remember Me and Forgot Password */}
      <div className="flex items-center justify-between">
        <div className="flex items-center">
          <input
            id="remember-me"
            name="remember-me"
            type="checkbox"
            checked={rememberMe}
            onChange={(e) => onRememberMeChange(e.target.checked)}
            className="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded cursor-pointer"
          />
          <label htmlFor="remember-me" className="ml-2 block text-sm text-gray-700 cursor-pointer font-athletics">
            Remember me
          </label>
        </div>

        <div className="text-sm">
          <Link to="/forgot-password" className="font-medium text-indigo-600 hover:text-indigo-500 transition duration-150 font-athletics">
            Forgot password?
          </Link>
        </div>
      </div>

      {/* Error Message */}
      {error && (
        <div className="rounded-md bg-red-50 border border-red-200 p-3">
          <p className="text-sm text-red-800 text-center">{error}</p>
        </div>
      )}

      {/* Sign In Button */}
      <div>
        <button
          type="submit"
          disabled={isSubmitting}
          className="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition duration-150 ease-in-out shadow-md hover:shadow-lg font-athletics disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {isSubmitting ? (
            <>
              <Spinner variant="circle" size={16} className="mr-2" />
              Signing in...
            </>
          ) : (
            "Sign in"
          )}
        </button>
      </div>
    </form>
  );
}
