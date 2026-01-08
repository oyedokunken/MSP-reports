import {
  BrandingPanel,
  MobileLogo,
  SigninForm,
  EnterpriseAuthOptions,
  AuthDivider,
  AuthFooter,
  ContactAdminLink,
} from "../components";
import { useSignin } from "../hooks";

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

            <EnterpriseAuthOptions isOpen={showAuthOptions} onLdapClick={() => { }} />

            {showAuthOptions && <ContactAdminLink />}

            <AuthFooter />
          </div>
        </div>
      </div>
    </div>
  );
}

export default Signin;
