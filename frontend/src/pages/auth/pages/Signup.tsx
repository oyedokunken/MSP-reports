import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card";
import { AlertCircle, ArrowLeft } from "lucide-react";
import { Link } from "react-router-dom";

const Signup = () => {
  return (
    <Card className="w-full max-w-[450px] border-border bg-[hsl(var(--auth-card))] shadow-2xl">
      <CardHeader className="space-y-3 text-center">
        <div className="mx-auto w-12 h-12 rounded-full bg-blue-500/10 flex items-center justify-center">
          <AlertCircle className="h-6 w-6 text-blue-500" />
        </div>
        <CardTitle className="text-xl font-bold">Invitation Only</CardTitle>
        <CardDescription className="text-sm">
          This is an enterprise platform. New accounts must be created by your organization's administrator.
        </CardDescription>
      </CardHeader>
  
      <CardContent className="space-y-4">
        <div className="rounded-lg border border-border bg-muted/50 p-4 space-y-3">
          <h3 className="font-semibold text-sm">Need Access?</h3>
          <ul className="space-y-2 text-sm text-muted-foreground">
            <li className="flex items-start gap-2">
              <span className="text-blue-500 mt-0.5">•</span>
              <span>Contact your system administrator to request an account</span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-blue-500 mt-0.5">•</span>
              <span>Your admin can create users from the dashboard</span>
            </li>
            <li className="flex items-start gap-2">
              <span className="text-blue-500 mt-0.5">•</span>
              <span>You'll receive login credentials via email</span>
            </li>
          </ul>
        </div>

        <Link to="/sign-in" className="w-full block">
          <Button className="w-full" variant="default">
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Sign In
          </Button>
        </Link>
      </CardContent>
  
      {/* Footer */}
      <CardFooter className="flex justify-center border-t border-border pt-4">
        <p className="text-sm text-muted-foreground">
          Already have an account?{" "}
          <Link to="/sign-in" className="text-primary font-medium hover:underline">
            Sign in here
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
