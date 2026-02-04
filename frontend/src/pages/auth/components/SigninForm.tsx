import { Eye, EyeOff } from 'lucide-react';
import { Spinner } from "@/components/ui/shadcn-io/spinner";
import { Link } from "react-router-dom";
import type { UseFormReturn } from 'react-hook-form';
import type { LoginFormData } from '../hooks';

interface SigninFormProps {
  form: UseFormReturn<LoginFormData>;
  error: string;
  isSubmitting: boolean;
  showPassword: boolean;
  onShowPasswordToggle: () => void;
  onSubmit: (e: React.FormEvent) => void;
}

export function SigninForm({
  form,
  error,
  isSubmitting,
  showPassword,
  onShowPasswordToggle,
  onSubmit,
}: SigninFormProps) {
  const { register, formState: { errors } } = form;

  return (
    <form className="space-y-6" onSubmit={onSubmit} noValidate>
      <div>
        <label htmlFor="email" className="block text-sm text-left font-medium text-gray-900 mb-2">
          Email address <span className="text-red-500">*</span>
        </label>
        <input
          id="email"
          type="email"
          autoComplete="email"
          disabled={isSubmitting}
          aria-invalid={!!errors.email}
          aria-describedby={errors.email ? "email-error" : undefined}
          {...register("email")}
          className={`appearance-none relative block w-full px-4 py-3 border placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out ${errors.email
            ? 'border-red-500 focus:ring-red-500'
            : 'border-gray-300'
            }`}
          placeholder="youremail@example.com"
        />
        {errors.email && (
          <p id="email-error" className="mt-1 text-sm text-red-600">{errors.email.message}</p>
        )}
      </div>

      <div>
        <label htmlFor="password" className="block text-sm text-left font-medium text-gray-900 mb-2">
          Password <span className="text-red-500">*</span>
        </label>
        <div className="relative">
          <input
            id="password"
            type={showPassword ? "text" : "password"}
            autoComplete="current-password"
            disabled={isSubmitting}
            aria-invalid={!!errors.password}
            aria-describedby={errors.password ? "password-error" : undefined}
            {...register("password")}
            className={`appearance-none relative block w-full px-4 py-3 pr-12 border placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out ${errors.password
              ? 'border-red-500 focus:ring-red-500'
              : 'border-gray-300'
              }`}
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
        {errors.password && (
          <p id="password-error" className="mt-1 text-sm text-red-600">{errors.password.message}</p>
        )}
      </div>

      <div className="flex items-center justify-between">
        <div className="flex items-center">
          <input
            id="remember-me"
            type="checkbox"
            {...register("rememberMe")}
            className="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded cursor-pointer"
          />
          <label htmlFor="remember-me" className="ml-2 block text-sm text-gray-700 cursor-pointer">
            Remember me
          </label>
        </div>

        <div className="text-sm">
          <Link to="/forgot-password" className="font-semibold text-blue-600 hover:text-blue-800 transition duration-150">
            Forgot password?
          </Link>
        </div>
      </div>

      {error && (
        <div className="rounded-md bg-red-50 border border-red-200 p-3">
          <p className="text-sm text-red-800 text-center">{error}</p>
        </div>
      )}

      <div>
        <button
          type="submit"
          disabled={isSubmitting}
          className="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out shadow-md hover:shadow-lg disabled:opacity-50 disabled:cursor-not-allowed"
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
