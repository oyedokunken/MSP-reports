import { type ReactNode } from "react";
import AppSidebar from "./AppSidebar";
import FloatingNav from "./FloatingNav";
import { SidebarProvider, SidebarInset } from "@/components/ui/sidebar";
import { SupportChatbot } from "@/components/chatbot";
import { SkipLink } from "@/components/ui/SkipLink";
import { Outlet } from "react-router-dom";

/**
 * Floating Layout
 * Templify-style: Top navbar (full width) + sidebar below
 */

interface FloatingLayoutProps {
    children?: ReactNode;
}

export default function FloatingLayout({ children }: FloatingLayoutProps) {
    return (
        <SidebarProvider>
            <SkipLink />
            <div className="flex h-screen w-full bg-gray-50 dark:bg-background">
                <AppSidebar />
                <SidebarInset className="flex flex-col flex-1 overflow-hidden">
                    <FloatingNav />
                    <main id="main-content" className="flex-1 overflow-y-auto">
                        <div className="p-7 md:p-8 max-w-full">
                            {children || <Outlet />}
                        </div>
                    </main>
                </SidebarInset>
                <SupportChatbot />
            </div>
        </SidebarProvider>
    );
}
