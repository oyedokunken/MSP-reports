import { useState, useEffect, useRef, useCallback } from 'react'
import { logger } from '@/lib/logger'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Loader2, Info } from 'lucide-react'
import { api } from '@/lib/api'
import { useToast } from '@/components/ui/Toast'
import { useAuth } from '@/contexts/AuthContext'
import { hasPermission } from '@/utils/permissions'

interface TokenDef {
    key: string
    description: string
}

const AVAILABLE_TOKENS: TokenDef[] = [
    { key: '{customer_name}', description: 'Customer name' },
    { key: '{report_type}', description: 'Report type' },
    { key: '{date}', description: 'Date (YYYY-MM-DD)' },
    { key: '{month}', description: 'Month name' },
    { key: '{year}', description: '4-digit year' },
    { key: '{frequency}', description: 'Frequency' },
    { key: '{timestamp}', description: 'Full timestamp' },
]

export default function ReportNamingSection() {
    const toast = useToast()
    const { user } = useAuth()
    const [loading, setLoading] = useState(true)
    const [saving, setSaving] = useState(false)
    const [pattern, setPattern] = useState('{customer_name}_{report_type}_{date}')
    const [preview, setPreview] = useState('')
    const inputRef = useRef<HTMLInputElement>(null)

    const fetchSettings = useCallback(async () => {
        try {
            setLoading(true)
            const response = await api.get('/company/report-naming') as { data?: { naming_pattern?: string; preview?: string } }
            if (response.data) {
                setPattern(response.data.naming_pattern || '{customer_name}_{report_type}_{date}')
                setPreview(response.data.preview || '')
            }
        } catch (error) {
            logger.error('Failed to load naming settings:', error)
        } finally {
            setLoading(false)
        }
    }, [])

    useEffect(() => {
        fetchSettings()
    }, [fetchSettings])

    useEffect(() => {
        const tokens: Record<string, string> = {
            '{customer_name}': 'Acme_Corp',
            '{report_type}': 'monthly-summary',
            '{date}': new Date().toISOString().split('T')[0],
            '{month}': new Date().toLocaleString('default', { month: 'long' }),
            '{year}': new Date().getFullYear().toString(),
            '{frequency}': 'monthly',
            '{timestamp}': new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19),
        }

        let result = pattern
        for (const [key, value] of Object.entries(tokens)) {
            result = result.replace(new RegExp(key.replace(/[{}]/g, '\\$&'), 'g'), value)
        }
        setPreview(result.replace(/[<>:"/\\|?*]/g, '').replace(/ /g, '_') + '.pdf')
    }, [pattern])

    const insertToken = (token: string) => {
        if (!inputRef.current) return

        const input = inputRef.current
        const start = input.selectionStart || 0
        const end = input.selectionEnd || 0
        const newValue = pattern.slice(0, start) + token + pattern.slice(end)
        setPattern(newValue)

        setTimeout(() => {
            input.focus()
            const newPos = start + token.length
            input.setSelectionRange(newPos, newPos)
        }, 0)
    }

    const handleSave = async () => {
        if (!hasPermission(user, 'company_settings', 'edit')) {
            toast.error('Permission Denied', 'You don\'t have permission to edit settings')
            return
        }

        try {
            setSaving(true)
            await api.patch('/company/report-naming', { naming_pattern: pattern })
            toast.success('Saved', 'Report naming pattern updated')
        } catch (error) {
            logger.error('Failed to save:', error)
            const errMsg = error instanceof Error ? error.message : 'Failed to save settings'
            toast.error('Error', errMsg)
        } finally {
            setSaving(false)
        }
    }

    if (loading) {
        return (
            <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
                <div className="flex items-center justify-center py-8">
                    <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                </div>
            </section>
        )
    }

    return (
        <section className="rounded-lg border border-gray-200 dark:border-0 bg-white dark:bg-[hsl(var(--auth-card))] p-5 shadow-sm">
            <div className="flex items-start border-b pb-2 justify-between">
                <div>
                    <h2 className="text-base font-semibold text-foreground">Report Naming Configuration</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Customize how generated report files are named</p>
                </div>
                <Button onClick={handleSave} disabled={saving} size="sm">
                    {saving ? 'Saving...' : 'Save Pattern'}
                </Button>
            </div>

            <div className="mt-8 relative">
                {/* Continuous Vertical Divider (Desktop Only) */}
                <div className="absolute left-[33.333333%] top-0 bottom-0 w-px bg-border hidden md:block" />

                <div className="space-y-0">
                    {/* Naming Pattern Row */}
                    <div className="grid grid-cols-1 md:grid-cols-12 items-start">
                        <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
                            <label className="text-sm font-semibold text-foreground">Naming Pattern</label>
                            <p className="text-xs text-muted-foreground mt-1">Define the structure of generated report filenames.</p>
                        </div>
                        <div className="md:col-span-8 p-4 md:p-4 md:pl-6 space-y-4">
                            <Input
                                ref={inputRef}
                                value={pattern}
                                onChange={(e) => setPattern(e.target.value)}
                                placeholder="{customer_name}_{report_type}_{date}"
                                className="w-full"
                            />

                            <div className="space-y-2">
                                <label className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Info className="h-3 w-3" />
                                    Click to insert dynamic tokens
                                </label>
                                <div className="flex flex-wrap gap-2">
                                    {AVAILABLE_TOKENS.map((token) => (
                                        <Badge
                                            key={token.key}
                                            variant="secondary"
                                            className="cursor-pointer hover:bg-primary hover:text-primary-foreground transition-colors text-[10px] py-0 px-2 h-5"
                                            onClick={() => insertToken(token.key)}
                                        >
                                            {token.key}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Preview Row */}
                    <div className="grid grid-cols-1 md:grid-cols-12 items-start">
                        <div className="md:col-span-4 p-4 md:py-4 md:pr-6">
                            <label className="text-sm font-semibold text-foreground">Live Preview</label>
                            <p className="text-xs text-muted-foreground mt-1">See how a sample filename would look with your current pattern.</p>
                        </div>
                        <div className="md:col-span-8 p-4 md:p-4 md:pl-6">
                            <div className="rounded-md border bg-muted/30 p-3">
                                <code className="text-xs font-mono break-all text-primary/80">{preview}</code>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    )
}
