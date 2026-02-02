import { useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { CheckCircle, XCircle, Package, Database, Users, Loader2, ShieldCheck } from 'lucide-react'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'
import { useIntegrationsQuery } from '@/pages/integrations/hooks/useIntegrations'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"

interface CheckResult {
  allowed: boolean
  reason: string | null
}

export function LicenseCheckCard() {
  const toast = useToast()
  const { data: integrationsData, isLoading: loadingIntegrations } = useIntegrationsQuery()
  const [integrationType, setIntegrationType] = useState('datto')
  const [retentionMonths, setRetentionMonths] = useState('36')

  const [integrationResult, setIntegrationResult] = useState<CheckResult | null>(null)
  const [retentionResult, setRetentionResult] = useState<CheckResult | null>(null)
  const [userResult, setUserResult] = useState<{ can_create: CheckResult } | null>(null)

  const [checkingIntegration, setCheckingIntegration] = useState(false)
  const [checkingRetention, setCheckingRetention] = useState(false)
  const [checkingUser, setCheckingUser] = useState(false)

  // Get unique configured integration types or fall back to available types
  const integrationTypes = integrationsData?.availableTypes || ['datto', 'autotask', 'kaseya', 'n-able', 'itglue']

  const checkIntegrationLicense = async () => {
    setCheckingIntegration(true)
    try {
      const response = await api.post('/licenses/check/integration', {
        integration_type: integrationType
      })
      const data = response as { data?: CheckResult } | CheckResult
      const result = (data as { data?: CheckResult }).data || data as CheckResult
      setIntegrationResult(result)
      if (result.allowed) toast.success('Allowed', `${integrationType} integration is within license limits.`)
      else toast.error('Denied', result.reason || 'License limit exceeded.')
    } catch (err) {
      toast.error('Check Failed', 'Unable to verify integration license.')
    } finally {
      setCheckingIntegration(false)
    }
  }

  const checkRetentionLicense = async () => {
    setCheckingRetention(true)
    try {
      const response = await api.post('/licenses/check/retention', {
        requested_months: parseInt(retentionMonths)
      })
      const data = response as { data?: CheckResult } | CheckResult
      const result = (data as { data?: CheckResult }).data || data as CheckResult
      setRetentionResult(result)
      if (result.allowed) toast.success('Allowed', `${retentionMonths} months retention is licensed.`)
      else toast.error('Denied', result.reason || 'Retention limit exceeded.')
    } catch (err) {
      toast.error('Check Failed', 'Unable to verify retention license.')
    } finally {
      setCheckingRetention(false)
    }
  }

  const checkUserLicense = async () => {
    setCheckingUser(true)
    try {
      const response = await api.post('/licenses/check/user', {})
      const data = response as { data?: { can_create: CheckResult } } | { can_create: CheckResult }
      const result = (data as { data?: { can_create: CheckResult } }).data || data as { can_create: CheckResult }
      setUserResult(result)
      if (result.can_create.allowed) toast.success('Allowed', 'User creation is within license limits.')
      else toast.error('Denied', result.can_create.reason || 'User limit reached.')
    } catch (err) {
      toast.error('Check Failed', 'Unable to verify user license.')
    } finally {
      setCheckingUser(false)
    }
  }

  const ResultIndicator = ({ result, label }: { result: CheckResult | null; label: string }) => {
    if (!result) return null
    return (
      <div className={`mt-3 flex items-center gap-2 p-2 rounded-lg border ${
        result.allowed 
          ? 'bg-emerald-500/5 border-emerald-500/20 text-emerald-600 dark:text-emerald-400' 
          : 'bg-destructive/5 border-destructive/20 text-destructive'
      }`}>
        {result.allowed ? <CheckCircle className="h-4 w-4" /> : <XCircle className="h-4 w-4" />}
        <span className="text-xs font-semibold uppercase tracking-wider">{label}: {result.allowed ? 'Allowed' : 'Denied'}</span>
      </div>
    )
  }

  return (
    <Card className="border-border/50 bg-card overflow-hidden">
      <CardHeader className="bg-muted/30 border-b border-border/50 py-3 px-5">
        <CardTitle className="text-sm font-bold text-foreground flex items-center gap-2">
          <ShieldCheck className="h-4 w-4 text-primary" />
          License Validator
        </CardTitle>
      </CardHeader>
      <CardContent className="p-4">
        <div className="flex flex-col gap-6">
          <div className="space-y-3">
            <div className="flex items-center gap-2 text-foreground font-semibold">
              <Package className="h-4 w-4 text-primary" />
              <span>Integration</span>
            </div>
            <div className="space-y-1.5">
              <Label className="text-[10px] uppercase text-muted-foreground font-bold">Package Name</Label>
              <div className="flex gap-2">
                <Select 
                  value={integrationType} 
                  onValueChange={setIntegrationType}
                  disabled={loadingIntegrations}
                >
                  <SelectTrigger className="h-9 text-sm bg-muted/20 border-border/50 focus:ring-primary">
                    <SelectValue placeholder="Select type" />
                  </SelectTrigger>
                  <SelectContent>
                    {integrationTypes.map((type) => (
                      <SelectItem key={type} value={type} className="capitalize">
                        {type.replace(/_/g, ' ')}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <Button
                  onClick={checkIntegrationLicense}
                  disabled={checkingIntegration || !integrationType}
                  className="h-9 px-4 bg-primary hover:bg-primary/90"
                  size="sm"
                >
                  {checkingIntegration ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Check'}
                </Button>
              </div>
              <ResultIndicator result={integrationResult} label="Tier" />
            </div>
          </div>

          <div className="space-y-3">
            <div className="flex items-center gap-2 text-foreground font-semibold">
              <Database className="h-4 w-4 text-primary" />
              <span>Retention</span>
            </div>
            <div className="space-y-1.5">
              <Label className="text-[10px] uppercase text-muted-foreground font-bold">Months Required</Label>
              <div className="flex gap-2">
                <Input
                  type="number"
                  value={retentionMonths}
                  onChange={(e) => setRetentionMonths(e.target.value)}
                  placeholder="36"
                  className="h-9 text-sm bg-muted/20 border-border/50 focus-visible:ring-primary"
                />
                <Button
                  onClick={checkRetentionLicense}
                  disabled={checkingRetention || !retentionMonths}
                  className="h-9 px-4 bg-primary hover:bg-primary/90"
                  size="sm"
                >
                  {checkingRetention ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Check'}
                </Button>
              </div>
              <ResultIndicator result={retentionResult} label="Policy" />
            </div>
          </div>

          <div className="space-y-3">
            <div className="flex items-center gap-2 text-foreground font-semibold">
              <Users className="h-4 w-4 text-primary" />
              <span>Scale Capacity</span>
            </div>
            <div className="space-y-1.5">
              <Label className="text-[10px] uppercase text-muted-foreground font-bold">User Seating</Label>
              <Button
                onClick={checkUserLicense}
                disabled={checkingUser}
                className="w-full h-9 bg-primary hover:bg-primary/90"
                size="sm"
              >
                {checkingUser ? <Loader2 className="h-4 w-4 animate-spin" /> : 'Validate Quota'}
              </Button>
              {userResult && <ResultIndicator result={userResult.can_create} label="Quota" />}
            </div>
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
