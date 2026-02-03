import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Lock, Eye, EyeOff, CheckCircle2 } from "lucide-react";
import { Spinner } from "@/components/ui/spinner";
import { useResetPassword } from "../hooks";

const ResetPassword = () => {
  const {
    form,
    showPassword,
    showConfirmPassword,
    error,
    isSubmitting,
    success,
    setShowPassword,
    setShowConfirmPassword,
    handleSubmit
  } = useResetPassword();

  const { register, formState: { errors }, watch } = form;
  const passwordValue = watch("password");
  const confirmPasswordValue = watch("confirmPassword");

  return (
    <div className="min-h-screen flex items-center justify-center relative bg-blue-950 overflow-hidden">
      {/* Background Decoration */}
      <div className="absolute inset-0 z-0 pointer-events-none">
        <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
        <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
      </div>

      <div className="absolute top-6 sm:top-8 left-0 right-0 sm:left-8 sm:right-auto z-20 flex justify-center sm:block">
        <div className="text-center">
          <img src="/logo.png" alt="Logo" className="h-12 w-auto brightness-0 invert" />
        </div>
      </div>

      <div className="relative z-10 w-full max-w-[400px] px-4 py-12">

        {success ? (
          <Card className="border-border bg-white shadow-2xl">
            <CardContent className="pt-8">
              <div className="flex flex-col items-center justify-center py-8 text-center">
                <div className="mb-4 rounded-full bg-green-500/10 p-3">
                  <CheckCircle2 className="h-12 w-12 text-green-500" />
                </div>
                <h2 className="text-2xl font-bold text-green-600 mb-2">Password Reset!</h2>
                <p className="text-sm text-gray-600 mb-4">
                  Your password has been reset successfully.
                </p>
                <p className="text-xs text-gray-400">
                  Redirecting to sign in...
                </p>
              </div>
            </CardContent>
          </Card>
        ) : (
          <Card className="border-border bg-white shadow-2xl">
            <CardHeader className="space-y-0.5 text-center pt-8">
              <CardTitle className="text-lg font-bold text-gray-900">
                Reset Your Password
              </CardTitle>
              <CardDescription className="text-sm text-gray-600">
                Enter your new password below
              </CardDescription>
            </CardHeader>

            <CardContent className="space-y-3 pb-8">
              <form onSubmit={handleSubmit} className="space-y-5">
                <div className="space-y-1">
                  <Label htmlFor="password" className="text-xs text-gray-700">New Password</Label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <Input
                      id="password"
                      type={showPassword ? "text" : "password"}
                      placeholder="Enter new password (min 8 characters)"
                      {...register("password")}
                      className={`pl-9 pr-10 border-gray-300 focus:ring-blue-500 ${errors.password ? 'border-red-500' : ''}`}
                      disabled={isSubmitting}
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                      tabIndex={-1}
                    >
                      {showPassword ? (
                        <EyeOff className="h-4 w-4" />
                      ) : (
                        <Eye className="h-4 w-4" />
                      )}
                    </button>
                  </div>
                  {errors.password && (
                    <p className="mt-1 text-xs text-red-600">{errors.password.message}</p>
                  )}
                </div>

                <div className="space-y-1">
                  <Label htmlFor="confirmPassword" className="text-xs text-gray-700">Confirm Password</Label>
                  <div className="relative">
                    <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
                    <Input
                      id="confirmPassword"
                      type={showConfirmPassword ? "text" : "password"}
                      placeholder="Confirm new password"
                      {...register("confirmPassword")}
                      className={`pl-9 pr-10 border-gray-300 focus:ring-blue-500 ${errors.confirmPassword ? 'border-red-500' : ''}`}
                      disabled={isSubmitting}
                    />
                    <button
                      type="button"
                      onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                      tabIndex={-1}
                    >
                      {showConfirmPassword ? (
                        <EyeOff className="h-4 w-4" />
                      ) : (
                        <Eye className="h-4 w-4" />
                      )}
                    </button>
                  </div>
                  {errors.confirmPassword && (
                    <p className="mt-1 text-xs text-red-600">{errors.confirmPassword.message}</p>
                  )}
                </div>

                {/* Password Requirements */}
                <div className="rounded-lg border border-gray-100 bg-gray-50 p-3">
                  <p className="text-xs font-medium mb-2 text-gray-700">Password Requirements:</p>
                  <ul className="text-xs text-gray-500 space-y-1">
                    <li className="flex items-center gap-2">
                      <span className={passwordValue.length >= 8 ? 'text-green-600' : ''}>
                        {passwordValue.length >= 8 ? '✓' : '•'}
                      </span>
                      At least 8 characters
                    </li>
                    <li className="flex items-center gap-2">
                      <span className={passwordValue === confirmPasswordValue && passwordValue.length > 0 ? 'text-green-600' : ''}>
                        {passwordValue === confirmPasswordValue && passwordValue.length > 0 ? '✓' : '•'}
                      </span>
                      Passwords match
                    </li>
                  </ul>
                </div>

                {error && (
                  <div className="text-sm text-red-600">{error}</div>
                )}

                <Button
                  type="submit"
                  className="w-full text-white bg-blue-600 hover:bg-blue-700 shadow-md hover:shadow-lg transition-all"
                  disabled={isSubmitting || !form.formState.isValid}
                >
                  {isSubmitting ? (
                    <>
                      <Spinner className="mr-2 h-4 w-4" />
                      Resetting...
                    </>
                  ) : (
                    "Reset Password"
                  )}
                </Button>
              </form>
            </CardContent>

            {/* Secured by Footer */}
            <div className="relative">
              <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-60"></div>
              <div className="flex justify-center py-4">
                <p className="text-xs text-gray-500 font-medium tracking-wide">
                  Secured by <span className="text-blue-600 font-semibold">{import.meta.env.VITE_COMPANY_NAME || 'Athena Networks'}</span>
                </p>
              </div>
            </div>
          </Card>
        )}
      </div>
    </div>
  );
};

export default ResetPassword;
