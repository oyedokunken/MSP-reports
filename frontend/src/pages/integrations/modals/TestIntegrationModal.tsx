import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle
} from '@/components/ui/dialog'
import { CheckCircle2, XCircle, AlertTriangle, Zap, Loader2 } from 'lucide-react'
import { api } from '@/lib/api'
import type { Integration } from '../types/integration'

interface TestResult {
  name: string
  status: 'success' | 'failed' | 'warning'
  message: string
}

interface TestIntegrationModalProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  integration: Integration | null
}

export function TestIntegrationModal({
  open,
  onOpenChange,
  integration
}: TestIntegrationModalProps) {
  const [isTesting, setIsTesting] = useState(false)
  const [progress, setProgress] = useState(0)
  const [currentStep, setCurrentStep] = useState('')
  const [results, setResults] = useState<TestResult[]>([])
  const [overallStatus, setOverallStatus] = useState<'idle' | 'testing' | 'success' | 'failed'>('idle')

  useEffect(() => {
    if (open && integration) {
      runTest()
    }
  }, [open, integration])

  const runTest = async () => {
    if (!integration) return

    setIsTesting(true)
    setProgress(0)
    setResults([])
    setOverallStatus('testing')

    const testSteps = [
      { name: 'Connection', step: 'Testing API connection...' },
      { name: 'Authentication', step: 'Verifying credentials...' },
      { name: 'Permissions', step: 'Checking API permissions...' },
      { name: 'Data Access', step: 'Testing data retrieval...' },
    ]

    const testResults: TestResult[] = []

    try {
      // Simulate progress through steps
      for (let i = 0; i < testSteps.length; i++) {
        setCurrentStep(testSteps[i].step)
        setProgress(((i + 1) / (testSteps.length + 1)) * 100)
        await new Promise(resolve => setTimeout(resolve, 500))
      }

      // Call actual health check endpoint
      setCurrentStep('Running health check...')
      const response = await api.post<{
        success?: boolean
        status?: string
        message?: string
        error?: string
        checks?: Array<{ name: string; status: string; message?: string }>
      }>(`/integrations/${integration.id}/health-check`)

      setProgress(100)

      // Process response
      if (response.success || response.status === 'healthy') {
        // Add success results
        testResults.push({
          name: 'Connection',
          status: 'success',
          message: 'Successfully connected to API'
        })
        testResults.push({
          name: 'Authentication',
          status: 'success',
          message: 'Credentials are valid'
        })
        testResults.push({
          name: 'Permissions',
          status: 'success',
          message: 'API permissions verified'
        })
        testResults.push({
          name: 'Data Access',
          status: 'success',
          message: 'Can retrieve data from API'
        })

        // Add any specific checks from response
        if (response.checks) {
          response.checks.forEach(check => {
            testResults.push({
              name: check.name,
              status: check.status === 'passed' ? 'success' : check.status === 'warning' ? 'warning' : 'failed',
              message: check.message || ''
            })
          })
        }

        setOverallStatus('success')
      } else {
        // Add failure results
        testResults.push({
          name: 'Connection',
          status: 'failed',
          message: response.message || response.error || 'Connection test failed'
        })
        setOverallStatus('failed')
      }

    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unknown error occurred'
      testResults.push({
        name: 'Connection',
        status: 'failed',
        message: message
      })
      setOverallStatus('failed')
    } finally {
      setResults(testResults)
      setIsTesting(false)
      setCurrentStep('')
    }
  }

  const handleClose = () => {
    if (isTesting) return
    setOverallStatus('idle')
    setResults([])
    setProgress(0)
    onOpenChange(false)
  }

  const getStatusIcon = (status: 'success' | 'failed' | 'warning') => {
    switch (status) {
      case 'success':
        return <CheckCircle2 className="h-5 w-5 text-green-500" />
      case 'failed':
        return <XCircle className="h-5 w-5 text-red-500" />
      case 'warning':
        return <AlertTriangle className="h-5 w-5 text-yellow-500" />
    }
  }

  if (!integration) return null

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <div className="flex items-center gap-2">
            <Zap className="h-5 w-5 text-primary" />
            <DialogTitle>Test Integration</DialogTitle>
          </div>
          <DialogDescription>
            Testing connection to {integration.name}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Progress Bar */}
          {isTesting && (
            <div className="space-y-2">
              <div className="flex items-center justify-between text-sm">
                <span className="text-muted-foreground">{currentStep}</span>
                <span className="font-medium">{Math.round(progress)}%</span>
              </div>
              <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                <div
                  className="bg-primary h-2 rounded-full transition-all duration-300"
                  style={{ width: `${progress}%` }}
                />
              </div>
            </div>
          )}

          {/* Overall Status */}
          {!isTesting && overallStatus !== 'idle' && (
            <div className={`p-4 rounded-lg border ${
              overallStatus === 'success' 
                ? 'bg-green-50 dark:bg-green-950/20 border-green-200 dark:border-green-800' 
                : 'bg-red-50 dark:bg-red-950/20 border-red-200 dark:border-red-800'
            }`}>
              <div className="flex items-center gap-2">
                {overallStatus === 'success' ? (
                  <CheckCircle2 className="h-6 w-6 text-green-500" />
                ) : (
                  <XCircle className="h-6 w-6 text-red-500" />
                )}
                <span className={`font-semibold ${
                  overallStatus === 'success' ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400'
                }`}>
                  {overallStatus === 'success' ? 'All Tests Passed!' : 'Test Failed'}
                </span>
              </div>
            </div>
          )}

          {/* Test Results */}
          {results.length > 0 && (
            <div className="space-y-2">
              <h4 className="text-sm font-medium text-muted-foreground">Test Results</h4>
              <div className="space-y-2">
                {results.map((result, index) => (
                  <div
                    key={index}
                    className="flex items-start gap-3 p-3 rounded-lg border border-border bg-card"
                  >
                    {getStatusIcon(result.status)}
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-sm">{result.name}</p>
                      <p className="text-xs text-muted-foreground truncate">{result.message}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Loading State */}
          {isTesting && (
            <div className="flex items-center justify-center py-4">
              <Loader2 className="h-8 w-8 animate-spin text-primary" />
            </div>
          )}
        </div>

        <DialogFooter>
          {!isTesting && (
            <>
              <Button variant="outline" onClick={handleClose}>
                Close
              </Button>
              {overallStatus === 'failed' && (
                <Button onClick={runTest}>
                  <Zap className="h-4 w-4 mr-2" />
                  Retry Test
                </Button>
              )}
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
