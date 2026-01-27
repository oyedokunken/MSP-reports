import { useEffect, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { logger } from '@/lib/logger'
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

                // Store the token with correct key
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
                logger.error('Auth callback error:', err);
                setError(error.response?.data?.message || 'Failed to complete authentication');
                localStorage.removeItem('auth_token');
                setTimeout(() => navigate('/sign-in?error=auth_failed'), 2000);
            }
        };

        processCallback();
    }, [searchParams, navigate]);

    if (error) {
        return (
            <div className="flex min-h-screen items-center justify-center flex-col gap-4">
                <p className="text-red-500">Authentication Error: {error}</p>
                <p className="text-gray-500">Redirecting...</p>
            </div>
        );
    }

    return (
        <div className="flex min-h-screen items-center justify-center flex-col gap-4">
            <Loader2 className="h-8 w-8 animate-spin text-primary" />
            <p className="text-gray-600">{status}</p>
        </div>
    );
}
