import { getIntegrationIconConfig } from '../utils/integrationIcons'

export function IntegrationIcon({ type, size = 'md' }: { type: string; size?: 'sm' | 'md' | 'lg' }) {
    const config = getIntegrationIconConfig(type)
    const sizeClasses = {
        sm: 'h-6 w-6',
        md: 'h-8 w-8',
        lg: 'h-10 w-10',
    }

    return (
        <div className={`${config.bgColor} ${config.color} rounded-lg p-2 flex items-center justify-center`}>
            <div className={sizeClasses[size]}>
                {config.icon}
            </div>
        </div>
    )
}
