import { ThemeToggle } from "@/components/ui/theme-toggle";
import { Outlet } from "react-router-dom";

const Layout = () => {
    return (
        <div className="fixed inset-0 overflow-hidden auth-theme">
            <div className="fixed right-4 top-4 z-50">
                <ThemeToggle />
            </div>
            <Outlet />
        </div>
    );
};

export default Layout;
