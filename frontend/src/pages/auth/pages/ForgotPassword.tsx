import { MobileLogo, ForgotPasswordForm, SuccessMessage } from "../components";
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

  return (
    <div className="min-h-screen flex items-center justify-center relative bg-blue-950 overflow-hidden">
      {/* Background Decoration */}
      <div className="absolute inset-0 z-0 pointer-events-none">
        <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
        <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
      </div>

      <div className="absolute top-8 left-8 z-20">
        <MobileLogo />
      </div>

      <div className="relative z-10 w-full max-w-md px-4 py-12">

        {/* Form Container */}
        {success ? (
          <SuccessMessage email={email} />
        ) : (
          <ForgotPasswordForm
            email={email}
            error={error}
            isSubmitting={isSubmitting}
            onEmailChange={setEmail}
            onSubmit={handleSubmit}
          />
        )}
      </div>
    </div>
  );
};

export default ForgotPassword;
