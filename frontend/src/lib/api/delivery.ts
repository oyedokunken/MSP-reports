import { api } from '@/lib/api';

export interface DeliveryMethod {
    identifier: string;
    display_name: string;
    config_schema: {
        fields: Array<{
            name: string;
            label: string;
            type: string;
            required: boolean;
            placeholder?: string;
            help?: string;
        }>;
    };
}

export interface EmailConfig {
    destination?: string;
    cc?: string[];
}

export interface SharePointConfig {
    tenant_id?: string;
    client_id?: string;
    client_secret?: string;
    site_id?: string;
    drive_id?: string;
    folder_path?: string;
}

export interface DeliveryConfig {
    enabled_methods?: string[];
    method?: string; // Legacy support
    email?: EmailConfig;
    sharepoint?: SharePointConfig;
}

export interface CustomerDeliveryResponse {
    customer_id: number;
    customer_name: string;
    delivery_config: DeliveryConfig;
    available_methods: Record<string, DeliveryMethod>;
}

export async function getAvailableDeliveryMethods(): Promise<Record<string, DeliveryMethod>> {
    const response = await api.get('/delivery/methods');
    return response.data.data;
}

export async function getCustomerDeliveryConfig(customerId: number): Promise<CustomerDeliveryResponse> {
    const response = await api.get(`/customers/${customerId}/delivery`);
    return response.data.data;
}

export async function updateCustomerDeliveryConfig(
    customerId: number,
    config: DeliveryConfig
): Promise<DeliveryConfig> {
    const response = await api.patch(`/customers/${customerId}/delivery`, config);
    return response.data.data;
}

export async function testDeliveryConnection(
    customerId: number,
    method: string,
    config: Record<string, unknown>
): Promise<{ success: boolean; message: string }> {
    const response = await api.post(`/customers/${customerId}/delivery/test`, {
        method,
        config,
    });
    return response.data;
}
