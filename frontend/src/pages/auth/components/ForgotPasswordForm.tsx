import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { ArrowLeft } from "lucide-react";
import { Spinner } from "@/components/ui/shadcn-io/spinner";
import { Link } from "react-router-dom";
import type { UseFormReturn } from 'react-hook-form';
import type { ForgotPasswordFormData } from '../hooks';

interface ForgotPasswordFormProps {
  form: UseFormReturn<ForgotPasswordFormData>;
  error: string;
  isSubmitting: boolean;
  onSubmit: (e: React.FormEvent) => void;
}

export function ForgotPasswordForm({
  form,
  error,
  isSubmitting,
  onSubmit,
}: ForgotPasswordFormProps) {
  const { register, formState: { errors } } = form;

  return (
    <div className="bg-[hsl(var(--auth-card))] rounded-2xl shadow-2xl p-6 sm:p-10 border border-border/50 backdrop-blur-md relative overflow-hidden">
      <div className="absolute top-0 left-0 right-0 h-1 bg-brand-gradient opacity-80" />

      {/* Heading */}
      <div className="text-center space-y-2 mb-10">
        <h1 className="text-3xl font-extrabold text-foreground tracking-tight">
          Forgot Password?
        </h1>
        <p className="text-sm font-medium text-muted-foreground">
          Enter your email and we'll send you a reset link
        </p>
      </div>

      <form onSubmit={onSubmit} className="space-y-6" noValidate>
        <div className="space-y-2 text-left">
          <label htmlFor="email" className="block text-sm font-semibold text-foreground">
            Email Address
          </label>
          <Input
            id="email"
            type="email"
            placeholder="your.email@company.com"
            {...register("email")}
            className={`h-11 bg-background/50 border border-border text-foreground placeholder-muted-foreground shadow-sm focus:border-primary focus:ring-2 focus:ring-ring ${errors.email ? 'border-destructive' : ''}`}
            disabled={isSubmitting}
          />
          {errors.email && (
            <p className="mt-1.5 text-xs font-medium text-destructive">{errors.email.message}</p>
          )}
        </div>

        {error && (
          <div className="rounded-lg bg-destructive/10 border border-destructive/20 p-4 animate-shake">
            <p className="text-sm font-medium text-destructive text-center">{error}</p>
          </div>
        )}

        <Button
          type="submit"
          className="w-full h-12 text-sm font-bold bg-primary hover:bg-primary/90 text-primary-foreground uppercase tracking-wider rounded-xl shadow-lg shadow-primary/20 hover:shadow-xl hover:shadow-primary/30 transition-all duration-200"
          disabled={isSubmitting}
        >
          {isSubmitting ? (
            <>
              <Spinner variant="circle" size={16} className="mr-2" />
              Sending...
            </>
          ) : (
            "Send Reset Link"
          )}
        </Button>
      </form>

      <div className="mt-8 text-center pt-6 border-t border-border/50">
        <Link to="/login" className="text-sm font-semibold text-primary hover:text-primary/80 transition-colors inline-flex items-center gap-2">
          <ArrowLeft className="h-4 w-4" />
          Back to Sign In
        </Link>
      </div>
    </div>
  );
}
