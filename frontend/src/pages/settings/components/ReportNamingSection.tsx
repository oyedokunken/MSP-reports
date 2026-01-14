import { useState, useEffect, useRef, useCallback } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Loader2, Save, Info } from 'lucide-react'
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
            console.error('Failed to load naming settings:', error)
        } finally {
            setLoading(false)
        }
    }, [])

    useEffect(() => {
        fetchSettings()
    }, [fetchSettings])

    // Update preview when pattern changes
    useEffect(() => {
        // Simple local preview
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

        // Set cursor after inserted token
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
            console.error('Failed to save:', error)
            const errMsg = error instanceof Error ? error.message : 'Failed to save settings'
            toast.error('Error', errMsg)
        } finally {
            setSaving(false)
        }
    }

    if (loading) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>Report Naming</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center justify-center py-8">
                        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                    </div>
                </CardContent>
            </Card>
        )
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Report Naming Convention</CardTitle>
                <CardDescription>
                    Customize how generated report files are named
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
                {/* Pattern Input */}
                <div className="space-y-2">
                    <label className="text-sm font-medium">Naming Pattern</label>
                    <Input
                        ref={inputRef}
                        value={pattern}
                        onChange={(e) => setPattern(e.target.value)}
                        placeholder="{customer_name}_{report_type}_{date}"
                    />
                </div>

                {/* Token Badges */}
                <div className="space-y-2">
                    <label className="text-sm font-medium text-muted-foreground flex items-center gap-1">
                        <Info className="h-3.5 w-3.5" />
                        Click to insert token
                    </label>
                    <div className="flex flex-wrap gap-2">
                        {AVAILABLE_TOKENS.map((token) => (
                            <Badge
                                key={token.key}
                                variant="secondary"
                                className="cursor-pointer hover:bg-primary hover:text-primary-foreground transition-colors"
                                onClick={() => insertToken(token.key)}
                            >
                                {token.key}
                            </Badge>
                        ))}
                    </div>
                </div>

                {/* Preview */}
                <div className="rounded-lg border bg-muted/50 p-4">
                    <div className="text-sm font-medium text-muted-foreground mb-1">Preview</div>
                    <code className="text-sm font-mono break-all">{preview}</code>
                </div>

                {/* Save Button */}
                <div className="flex justify-end">
                    <Button onClick={handleSave} disabled={saving}>
                        {saving ? (
                            <>
                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                Saving...
                            </>
                        ) : (
                            <>
                                <Save className="mr-2 h-4 w-4" />
                                Save Pattern
                            </>
                        )}
                    </Button>
                </div>
            </CardContent>
        </Card>
    )
}
