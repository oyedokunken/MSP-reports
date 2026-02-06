import { z } from 'zod'

export const CustomerMappingSchema = z.object({
    type: z.enum(['auto', 'manual']),
    confidence_score: z.number(),
    mapped_at: z.string(),
    notes: z.string().nullable(),
})

export const SuggestedMatchSchema = z.object({
    customer_id: z.number(),
    canonical_name: z.string(),
    confidence_score: z.number(),
})

export const IntegrationCustomerSchema = z.object({
    integration_customer_id: z.number(),
    integration_id: z.number(),
    integration_type: z.string(),
    display_name: z.string(),
    external_id: z.string(),
    name: z.string(),
    active: z.boolean(),
    last_seen_at: z.union([z.string(), z.number()]).nullable(),
    metadata: z.record(z.string(), z.unknown()).optional(),
    mapping: CustomerMappingSchema.nullable().optional(),
    suggested_matches: z.array(SuggestedMatchSchema).optional(),
})

export const CustomerSchema = z.object({
    customer_id: z.number(),
    canonical_name: z.string(),
    status: z.enum(['active', 'inactive']),
    report_destination: z.string().nullable(),
    report_schedules_count: z.number(),
    integrations: z.array(IntegrationCustomerSchema),
})

export const UnmappedCustomerSchema = z.object({
    integration_customer_id: z.number(),
    integration_id: z.number(),
    integration_type: z.string(),
    display_name: z.string(),
    external_id: z.string(),
    name: z.string(),
    active: z.boolean(),
    last_seen_at: z.number().nullable(),
    suggested_matches: z.array(SuggestedMatchSchema).optional(),
})

export const UnmappedSummarySchema = z.object({
    total_unmapped: z.number(),
    by_integration_type: z.record(z.string(), z.number()),
})

export const MapCustomerRequestSchema = z.object({
    integration_customer_id: z.number(),
    customer_id: z.number().optional(),
    create_new: z.boolean().optional(),
    canonical_name: z.string().optional(),
    status: z.enum(['active', 'inactive']).optional(),
    report_destination: z.string().optional(),
    metadata: z.record(z.string(), z.unknown()).optional(),
})

export const UpdateCustomerRequestSchema = z.object({
    canonical_name: z.string().optional(),
    status: z.enum(['active', 'inactive']).optional(),
    report_destination: z.string().nullable().optional(),
    metadata: z.record(z.string(), z.unknown()).optional(),
})

export const MergeCustomersRequestSchema = z.object({
    merge_customer_id: z.number(),
})

export const SplitCustomerRequestSchema = z.object({
    new_customer_name: z.string(),
    integration_customer_ids: z.array(z.number()),
})

export const IntegrationStatsSchema = z.object({
    tickets: z.object({
        total: z.number(),
        open: z.number(),
        completed: z.number(),
        top_technicians: z.array(z.object({
            name: z.string(),
            ticket_count: z.number(),
        })).optional(),
    }).optional(),
    time_entries: z.object({
        total: z.number(),
        total_hours: z.number(),
        billable_hours: z.number(),
    }).optional(),
    contracts: z.object({
        total: z.number(),
        active: z.number(),
    }).optional(),
})

export type CustomerMapping = z.infer<typeof CustomerMappingSchema>
export type SuggestedMatch = z.infer<typeof SuggestedMatchSchema>
export type IntegrationCustomer = z.infer<typeof IntegrationCustomerSchema>
export type Customer = z.infer<typeof CustomerSchema>
export type UnmappedCustomer = z.infer<typeof UnmappedCustomerSchema>
export type UnmappedSummary = z.infer<typeof UnmappedSummarySchema>
export type MapCustomerRequest = z.infer<typeof MapCustomerRequestSchema>
export type UpdateCustomerRequest = z.infer<typeof UpdateCustomerRequestSchema>
export type MergeCustomersRequest = z.infer<typeof MergeCustomersRequestSchema>
export type SplitCustomerRequest = z.infer<typeof SplitCustomerRequestSchema>
export type IntegrationStats = z.infer<typeof IntegrationStatsSchema>
