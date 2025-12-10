import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import type { CredentialField } from '@/components/integrations/DynamicCredentialForm';

interface IntegrationConfig {
  name: string;
  description: string;
  credential_fields: CredentialField[];
}

interface ApiResponse {
  success: boolean;
  config?: IntegrationConfig;
  error?: string;
}

interface UseIntegrationConfigResult {
  config: IntegrationConfig | null;
  loading: boolean;
  error: string | null;
}

export const useIntegrationConfig = (integrationType: string | null): UseIntegrationConfigResult => {
  const [config, setConfig] = useState<IntegrationConfig | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!integrationType) {
      setConfig(null);
      return;
    }

    const fetchConfig = async () => {
      setLoading(true);
      setError(null);

      try {
        // api.get returns the unwrapped data
        const response = await api.get<ApiResponse>(
          `/integrations/config/${integrationType}`
        );

        console.log('API Data:', response);

        if (!response) {
          console.error('No data returned');
          setError('Invalid response from server');
          return;
        }

        // Cast to our expected type
        const data = response as ApiResponse;

        if (data.success && data.config) {
          console.log('Setting config:', data.config);
          // Store the full config (including hidden fields for default values)
          setConfig(data.config);
        } else {
          console.error('Success false or no config:', data);
          setError(data.error || 'Failed to load integration configuration');
        }
      } catch (err: unknown) {
        console.error('Failed to fetch integration config:', err);
        const error = err as { response?: { data?: { error?: string } }; message?: string };
        console.error('Error response:', error.response);
        setError(error.response?.data?.error || error.message || 'Failed to load integration configuration');
      } finally {
        setLoading(false);
      }
    };

    fetchConfig();
  }, [integrationType]);

  return { config, loading, error };
};
