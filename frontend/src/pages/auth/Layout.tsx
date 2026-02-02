import { Outlet } from "react-router-dom";
import { useEffect } from "react";

const Layout = () => {
    // Force light mode and hide scrollbar on auth pages
    useEffect(() => {
        document.documentElement.classList.remove('dark');
        document.body.classList.add('no-scrollbar');
        
        return () => {
            document.body.classList.remove('no-scrollbar');
        };
    }, []);

    return (
        <div className="min-h-screen overflow-y-auto no-scrollbar auth-theme">
            <Outlet />
        </div>
    );
};

export default Layout;
