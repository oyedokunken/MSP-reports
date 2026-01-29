import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { transformIntegrations } from '@/pages/integrations/utils/transformIntegrations';
import type { IntegrationListResponse } from '@/types/api/integration';
import type { Integration } from '@/pages/integrations/types/integration';

export const integrationKeys = {
  all: ['integrations'] as const,
  lists: () => [...integrationKeys.all, 'list'] as const,
};

interface IntegrationsData {
  integrations: Integration[];
  availableTypes: string[];
}

const fetchIntegrations = async (): Promise<IntegrationsData> => {
  const response = await api.get<IntegrationListResponse>('/integrations');
  return {
    integrations: transformIntegrations(response) || [],
    availableTypes: response.available_types || [],
  };
};

export const useIntegrationsQuery = (options?: {
  refetchInterval?: number | false
  enabled?: boolean
}) => {
  return useQuery({
    queryKey: integrationKeys.lists(),
    queryFn: fetchIntegrations,
    refetchInterval: options?.refetchInterval ?? 10000, // Default 10s (faster for progress updates)
    staleTime: 5000,
    enabled: options?.enabled ?? true,
  });
};

export const useSetSourceOfTruthMutation = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.patch('/company/source-integration', { integration_id: id });
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: integrationKeys.lists() });
    },
  });
};

export const useSyncIntegrationMutation = () => {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.get(`/integrations/${id}/sync`);
      return id;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: integrationKeys.lists() });
    },
  });
};
