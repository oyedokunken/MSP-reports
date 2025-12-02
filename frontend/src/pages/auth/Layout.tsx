import { Outlet } from "react-router-dom";
import { useEffect } from "react";

const Layout = () => {
    // Force light mode on auth pages
    useEffect(() => {
        document.documentElement.classList.remove('dark');
    }, []);

    return (
        <div className="min-h-screen overflow-auto auth-theme">
            <Outlet />
        </div>
    );
};

export default Layout;
