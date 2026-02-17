import { useState } from "react";
import {
  MobileLogo,
  SigninForm,
  EnterpriseAuthOptions,
  AuthDivider,
  ContactAdminLink,
  LdapLoginModal,
} from "../components";
import { useSignin } from "../hooks";
import { ThemeToggle } from "@/components/ui/theme-toggle";
import { useAuth } from "@/contexts/AuthContext";
import { useNavigate, Link } from "react-router-dom";
import { useToast } from "@/components/ui/Toast";

const Signin = () => {
  const {
    form,
    error,
    isSubmitting,
    showAuthOptions,
    showPassword,
    handleSubmit,
    setShowAuthOptions,
    setShowPassword
  } = useSignin();


  const [showLdapModal, setShowLdapModal] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();
  const toast = useToast();


  const handleLdapLogin = async (username: string, password: string) => {
    try {
      const email = username.includes('@') ? username : `${username}@domain.local`;

      await login({
        email,
        password,
        auth_method: "ldap",
        rememberMe: false,
      });

      toast.success('Login Successful', 'Welcome back!');
      setShowLdapModal(false);
      navigate('/platform/dashboard');
    } catch (err: unknown) {
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      const errorMessage = error.response?.data?.message || error.message || 'LDAP authentication failed';
      throw new Error(errorMessage);
    }
  };

  const handleOAuthLogin = () => {
    window.location.href = `/api/auth/keycloak/redirect`;
  };

  const handleSamlLogin = () => {
    window.location.href = `/api/auth/saml/login`;
  };

  return (
    <div className="min-h-screen flex items-center justify-center relative bg-[hsl(var(--auth-background))] overflow-hidden">
      {/* Background Decoration - High-contrast premium waves and blobs */}
      <div className="absolute inset-0 z-0 pointer-events-none overflow-hidden bg-[radial-gradient(circle_at_20%_20%,hsl(var(--primary)/0.08)_0%,transparent_50%),radial-gradient(circle_at_80%_80%,hsl(var(--primary)/0.08)_0%,transparent_50%)]">
        {/* Top Left Wave */}
        <svg className="absolute -top-[10%] -left-[10%] w-[70%] h-auto text-primary/40 -rotate-12 mix-blend-soft-light" viewBox="0 0 1440 320" preserveAspectRatio="none">
          <path fill="currentColor" d="M0,160L48,176C96,192,192,224,288,213.3C384,203,480,149,576,144C672,139,768,181,864,197.3C960,213,1056,203,1152,186.7C1248,171,1344,149,1392,138.7L1440,128L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path>
        </svg>

        {/* Bottom Right Wave */}
        <svg className="absolute -bottom-[10%] -right-[10%] w-[70%] h-auto text-primary/40 rotate-[168deg] mix-blend-soft-light" viewBox="0 0 1440 320" preserveAspectRatio="none">
          <path fill="currentColor" d="M0,160L48,176C96,192,192,224,288,213.3C384,203,480,149,576,144C672,139,768,181,864,197.3C960,213,1056,203,1152,186.7C1248,171,1344,149,1392,138.7L1440,128L1440,0L1392,0C1344,0,1248,0,1152,0C1056,0,960,0,864,0C768,0,672,0,576,0C480,0,384,0,288,0C192,0,96,0,48,0L0,0Z"></path>
        </svg>

        <div className="absolute top-[-10%] left-[-10%] w-[60%] h-[60%] bg-primary/25 rounded-full blur-[120px] animate-pulse" />
        <div className="absolute bottom-[-10%] right-[-10%] w-[60%] h-[60%] bg-primary/25 rounded-full blur-[120px] animate-pulse" style={{ animationDelay: '2s' }} />
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-[radial-gradient(circle_at_center,transparent_0%,hsl(var(--auth-background))_100%)] opacity-90" />
      </div>

      {/* Header Content - Logo */}
      <div className="absolute top-6 sm:top-8 left-0 right-0 sm:left-8 sm:right-auto z-20 flex justify-center sm:block">
        <MobileLogo />
      </div>

      {/* Theme Toggle - Positioned in bottom left corner */}
      <div className="absolute bottom-6 left-6 z-20">
        <ThemeToggle />
      </div>

      {/* Legal Links - Updated for system theme */}
      <div className="absolute bottom-6 sm:bottom-auto sm:top-8 left-0 right-0 sm:left-auto sm:right-8 z-20 flex justify-center items-center gap-4 px-4">
        <Link to="/privacy-policy" className="text-xs sm:text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
          Privacy policy
        </Link>
        <span className="text-muted-foreground/30 select-none">|</span>
        <Link to="/terms-of-service" className="text-xs sm:text-sm font-medium text-muted-foreground hover:text-foreground transition-colors">
          Terms of service
        </Link>
      </div>

      <div className="relative z-10 w-full max-w-xl px-4 py-20 sm:py-12">

        {/* Form Container - Glassmorphism System Card */}
        <div className="bg-[hsl(var(--auth-card))] rounded-2xl shadow-2xl p-6 sm:p-10 border border-border/50 backdrop-blur-md relative overflow-hidden">
          {/* Subtle line at the top with brand colors */}
          <div className="absolute top-0 left-0 right-0 h-1 bg-brand-gradient opacity-80" />

          {/* Header */}
          <div className="text-center mb-8">
            <h2 className="text-3xl font-extrabold text-foreground tracking-tight">Welcome to MSP Reports</h2>
            <p className="mt-3 text-sm text-muted-foreground">
              Sign in to your account to manage your data and insights
            </p>
          </div>

          {/* Sign In Form */}
          <div
            aria-hidden={showAuthOptions}
            className={`transform transition-all duration-500 ease-in-out ${showAuthOptions
              ? 'max-h-0 opacity-0 -translate-y-4 pointer-events-none overflow-hidden'
              : 'max-h-[1000px] opacity-100 translate-y-0'
              }`}
          >
            <SigninForm
              form={form}
              error={error}
              isSubmitting={isSubmitting}
              showPassword={showPassword}
              onShowPasswordToggle={() => setShowPassword(!showPassword)}
              onSubmit={handleSubmit}
            />
          </div>

          <AuthDivider
            showAuthOptions={showAuthOptions}
            onToggle={() => setShowAuthOptions(!showAuthOptions)}
          />

          <EnterpriseAuthOptions
            isOpen={showAuthOptions}
            onLdapClick={() => setShowLdapModal(true)}
            onOAuthClick={handleOAuthLogin}
            onSamlClick={handleSamlLogin}
          />


          <div className="mt-8 pt-6 border-t border-border/50">
            <ContactAdminLink />
          </div>
        </div>


        {/* LDAP Login Modal */}
        <LdapLoginModal
          isOpen={showLdapModal}
          onClose={() => setShowLdapModal(false)}
          onSubmit={handleLdapLogin}
        />
      </div>
    </div>
  );
}

export default Signin;
