import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
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
  Server,
  Globe,
  Monitor,
  Activity,
  Link as LinkIcon,
  FileText,
} from 'lucide-react'

interface DattoCustomerDetailsProps {
  metadata: Record<string, any>
}

const formatValue = (value: any): string => {
  if (!value && value !== 0 && value !== false) return '-'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  return String(value)
}

export default function DattoCustomerDetails({ metadata }: DattoCustomerDetailsProps) {
  const siteFields = [
    { key: 'site_id', label: 'Site ID' },
    { key: 'account_uid', label: 'Account UID' },
    { key: 'source', label: 'Source' },
    { key: 'on_demand', label: 'On Demand' },
    { key: 'description', label: 'Description' },
  ]

  // Device fields are rendered inline in the UI, not in a table
  // const deviceFields = [
  //   { key: 'devices_total', label: 'Total Devices' },
  //   { key: 'devices_online', label: 'Online' },
  //   { key: 'devices_offline', label: 'Offline' },
  // ]

  const autotaskFields = [
    { key: 'autotask_company_id', label: 'Autotask Company ID' },
    { key: 'autotask_company_name', label: 'Autotask Company Name' },
  ]

  const hasDeviceData = metadata.devices_total !== undefined
  const hasAutotaskLink = metadata.autotask_company_id !== undefined

  return (
    <Accordion type="multiple" className="w-full">
      {/* Site Information */}
      <AccordionItem value="site">
        <AccordionTrigger className="hover:no-underline">
          <div className="flex items-center gap-2">
            <Server className="h-4 w-4 text-primary" />
            <span className="font-semibold">Site Information</span>
          </div>
        </AccordionTrigger>
        <AccordionContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-1/3">Field</TableHead>
                <TableHead>Value</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {siteFields.map((field) => {
                const value = metadata[field.key]
                if (value === undefined || value === null || value === '') return null
                return (
                  <TableRow key={field.key}>
                    <TableCell className="font-medium text-muted-foreground">
                      {field.label}
                    </TableCell>
                    <TableCell>{formatValue(value)}</TableCell>
                  </TableRow>
                )
              })}
            </TableBody>
          </Table>
        </AccordionContent>
      </AccordionItem>

      {/* Device Statistics */}
      {hasDeviceData && (
        <AccordionItem value="devices">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <Monitor className="h-4 w-4 text-primary" />
              <span className="font-semibold">Device Statistics</span>
              <Badge variant="secondary" className="ml-2">
                {metadata.devices_total || 0} devices
              </Badge>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <div className="grid grid-cols-3 gap-4">
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-muted-foreground">
                    Total Devices
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold">{metadata.devices_total || 0}</div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-muted-foreground">
                    Online
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-green-600">
                    {metadata.devices_online || 0}
                  </div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-sm font-medium text-muted-foreground">
                    Offline
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="text-2xl font-bold text-red-600">
                    {metadata.devices_offline || 0}
                  </div>
                </CardContent>
              </Card>
            </div>
          </AccordionContent>
        </AccordionItem>
      )}

      {/* Portal Access */}
      {metadata.portal_url && (
        <AccordionItem value="portal">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <Globe className="h-4 w-4 text-primary" />
              <span className="font-semibold">Portal Access</span>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <div className="p-4 bg-muted/50 rounded-lg">
              <a
                href={metadata.portal_url}
                target="_blank"
                rel="noopener noreferrer"
                className="text-primary hover:underline inline-flex items-center gap-2"
              >
                <LinkIcon className="h-4 w-4" />
                {metadata.portal_url}
              </a>
            </div>
          </AccordionContent>
        </AccordionItem>
      )}

      {/* Autotask Integration */}
      {hasAutotaskLink && (
        <AccordionItem value="autotask">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <Activity className="h-4 w-4 text-primary" />
              <span className="font-semibold">Autotask Integration</span>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-1/3">Field</TableHead>
                  <TableHead>Value</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {autotaskFields.map((field) => {
                  const value = metadata[field.key]
                  if (value === undefined || value === null || value === '') return null
                  return (
                    <TableRow key={field.key}>
                      <TableCell className="font-medium text-muted-foreground">
                        {field.label}
                      </TableCell>
                      <TableCell>{formatValue(value)}</TableCell>
                    </TableRow>
                  )
                })}
              </TableBody>
            </Table>
          </AccordionContent>
        </AccordionItem>
      )}

      {/* Notes */}
      {metadata.notes && (
        <AccordionItem value="notes">
          <AccordionTrigger className="hover:no-underline">
            <div className="flex items-center gap-2">
              <FileText className="h-4 w-4 text-primary" />
              <span className="font-semibold">Notes</span>
            </div>
          </AccordionTrigger>
          <AccordionContent>
            <div className="p-4 bg-muted/50 rounded-lg">
              <p className="text-sm whitespace-pre-wrap">{metadata.notes}</p>
            </div>
          </AccordionContent>
        </AccordionItem>
      )}
    </Accordion>
  )
}
