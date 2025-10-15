
import { type FormEvent, useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { User, Mail, Lock } from "lucide-react";
import { Spinner } from "@/components/ui/shadcn-io/spinner";
import { Link, useNavigate } from "react-router-dom";

const Signup = () => {
    const isLoaded = true; // TODO: Replace with your auth loading state
    const [firstName, setFirstName] = useState("");
    const [lastName, setLastName] = useState("");
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [pendingVerification, setPendingVerification] = useState(false);
    const [code, setCode] = useState("");
    const [error, setError] = useState("");
    const [isSubmitting, setIsSubmitting] = useState(false);
    const navigate = useNavigate();
    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        if (!isLoaded) return;
        setError("");
        setIsSubmitting(true);

        try {
            // TODO: Implement your own sign-up logic here
            // Example: Call your API to create the user
            // const response = await fetch('/api/auth/signup', {
            //   method: 'POST',
            //   body: JSON.stringify({ firstName, lastName, email, password })
            // });
            
            // For now, simulate sending verification email
            await new Promise(resolve => setTimeout(resolve, 1000));
            setPendingVerification(true);
        } catch (err: any) {
            setError(err.errors?.[0]?.message || "Failed to sign up");
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleVerify = async (e: FormEvent) => {
        e.preventDefault();
        if (!isLoaded) return;
        setError("");
        setIsSubmitting(true);

        try {
            // TODO: Implement your own verification logic here
            // Example: Verify the code with your API
            // const response = await fetch('/api/auth/verify', {
            //   method: 'POST',
            //   body: JSON.stringify({ email, code })
            // });
            
            // For now, simulate verification and create session
            await new Promise(resolve => setTimeout(resolve, 1000));
            
            navigate("/dashboard");
        } catch (err: any) {
            setError(err.errors?.[0]?.message || "Invalid code");
        } finally {
            setIsSubmitting(false);
        }
    };

    if (pendingVerification) {
        return (
            <Card className="w-full max-w-[400px] border-border bg-[hsl(var(--auth-card))] shadow-2xl">
                <CardHeader className="space-y-1 text-center">
                    <CardTitle className="text-xl font-bold">Check your email</CardTitle>
                    <CardDescription className="text-sm">
                        We sent a verification code to {email}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form onSubmit={handleVerify} className="space-y-3">
                        <div className="space-y-1.5">
                            <Label htmlFor="code">Verification Code</Label>
                            <Input
                                id="code"
                                placeholder="Enter code"
                                value={code}
                                onChange={(e) => setCode(e.target.value)}
                                required
                            />
                        </div>

                        {error && (
                            <div className="text-sm text-destructive">{error}</div>
                        )}

                        <Button type="submit" className="w-full" disabled={isSubmitting}>
                            {isSubmitting ? (
                                <>
                                    <Spinner variant="circle" size={16} className="mr-2" />
                                    Verifying...
                                </>
                            ) : (
                                "Verify Email"
                            )}
                        </Button>
                    </form>
                </CardContent>
                
                {/* Secured by Footer */}
                <div className="relative mt-4">
                    <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-orange-500 to-transparent opacity-60"></div>
                    <div className="flex justify-center py-3">
                        <p className="text-xs text-muted-foreground/70 font-medium tracking-wide">
                            Secured by <span className="text-orange-500 font-semibold">Athena Networks</span>
                        </p>
                    </div>
                </div>
            </Card>
        );
    }

    return (
        <Card className="w-full max-w-[400px] border-border bg-[hsl(var(--auth-card))] shadow-2xl">
          <CardHeader className="space-y-0.5 text-center">
            <CardTitle className="text-lg font-bold">Create an Account</CardTitle>
            <CardDescription className="text-sm">
              Get started with your free account
            </CardDescription>
          </CardHeader>
      
          <CardContent className="space-y-3">
            <form onSubmit={handleSubmit} className="space-y-5">
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="firstName" className="text-xs">First name</Label>
                  <div className="relative">
                    <User className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/60" />
                    <Input
                      id="firstName"
                      type="text"
                      placeholder="John"
                      value={firstName}
                      onChange={(e) => setFirstName(e.target.value)}
                      className="pl-9"
                      required
                    />
                  </div>
                </div>
                <div className="space-y-1">
                  <Label htmlFor="lastName" className="text-xs">Last name</Label>
                  <div className="relative">
                    <User className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/60" />
                    <Input
                      id="lastName"
                      type="text"
                      placeholder="Doe"
                      value={lastName}
                      onChange={(e) => setLastName(e.target.value)}
                      className="pl-9"
                      required
                    />
                  </div>
                </div>
              </div>

              <div className="space-y-1">
                <Label htmlFor="email" className="text-xs">Email address</Label>
                <div className="relative">
                  <Mail className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/60" />
                  <Input
                    id="email"
                    type="email"
                    placeholder="john@example.com"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    className="pl-9"
                    required
                  />
                </div>
              </div>

              <div className="space-y-1">
                <Label htmlFor="password" className="text-xs">Password</Label>
                <div className="relative">
                  <Lock className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground/60" />
                  <Input
                    id="password"
                    type="password"
                    placeholder="Create a password"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    className="pl-9"
                    required
                  />
                </div>
              </div>
      
              {error && (
                <div className="text-sm text-destructive">{error}</div>
              )}
      
              <Button type="submit" className="w-full text-white" disabled={isSubmitting}>
                {isSubmitting ? (
                  <>
                    <Spinner variant="circle" size={16} className="mr-2" />
                    Creating account...
                  </>
                ) : (
                  "Create Account →"
                )}
              </Button>
            </form>
          </CardContent>
      
          {/* Footer */}
          <CardFooter className="flex justify-center border-t border-border mt-2 pt-2 border-0">
            <p className="text-sm text-muted-foreground">
              Already have an account?{" "}
              <Link to="/sign-in" className="text-primary font-medium hover:underline">
                Sign in
              </Link>
            </p>
          </CardFooter>
          
          {/* Secured by Footer */}
          <div className="relative">
            <div className="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-60"></div>
            <div className="flex justify-center py-3">
              <p className="text-xs text-muted-foreground/70 font-medium tracking-wide">
                Secured by <span className="text-blue-500 font-semibold">Athena Networks</span>
              </p>
            </div>
          </div>
        </Card>
      );
}

export default Signup;
