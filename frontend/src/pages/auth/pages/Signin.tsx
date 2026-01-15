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
import { useSignin } from "../hooks";
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

  const [showLdapModal, setShowLdapModal] = useState(false);
  const [ldapConfigured] = useState(false); // Set to true once LDAP is configured
  const [oauthConfigured] = useState(true); // Keycloak OIDC is configured
  const [samlConfigured] = useState(true); // SAML is now configured
  const { login } = useAuth();
  const navigate = useNavigate();
  const toast = useToast();

  const handleLdapLogin = async (username: string, password: string) => {
    try {
      // Construct email from username (add domain if needed)
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
    } catch (err: any) {
      const errorMessage = err.response?.data?.message || err.message || 'LDAP authentication failed';
      throw new Error(errorMessage);
    }
  };

  const handleOAuthLogin = () => {
    // Redirect to Keycloak OAuth endpoint
    window.location.href = `${import.meta.env.VITE_API_URL || 'http://localhost:8081'}/api/auth/keycloak/redirect`;
  };

  const handleSamlLogin = () => {
    // Redirect to SAML endpoint
    window.location.href = `${import.meta.env.VITE_API_URL || 'http://localhost:8081'}/api/auth/saml/login`;
  };

  return (
    <div className="min-h-screen flex">
      <BrandingPanel />

      {/* Right Panel - Form */}
      <div className="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-12 bg-gray-50">
        <div className="max-w-md w-full">
          <MobileLogo />

          {/* Form Container */}
          <div className="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
            {/* Header */}
            <div className="text-center mb-8">
              <h2 className="text-3xl font-bold text-gray-900 font-athletics">Sign In</h2>
              <p className="mt-2 text-sm text-gray-600 font-athletics">
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

            <AuthFooter />
          </div>

          {/* LDAP Login Modal */}
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
