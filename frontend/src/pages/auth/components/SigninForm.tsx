import { Eye, EyeOff, Mail, Lock } from 'lucide-react';
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
//Everyday I wake up to write a code that I pray it works and deluver the results I so esire
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
        <label htmlFor="email" className="block text-sm text-left font-medium text-foreground mb-2">
          Email <span className="text-destructive">*</span>
        </label>
        <div className="relative group">
          <div className="absolute left-0 top-1/2 -translate-y-1/2 flex items-center h-6 border-r border-border/50 px-3 transition-colors group-focus-within:border-primary/50 z-10 pointer-events-none">
            <Mail className="h-4 w-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
          </div>
          <input
            id="email"
            type="email"
            autoComplete="email"
            disabled={isSubmitting}
            aria-invalid={!!errors.email}
            aria-describedby={errors.email ? "email-error" : undefined}
            {...register("email")}
            className={`appearance-none relative block w-full pl-12 pr-4 py-3 border bg-background/50 placeholder-muted-foreground text-foreground rounded-lg focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary hover:border-primary/30 transition-all duration-200 ease-in-out ${errors.email
              ? 'border-destructive focus:ring-destructive/20'
              : 'border-border'
              }`}
            placeholder="Enter your email address"
          />
        </div>
        {errors.email && (
          <p id="email-error" className="mt-1 text-sm text-destructive">{errors.email.message}</p>
        )}
      </div>

      <div>
        <label htmlFor="password" className="block text-sm text-left font-medium text-foreground mb-2">
          Password <span className="text-destructive">*</span>
        </label>
        <div className="relative group">
          <div className="absolute left-0 top-1/2 -translate-y-1/2 flex items-center h-6 border-r border-border/50 px-3 transition-colors group-focus-within:border-primary/50 z-10 pointer-events-none">
            <Lock className="h-4 w-4 text-muted-foreground transition-colors group-focus-within:text-primary" />
          </div>
          <input
            id="password"
            type={showPassword ? "text" : "password"}
            autoComplete="current-password"
            disabled={isSubmitting}
            aria-invalid={!!errors.password}
            aria-describedby={errors.password ? "password-error" : undefined}
            {...register("password")}
            className={`appearance-none relative block w-full pl-12 pr-12 py-3 border bg-background/50 placeholder-muted-foreground text-foreground rounded-lg focus:outline-none focus:ring-4 focus:ring-primary/10 focus:border-primary hover:border-primary/30 transition-all duration-200 ease-in-out ${errors.password
              ? 'border-destructive focus:ring-destructive/20'
              : 'border-border'
              }`}
            placeholder="·············"
          />
          <button
            type="button"
            onClick={onShowPasswordToggle}
            className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors z-10"
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
          <p id="password-error" className="mt-1 text-sm text-destructive">{errors.password.message}</p>
        )}
      </div>

      <div className="flex items-center justify-between">
        <div className="flex items-center">
          <input
            id="remember-me"
            type="checkbox"
            {...register("rememberMe")}
            className="h-4 w-4 text-primary focus:ring-ring border-border rounded cursor-pointer bg-background"
          />
          <label htmlFor="remember-me" className="ml-2 block text-sm text-muted-foreground transition-colors hover:text-foreground cursor-pointer">
            Remember me
          </label>
        </div>

        <div className="text-sm">
          <Link to="/forgot-password" title="Click to reset password" className="font-semibold text-primary hover:text-primary/80 transition duration-150">
            Forgot password?
          </Link>
        </div>
      </div>

      {error && (
        <div className="rounded-lg bg-destructive/10 border border-destructive/20 p-4">
          <p className="text-sm font-medium text-destructive text-center">{error}</p>
        </div>
      )}

      <div>
        <button
          type="submit"
          disabled={isSubmitting}
          className="group relative w-full flex justify-center py-3.5 px-4 border border-transparent text-sm font-bold rounded-xl text-primary-foreground bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ring transition duration-200 ease-in-out shadow-lg shadow-primary/20 hover:shadow-xl hover:shadow-primary/30 disabled:opacity-50 disabled:cursor-not-allowed uppercase tracking-wider"
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
