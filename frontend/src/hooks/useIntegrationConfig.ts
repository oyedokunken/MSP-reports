import { useState, useEffect } from 'react';
import { api } from '@/lib/api';
import { logger } from '@/lib/logger';
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
        const response = await api.get<ApiResponse>(
          `/integrations/config/${integrationType}`
        );

        if (!response) {
          setError('Invalid response from server');
          return;
        }

        const data = response as ApiResponse;

        if (data.success && data.config) {
          setConfig(data.config);
        } else {
          setError(data.error || 'Failed to load integration configuration');
        }
      } catch (err: unknown) {
        const error = err as { response?: { data?: { error?: string } }; message?: string };
        logger.error('Failed to fetch integration config', err, { response: error.response });
        setError(error.response?.data?.error || error.message || 'Failed to load integration configuration');
      } finally {
        setLoading(false);
      }
    };

    fetchConfig();
  }, [integrationType]);

  return { config, loading, error };
};
