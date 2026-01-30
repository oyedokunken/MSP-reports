import { useState } from "react";
import {
  BrandingPanel,
  MobileLogo,
  SigninForm,
  EnterpriseAuthOptions,
  AuthDivider,
  AuthFooter,
  ContactAdminLink,
  LdapLoginModal,
} from "../components";
import { useSignin, useAuthConfig } from "../hooks";
import { useAuth } from "@/contexts/AuthContext";
import { useNavigate } from "react-router-dom";
import { useToast } from "@/components/ui/Toast";

const Signin = () => {
  const {
    email,
    password,
    error,
    isSubmitting,
    rememberMe,
    showAuthOptions,
    showPassword,
    setEmail,
    setPassword,
    handleSubmit,
    setRememberMe,
    setShowAuthOptions,
    setShowPassword
  } = useSignin();

  const { config: authConfig } = useAuthConfig();

  const [showLdapModal, setShowLdapModal] = useState(false);
  const { login } = useAuth();
  const navigate = useNavigate();
  const toast = useToast();

  const ldapConfigured = authConfig?.ldap.enabled ?? false;
  const oauthConfigured = authConfig?.oauth.enabled ?? false;
  const samlConfigured = authConfig?.saml.enabled ?? false;
  const hasEnterpriseAuth = ldapConfigured || oauthConfigured || samlConfigured;

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
    <div className="min-h-screen flex">
      <BrandingPanel />

      <div className="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-12 bg-gray-50">
        <div className="max-w-md w-full">
          <MobileLogo />

          <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            <div className="text-center mb-8">
              <h2 className="text-3xl font-bold text-gray-900 font-athletics">Sign In</h2>
              <p className="mt-2 text-sm text-gray-600 font-athletics">
                Access your reporting dashboard
              </p>
            </div>

            <div
              aria-hidden={showAuthOptions}
              className={`transform transition-all duration-300 ease-in-out ${showAuthOptions
                ? 'max-h-0 opacity-0 -translate-y-2 pointer-events-none overflow-hidden'
                : 'max-h-[1000px] opacity-100 translate-y-0'
                }`}
            >
              <SigninForm
                email={email}
                password={password}
                error={error}
                isSubmitting={isSubmitting}
                rememberMe={rememberMe}
                showPassword={showPassword}
                onEmailChange={setEmail}
                onPasswordChange={setPassword}
                onRememberMeChange={setRememberMe}
                onShowPasswordToggle={() => setShowPassword(!showPassword)}
                onSubmit={handleSubmit}
              />
              <ContactAdminLink />
            </div>

            {hasEnterpriseAuth && (
              <>
                <AuthDivider
                  showAuthOptions={showAuthOptions}
                  onToggle={() => setShowAuthOptions(!showAuthOptions)}
                />

                <EnterpriseAuthOptions
                  isOpen={showAuthOptions}
                  onLdapClick={() => setShowLdapModal(true)}
                  onOAuthClick={handleOAuthLogin}
                  onSamlClick={handleSamlLogin}
                  ldapConfigured={ldapConfigured}
                  oauthConfigured={oauthConfigured}
                  samlConfigured={samlConfigured}
                />

                {showAuthOptions && <ContactAdminLink />}
              </>
            )}

            <AuthFooter />
          </div>

          <LdapLoginModal
            isOpen={showLdapModal}
            onClose={() => setShowLdapModal(false)}
            onSubmit={handleLdapLogin}
          />
        </div>
      </div>
    </div>
  );
}

export default Signin;
