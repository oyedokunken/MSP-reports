import { BrandingPanel, MobileLogo, ForgotPasswordForm, SuccessMessage } from "../components";
import { useForgotPassword } from "../hooks";

const ForgotPassword = () => {
  const {
    email,
    error,
    isSubmitting,
    success,
    setEmail,
    handleSubmit
  } = useForgotPassword();

  if (success) {
    return (
      <div className="min-h-screen flex">
        <BrandingPanel />
        <div className="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-12 bg-gray-50">
          <div className="max-w-md w-full">
            <MobileLogo />
            <SuccessMessage email={email} />
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen flex">
      <BrandingPanel />
      <div className="flex-1 flex items-center justify-center px-4 sm:px-6 lg:px-12 bg-gray-50">
        <div className="max-w-md w-full">
          <MobileLogo />
          <ForgotPasswordForm
            email={email}
            error={error}
            isSubmitting={isSubmitting}
            onEmailChange={setEmail}
            onSubmit={handleSubmit}
          />
        </div>
      </div>
    </div>
  );
};

export default ForgotPassword;
