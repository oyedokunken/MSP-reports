import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/components/ui/accordion'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'

import {
  Building2,
  MapPin,
  Phone,
  Globe,
  FileText,
  Link as LinkIcon,
  Calendar,
} from 'lucide-react'

interface ITGlueCustomerDetailsProps {
  metadata: Record<string, unknown>
}

const formatValue = (value: unknown): string => {
  if (!value && value !== 0 && value !== false) return '-'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (value instanceof Date || (typeof value === 'string' && value.includes('T'))) {
    try {
      return new Date(value).toLocaleDateString()
    } catch {
      return String(value)
    }
  }
  return String(value)
}

interface MetadataTableProps {
  data: Record<string, unknown>
  fields: { key: string; label: string; link?: boolean }[]
}

const MetadataTable = ({ data, fields }: MetadataTableProps) => {
  const visibleFields = fields.filter(
    (field) => data[field.key] !== undefined && data[field.key] !== null && data[field.key] !== ''
  )

  if (visibleFields.length === 0) {
    return <p className="text-sm text-muted-foreground text-center py-4">No data available</p>
  }

  return (
    <Table>
      <TableHeader>
        <TableRow>
          <TableHead className="w-1/3">Field</TableHead>
          <TableHead>Value</TableHead>
        </TableRow>
      </TableHeader>
      <TableBody>
        {visibleFields.map((field) => (
          <TableRow key={field.key}>
            <TableCell className="font-medium text-muted-foreground">{field.label}</TableCell>
            <TableCell>
              {field.link && data[field.key] ? (
                <a
                  href={
                    String(data[field.key]).startsWith('http')
                      ? String(data[field.key])
                      : `https://${String(data[field.key])}`
                  }
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-primary hover:underline inline-flex items-center gap-1"
                >
                  {String(data[field.key])}
                  <LinkIcon className="h-3 w-3" />
                </a>
              ) : (
                formatValue(data[field.key])
              )}
            </TableCell>
          </TableRow>
        ))}
      </TableBody>
    </Table>
  )
}

export default function ITGlueCustomerDetails({ metadata }: ITGlueCustomerDetailsProps) {
  const organizationFields = [
    { key: 'short_name', label: 'Short Name' },
    { key: 'organization_type_name', label: 'Organization Type' },
    { key: 'organization_status_name', label: 'Status' },
    { key: 'is_primary', label: 'Primary Organization' },
    { key: 'description', label: 'Description' },
  ]

  const contactFields = [
    { key: 'phone', label: 'Phone' },
    { key: 'fax', label: 'Fax' },
    { key: 'website', label: 'Website', link: true },
  ]

  const addressFields = [
    { key: 'address', label: 'Address' },
    { key: 'city', label: 'City' },
    { key: 'region', label: 'Region/State' },
    { key: 'country', label: 'Country' },
    { key: 'postal_code', label: 'Postal Code' },
  ]

  const systemFields = [
    { key: 'itglue_id', label: 'IT Glue ID' },
    { key: 'parent_id', label: 'Parent Organization ID' },
    { key: 'group_id', label: 'Group ID' },
    { key: 'psa_id', label: 'PSA ID' },
    { key: 'psa_integration_type', label: 'PSA Type' },
  ]

  const datesFields = [
    { key: 'created_at', label: 'Created Date' },
    { key: 'updated_at', label: 'Last Updated' },
  ]

  const hasContactInfo = contactFields.some((field) => metadata[field.key])
  const hasAddressInfo = addressFields.some((field) => metadata[field.key])
  const hasSystemInfo = systemFields.some((field) => metadata[field.key])
  const hasDates = datesFields.some((field) => metadata[field.key])
  const hasQuickNotes = metadata.quick_notes !== undefined
  const hasAlert = metadata.alert !== undefined
  const hasLogoUrl = metadata.logo_url !== undefined
  const hasNotesOrAlerts = hasQuickNotes || hasAlert

  return (
    <Accordion type="multiple" className="w-full">
      <AccordionItem value="organization">
        <AccordionTrigger className="hover:no-underline">
          <div className="flex items-center gap-2">
            <Building2 className="h-4 w-4 text-primary" />
            <span className="font-semibold">Organization Information</span>
          </div>
        </AccordionTrigger>
        <AccordionContent>
          <MetadataTable data={metadata} fields={organizationFields} />
        </AccordionContent>
      </AccordionItem>

      {hasContactInfo && (
        <AccordionItem value="contact">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <Phone className="h-4 w-4 text-primary" />
              <span className="font-semibold">Contact Information</span>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <MetadataTable data={metadata} fields={contactFields} />
          </AccordionContent>
        </AccordionItem>
      )}

      {hasAddressInfo && (
        <AccordionItem value="address">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <MapPin className="h-4 w-4 text-primary" />
              <span className="font-semibold">Address</span>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <MetadataTable data={metadata} fields={addressFields} />
          </AccordionContent>
        </AccordionItem>
      )}

      {hasSystemInfo && (
        <AccordionItem value="system">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <Globe className="h-4 w-4 text-primary" />
              <span className="font-semibold">System Information</span>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <MetadataTable data={metadata} fields={systemFields} />
          </AccordionContent>
        </AccordionItem>
      )}

      {hasDates && (
        <AccordionItem value="dates">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <Calendar className="h-4 w-4 text-primary" />
              <span className="font-semibold">Important Dates</span>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <MetadataTable data={metadata} fields={datesFields} />
          </AccordionContent>
        </AccordionItem>
      )}

      {hasNotesOrAlerts && (
        <AccordionItem value="notes">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <FileText className="h-4 w-4 text-primary" />
              <span className="font-semibold">Notes & Alerts</span>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <div className="space-y-4">
              {hasQuickNotes && (
                <div>
                  <h4 className="text-sm font-medium mb-2">Quick Notes</h4>
                  <div className="p-4 bg-muted/50 rounded-lg">
                    <p className="text-sm whitespace-pre-wrap">{String(metadata.quick_notes)}</p>
                  </div>
                </div>
              )}
              {hasAlert && (
                <div>
                  <h4 className="text-sm font-medium mb-2">Alert</h4>
                  <div className="p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                    <p className="text-sm text-amber-900 dark:text-amber-100 whitespace-pre-wrap">
                      {String(metadata.alert)}
                    </p>
                  </div>
                </div>
              )}
            </div>
          </AccordionContent>
        </AccordionItem>
      )}

      {hasLogoUrl && (
        <AccordionItem value="logo">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <Globe className="h-4 w-4 text-primary" />
              <span className="font-semibold">Organization Logo</span>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <div className="p-4 bg-muted/50 rounded-lg flex items-center justify-center">
              <img
                src={String(metadata.logo_url)}
                alt="Organization Logo"
                className="max-h-32 max-w-full object-contain"
                onError={(e) => {
                  e.currentTarget.style.display = 'none'
                }}
              />
            </div>
          </AccordionContent>
        </AccordionItem>
      )}
    </Accordion>
  )
}
