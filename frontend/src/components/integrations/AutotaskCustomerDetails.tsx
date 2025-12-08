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
  Phone,
  MapPin,
  Building2,
  Calendar,
  Settings,
  Mail,
  ExternalLink,
} from 'lucide-react'

interface AutotaskCustomerDetailsProps {
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
                  <ExternalLink className="h-3 w-3" />
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

export default function AutotaskCustomerDetails({ metadata }: AutotaskCustomerDetailsProps) {
  const contactFields = [
    { key: 'phone', label: 'Phone' },
    { key: 'fax', label: 'Fax' },
    { key: 'alternate_phone1', label: 'Alternate Phone 1' },
    { key: 'alternate_phone2', label: 'Alternate Phone 2' },
    { key: 'website', label: 'Website', link: true },
  ]

  const addressFields = [
    { key: 'address1', label: 'Address 1' },
    { key: 'address2', label: 'Address 2' },
    { key: 'city', label: 'City' },
    { key: 'state', label: 'State' },
    { key: 'postal_code', label: 'Postal Code' },
    { key: 'country_id', label: 'Country ID' },
    { key: 'additional_address_info', label: 'Additional Info' },
  ]

  const billingAddressFields = [
    { key: 'billing_address1', label: 'Billing Address 1' },
    { key: 'billing_address2', label: 'Billing Address 2' },
    { key: 'bill_to_city', label: 'City' },
    { key: 'bill_to_state', label: 'State' },
    { key: 'bill_to_zip_code', label: 'ZIP Code' },
    { key: 'bill_to_country_id', label: 'Country ID' },
    { key: 'bill_to_attention', label: 'Attention' },
    { key: 'bill_to_additional_address_info', label: 'Additional Info' },
  ]

  const companyFields = [
    { key: 'company_number', label: 'Company Number' },
    { key: 'company_type', label: 'Company Type' },
    { key: 'classification', label: 'Classification' },
    { key: 'sic_code', label: 'SIC Code' },
    { key: 'tax_id', label: 'Tax ID' },
    { key: 'is_tax_exempt', label: 'Tax Exempt' },
    { key: 'stock_symbol', label: 'Stock Symbol' },
    { key: 'stock_market', label: 'Stock Market' },
    { key: 'asset_value', label: 'Asset Value' },
    { key: 'currency_id', label: 'Currency ID' },
  ]

  const datesFields = [
    { key: 'create_date', label: 'Created Date' },
    { key: 'last_activity_date', label: 'Last Activity' },
    { key: 'last_tracked_modified_date_time', label: 'Last Modified' },
  ]

  const systemFields = [
    { key: 'is_client_portal_active', label: 'Client Portal Active' },
    { key: 'is_task_fire_active', label: 'Task Fire Active' },
    { key: 'is_enabled_for_comanaged', label: 'Comanaged Enabled' },
    { key: 'owner_resource_id', label: 'Owner Resource ID' },
    { key: 'created_by_resource_id', label: 'Created By Resource ID' },
    { key: 'territory_id', label: 'Territory ID' },
    { key: 'parent_company_id', label: 'Parent Company ID' },
    { key: 'api_vendor_id', label: 'API Vendor ID' },
  ]

  return (
    <Accordion type="multiple" className="w-full">
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

      <AccordionItem value="address">
        <AccordionTrigger className="hover:no-underline">
          <div className="flex items-center gap-2">
            <MapPin className="h-4 w-4 text-primary" />
            <span className="font-semibold">Primary Address</span>
          </div>
        </AccordionTrigger>
        <AccordionContent>
          <MetadataTable data={metadata} fields={addressFields} />
        </AccordionContent>
      </AccordionItem>

      <AccordionItem value="billing">
        <AccordionTrigger className="hover:no-underline">
          <div className="flex items-center gap-2">
            <Mail className="h-4 w-4 text-primary" />
            <span className="font-semibold">Billing Address</span>
          </div>
        </AccordionTrigger>
        <AccordionContent>
          <MetadataTable data={metadata} fields={billingAddressFields} />
        </AccordionContent>
      </AccordionItem>

      <AccordionItem value="company">
        <AccordionTrigger className="hover:no-underline">
          <div className="flex items-center gap-2">
            <Building2 className="h-4 w-4 text-primary" />
            <span className="font-semibold">Company Information</span>
          </div>
        </AccordionTrigger>
        <AccordionContent>
          <MetadataTable data={metadata} fields={companyFields} />
        </AccordionContent>
      </AccordionItem>

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

      <AccordionItem value="system">
        <AccordionTrigger className="hover:no-underline">
          <div className="flex items-center gap-2">
            <Settings className="h-4 w-4 text-primary" />
            <span className="font-semibold">System Information</span>
          </div>
        </AccordionTrigger>
        <AccordionContent>
          <MetadataTable data={metadata} fields={systemFields} />
        </AccordionContent>
      </AccordionItem>
    </Accordion>
  )
}
