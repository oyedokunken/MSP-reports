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
    window.location.href = `${import.meta.env.VITE_API_URL || 'http://localhost:8081'}/api/auth/keycloak/redirect`;
  };

  const handleSamlLogin = () => {
    window.location.href = `${import.meta.env.VITE_API_URL || 'http://localhost:8081'}/api/auth/saml/login`;
  };

  return (
    <div className="min-h-screen flex items-center justify-center relative bg-blue-950 overflow-hidden">
      {/* Background Decoration */}
      <div className="absolute inset-0 z-0 pointer-events-none">
        <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
        <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
      </div>

      {/* Header Content - Logo */}
      <div className="absolute top-6 sm:top-8 left-0 right-0 sm:left-8 sm:right-auto z-20 flex justify-center sm:block">
        <MobileLogo />
      </div>

      {/* Legal Links - Top right on desktop, Bottom on mobile */}
      <div className="absolute bottom-6 sm:bottom-auto sm:top-8 left-0 right-0 sm:left-auto sm:right-8 z-20 flex justify-center gap-6 px-4">
        <Link to="/privacy-policy" className="text-xs sm:text-sm font-medium text-blue-200 hover:text-white transition-colors">
          Privacy Policy
        </Link>
        <Link to="/terms-of-service" className="text-xs sm:text-sm font-medium text-blue-200 hover:text-white transition-colors">
          Terms of Service
        </Link>
      </div>

      <div className="relative z-10 w-full max-w-md px-4 py-20 sm:py-12">

        {/* Form Container */}
        <div className="bg-white rounded-2xl shadow-2xl p-6 sm:p-8 border border-white/10 backdrop-blur-sm">
          {/* Header */}
          <div className="text-center mb-8">
            <h2 className="text-3xl font-bold text-gray-900">Sign In</h2>
            <p className="mt-2 text-sm text-gray-600">
              Access your reporting dashboard
            </p>
          </div>

          {/* Sign In Form */}
          <div
            aria-hidden={showAuthOptions}
            className={`transform transition-all duration-300 ease-in-out ${showAuthOptions
              ? 'max-h-0 opacity-0 -translate-y-2 pointer-events-none overflow-hidden'
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


          <div className="mt-8 pt-6 border-t border-gray-100">
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
