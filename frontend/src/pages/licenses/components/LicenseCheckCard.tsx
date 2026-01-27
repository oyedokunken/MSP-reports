import { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { logger } from '@/lib/logger'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { CheckCircle, XCircle, Package, Database, Users, Loader2 } from 'lucide-react'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'

interface CheckResult {
  allowed: boolean
  reason: string | null
}

export function LicenseCheckCard() {
  const toast = useToast()
  const [integrationType, setIntegrationType] = useState('datto')
  const [retentionMonths, setRetentionMonths] = useState('36')

  const [integrationResult, setIntegrationResult] = useState<CheckResult | null>(null)
  const [retentionResult, setRetentionResult] = useState<CheckResult | null>(null)
  const [userResult, setUserResult] = useState<{ can_create: CheckResult } | null>(null)

  const [checkingIntegration, setCheckingIntegration] = useState(false)
  const [checkingRetention, setCheckingRetention] = useState(false)
  const [checkingUser, setCheckingUser] = useState(false)

  const checkIntegrationLicense = async () => {
    setCheckingIntegration(true)
    try {
      const response = await api.post('/licenses/check/integration', {
        integration_type: integrationType
      })
      const data = response as { data?: CheckResult } | CheckResult
      const result = (data as { data?: CheckResult }).data || data as CheckResult

      if (!result || typeof result.allowed === 'undefined') {
        throw new Error('Invalid response from server')
      }

      setIntegrationResult(result)

      if (result.allowed) {
        toast.success('License Check', `${integrationType} integration can be activated`)
      } else {
        toast.error('License Check Failed', result.reason || 'Cannot activate integration')
      }
    } catch (err: unknown) {
      logger.error('Integration license check error:', err)
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Check Failed', error.response?.data?.message || error.message)
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

      if (!result || typeof result.allowed === 'undefined') {
        throw new Error('Invalid response from server')
      }

      setRetentionResult(result)

      if (result.allowed) {
        toast.success('License Check', `${retentionMonths} months retention is allowed`)
      } else {
        toast.error('License Check Failed', result.reason || 'Cannot extend retention')
      }
    } catch (err: unknown) {
      logger.error('Retention license check error:', err)
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Check Failed', error.response?.data?.message || error.message)
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

      if (!result || !result.can_create) {
        throw new Error('Invalid response from server')
      }

      setUserResult(result)

      if (result.can_create.allowed) {
        toast.success('License Check', 'New user can be created')
      } else {
        toast.error('License Check Failed', result.can_create.reason || 'Cannot create user')
      }
    } catch (err: unknown) {
      logger.error('User license check error:', err)
      const error = err as { response?: { data?: { message?: string } }; message?: string }
      toast.error('Check Failed', error.response?.data?.message || error.message)
    } finally {
      setCheckingUser(false)
    }
  }

  const ResultBadge = ({ result }: { result: CheckResult | null }) => {
    if (!result) return null

    return (
      <div className="flex items-start gap-2 mt-2 p-2 rounded-md bg-muted/30">
        {result.allowed ? (
          <CheckCircle className="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
        ) : (
          <XCircle className="h-4 w-4 text-red-500 mt-0.5 flex-shrink-0" />
        )}
        <div className="flex-1 min-w-0">
          <Badge 
            variant={result.allowed ? "outline" : "destructive"} 
            className={result.allowed ? "text-sm border-green-500/50 text-green-600 dark:text-green-400" : "text-sm"}
          >
            {result.allowed ? 'Allowed' : 'Not Allowed'}
          </Badge>
          {result.reason && (
            <p className="text-sm text-muted-foreground mt-1">{result.reason}</p>
          )}
        </div>
      </div>
    )
  }

  return (
    <Card className="border-border/50">
      <CardHeader className="pb-4">
        <CardTitle className="text-lg font-medium">License Checks</CardTitle>
        <CardDescription className="text-sm">
          Test license permissions for integrations, retention, and users
        </CardDescription>
      </CardHeader>
      <CardContent className="pt-0">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {/* Integration Check */}
          <div className="space-y-4">
            <div className="flex items-center gap-2">
              <div className="p-1.5 rounded-md bg-primary/10">
                <Package className="h-4 w-4 text-primary" />
              </div>
              <h3 className="text-base font-medium">Integration</h3>
            </div>
            <div className="space-y-2">
              <Label htmlFor="integration-type" className="text-sm text-muted-foreground">Integration Type</Label>
              <Input
                id="integration-type"
                value={integrationType}
                onChange={(e) => setIntegrationType(e.target.value)}
                placeholder="e.g., datto, autotask"
                className="h-9 text-sm"
              />
            </div>
            <Button
              onClick={checkIntegrationLicense}
              disabled={checkingIntegration || !integrationType}
              className="w-full h-9"
              size="sm"
            >
              {checkingIntegration ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Checking...
                </>
              ) : (
                'Check License'
              )}
            </Button>
            <ResultBadge result={integrationResult} />
          </div>

          {/* Retention Check */}
          <div className="space-y-4">
            <div className="flex items-center gap-2">
              <div className="p-1.5 rounded-md bg-primary/10">
                <Database className="h-4 w-4 text-primary" />
              </div>
              <h3 className="text-base font-medium">Retention</h3>
            </div>
            <div className="space-y-2">
              <Label htmlFor="retention-months" className="text-sm text-muted-foreground">Months</Label>
              <Input
                id="retention-months"
                type="number"
                value={retentionMonths}
                onChange={(e) => setRetentionMonths(e.target.value)}
                placeholder="e.g., 36"
                min="1"
                className="h-9 text-sm"
              />
            </div>
            <Button
              onClick={checkRetentionLicense}
              disabled={checkingRetention || !retentionMonths}
              className="w-full h-9"
              size="sm"
            >
              {checkingRetention ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Checking...
                </>
              ) : (
                'Check License'
              )}
            </Button>
            <ResultBadge result={retentionResult} />
          </div>

          {/* User Check */}
          <div className="space-y-4">
            <div className="flex items-center gap-2">
              <div className="p-1.5 rounded-md bg-primary/10">
                <Users className="h-4 w-4 text-primary" />
              </div>
              <h3 className="text-base font-medium">User Creation</h3>
            </div>
            <div className="space-y-1">
              <Label className="text-sm text-muted-foreground">Check user creation permission</Label>
              <p className="text-sm text-muted-foreground/70">
                Validates against current license quota
              </p>
            </div>
            <Button
              onClick={checkUserLicense}
              disabled={checkingUser}
              className="w-full h-9"
              size="sm"
            >
              {checkingUser ? (
                <>
                  <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                  Checking...
                </>
              ) : (
                'Check License'
              )}
            </Button>
            {userResult && (
              <div className="flex items-start gap-2 mt-2 p-2 rounded-md bg-muted/30">
                {userResult.can_create.allowed ? (
                  <CheckCircle className="h-4 w-4 text-green-500 mt-0.5 flex-shrink-0" />
                ) : (
                  <XCircle className="h-4 w-4 text-red-500 mt-0.5 flex-shrink-0" />
                )}
                <div className="flex-1 min-w-0">
                  <Badge 
                    variant={userResult.can_create.allowed ? "outline" : "destructive"}
                    className={userResult.can_create.allowed ? "text-sm border-green-500/50 text-green-600 dark:text-green-400" : "text-sm"}
                  >
                    {userResult.can_create.allowed ? 'Can Create' : 'Cannot Create'}
                  </Badge>
                  {userResult.can_create.reason && (
                    <p className="text-sm text-muted-foreground mt-1">
                      {userResult.can_create.reason}
                    </p>
                  )}
                </div>
              </div>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}
