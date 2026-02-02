import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import { api } from '@/lib/api';

export default function AuthCallback() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const [error, setError] = useState<string | null>(null);
    const [status, setStatus] = useState('Processing authentication...');

    useEffect(() => {
        const processCallback = async () => {
            const token = searchParams.get('token');
            const errorParam = searchParams.get('error');

            if (errorParam) {
                setError(errorParam);
                setTimeout(() => navigate('/sign-in?error=' + errorParam), 2000);
                return;
            }

            if (!token) {
                setError('No token received');
                setTimeout(() => navigate('/sign-in?error=no_token'), 2000);
                return;
            }

            try {
                setStatus('Fetching user data...');

                localStorage.setItem('auth_token', token);

                const response = await api.get<{ data: Record<string, unknown> }>('/auth/me', {
                    headers: { Authorization: `Bearer ${token}` }
                });
                const userData = response.data;

                if (!userData) {
                    throw new Error('No user data received');
                }
                const company = userData.company;

                localStorage.setItem('user', JSON.stringify(userData));
                if (company) {
                    localStorage.setItem('company', JSON.stringify(company));
                }

                setStatus('Redirecting to dashboard...');

                window.location.href = '/platform/dashboard';

            } catch (err: unknown) {
                const error = err as { response?: { data?: { message?: string } }; message?: string }
                console.error('Auth callback error:', err);
                setError(error.response?.data?.message || 'Failed to complete authentication');
                localStorage.removeItem('auth_token');
                setTimeout(() => navigate('/sign-in?error=auth_failed'), 2000);
            }
        };

        processCallback();
    }, [searchParams, navigate]);

    if (error) {
        return (
            <div className="min-h-screen flex items-center justify-center relative bg-blue-950 overflow-hidden">
                {/* Background Decoration */}
                <div className="absolute inset-0 z-0 pointer-events-none">
                    <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
                    <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
                </div>
                <div className="relative z-10 flex flex-col items-center gap-4 text-center">
                    <p className="text-red-400 font-medium text-lg">Authentication Error: {error}</p>
                    <p className="text-blue-200">Redirecting...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen flex items-center justify-center relative bg-blue-950 overflow-hidden">
            {/* Background Decoration */}
            <div className="absolute inset-0 z-0 pointer-events-none">
                <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
                <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-blue-600/20 rounded-full blur-[120px]" />
            </div>
            <div className="relative z-10 flex flex-col items-center gap-4 text-center">
                <Loader2 className="h-10 w-10 animate-spin text-blue-400" />
                <p className="text-blue-100 font-medium">{status}</p>
            </div>
        </div>
    );
}
