import { useState } from 'react';
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

const EMAIL_REGEX = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

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
  const [emailError, setEmailError] = useState<string | null>(null);
  const [passwordError, setPasswordError] = useState<string | null>(null);
  const [touched, setTouched] = useState({ email: false, password: false });

  const validateEmail = (value: string): string | null => {
    if (!value.trim()) {
      return 'Email address is required';
    }
    if (!EMAIL_REGEX.test(value)) {
      return 'Please enter a valid email address';
    }
    return null;
  };

  const validatePassword = (value: string): string | null => {
    if (!value) {
      return 'Password is required';
    }
    if (value.length < 6) {
      return 'Password must be at least 6 characters';
    }
    return null;
  };

  const handleEmailChange = (value: string) => {
    onEmailChange(value);
    if (touched.email) {
      setEmailError(validateEmail(value));
    }
  };

  const handlePasswordChange = (value: string) => {
    onPasswordChange(value);
    if (touched.password) {
      setPasswordError(validatePassword(value));
    }
  };

  const handleEmailBlur = () => {
    setTouched(prev => ({ ...prev, email: true }));
    setEmailError(validateEmail(email));
  };

  const handlePasswordBlur = () => {
    setTouched(prev => ({ ...prev, password: true }));
    setPasswordError(validatePassword(password));
  };

  const handleFormSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    const emailErr = validateEmail(email);
    const passwordErr = validatePassword(password);

    setEmailError(emailErr);
    setPasswordError(passwordErr);
    setTouched({ email: true, password: true });

    if (!emailErr && !passwordErr) {
      onSubmit(e);
    }
  };

  return (
    <form className="space-y-6" onSubmit={handleFormSubmit} noValidate>
      <div>
        <label htmlFor="email" className="block text-sm text-left font-medium text-gray-900 mb-2">
          Email address
        </label>
        <input
          id="email"
          name="email"
          type="email"
          autoComplete="email"
          value={email}
          onChange={(e) => handleEmailChange(e.target.value)}
          onBlur={handleEmailBlur}
          disabled={isSubmitting}
          aria-invalid={!!emailError}
          aria-describedby={emailError ? "email-error" : undefined}
          className={`appearance-none relative block w-full px-4 py-3 border placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out ${emailError && touched.email
            ? 'border-red-500 focus:ring-red-500'
            : 'border-gray-300'
            }`}
          placeholder="youremail@example.com"
        />
        {emailError && touched.email && (
          <p id="email-error" className="mt-1 text-sm text-red-600">{emailError}</p>
        )}
      </div>

      <div>
        <label htmlFor="password" className="block text-sm text-left font-medium text-gray-900 mb-2">
          Password
        </label>
        <div className="relative">
          <input
            id="password"
            name="password"
            type={showPassword ? "text" : "password"}
            autoComplete="current-password"
            value={password}
            onChange={(e) => handlePasswordChange(e.target.value)}
            onBlur={handlePasswordBlur}
            disabled={isSubmitting}
            aria-invalid={!!passwordError}
            aria-describedby={passwordError ? "password-error" : undefined}
            className={`appearance-none relative block w-full px-4 py-3 pr-12 border placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition duration-150 ease-in-out ${passwordError && touched.password
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
        {passwordError && touched.password && (
          <p id="password-error" className="mt-1 text-sm text-red-600">{passwordError}</p>
        )}
      </div>

      <div className="flex items-center justify-between">
        <div className="flex items-center">
          <input
            id="remember-me"
            name="remember-me"
            type="checkbox"
            checked={rememberMe}
            onChange={(e) => onRememberMeChange(e.target.checked)}
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
