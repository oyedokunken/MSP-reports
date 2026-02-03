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
    <div className="bg-white rounded-xl shadow-xl p-8 border border-gray-100">
      {/* Heading */}
      <div className="text-center space-y-2 mb-10">
        <h1 className="text-3xl font-bold text-gray-900">
          Forgot Password?
        </h1>
        <p className="text-base text-gray-600">
          Enter your email and we'll send you a reset link
        </p>
      </div>

      <form onSubmit={onSubmit} className="space-y-5" noValidate>
        <div className="space-y-2">
          <label htmlFor="email" className="block text-sm font-medium text-gray-900">
            Email Address
          </label>
          <Input
            id="email"
            type="email"
            placeholder="your.email@company.com"
            {...register("email")}
            className={`h-11 bg-white border border-gray-300 text-gray-900 placeholder:text-gray-400 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 ${errors.email ? 'border-red-500' : ''}`}
            disabled={isSubmitting}
          />
          {errors.email && (
            <p className="mt-1 text-sm text-red-600">{errors.email.message}</p>
          )}
        </div>

        {error && (
          <div className="rounded-md bg-red-50 border border-red-200 p-3">
            <p className="text-sm text-red-800 text-center">{error}</p>
          </div>
        )}

        <Button
          type="submit"
          className="w-full h-12 text-base font-semibold bg-blue-600 hover:bg-blue-700 text-white"
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

      <div className="mt-6 text-center">
        <Link to="/login" className="text-sm text-blue-600 hover:underline inline-flex items-center gap-1">
          <ArrowLeft className="h-4 w-4" />
          Back to Sign In
        </Link>
      </div>
    </div>
  );
}
