import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import { transformIntegrations } from '@/pages/integrations/utils/transformIntegrations';
import type { IntegrationListResponse } from '@/types/api/integration';
import type { Integration } from '@/pages/integrations/types/integration';

// Keys
export const integrationKeys = {
  all: ['integrations'] as const,
  lists: () => [...integrationKeys.all, 'list'] as const,
};

// Fetcher
const fetchIntegrations = async (): Promise<Integration[]> => {
  const response = await api.get('/integrations') as unknown as IntegrationListResponse;
  return transformIntegrations(response) || [];
};

export const useIntegrationsQuery = () => {
  return useQuery({
    queryKey: integrationKeys.lists(),
    queryFn: fetchIntegrations,
    refetchInterval: 30000, // Poll every 30s
    staleTime: 10000, // Consider data fresh for 10s
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
      // Invalidate to immediately refresh the integration list UI
      queryClient.invalidateQueries({ queryKey: integrationKeys.lists() });
    },
  });
};
