/**
 * Progress Indicators
 * Visual feedback for ongoing processes
 */

interface ProgressBarProps {
  progress: number // 0-100
  label?: string
  showPercentage?: boolean
  color?: 'primary' | 'success' | 'warning' | 'error'
  size?: 'sm' | 'md' | 'lg'
}

export function ProgressBar({
  progress,
  label,
  showPercentage = true,
  color = 'primary',
  size = 'md',
}: ProgressBarProps) {
  const colorClasses = {
    primary: 'bg-primary',
    success: 'bg-green-500',
    warning: 'bg-yellow-500',
    error: 'bg-red-500',
  }

  const heightClasses = {
    sm: 'h-1',
    md: 'h-2',
    lg: 'h-3',
  }

  return (
    <div className="w-full">
      {(label || showPercentage) && (
        <div className="flex items-center justify-between mb-2">
          {label && <span className="text-sm font-medium text-foreground">{label}</span>}
          {showPercentage && (
            <span className="text-sm text-muted-foreground">{Math.round(progress)}%</span>
          )}
        </div>
      )}
      <div className={`w-full bg-gray-200 dark:bg-gray-800 rounded-full overflow-hidden ${heightClasses[size]}`}>
        <div
          className={`${colorClasses[color]} ${heightClasses[size]} rounded-full transition-all duration-500 ease-out`}
          style={{ width: `${Math.min(100, Math.max(0, progress))}%` }}
        />
      </div>
    </div>
  )
}

/**
 * Circular Progress Indicator
 */

interface CircularProgressProps {
  progress: number // 0-100
  size?: number
  strokeWidth?: number
  label?: string
}

export function CircularProgress({
  progress,
  size = 120,
  strokeWidth = 8,
  label,
}: CircularProgressProps) {
  const radius = (size - strokeWidth) / 2
  const circumference = radius * 2 * Math.PI
  const offset = circumference - (progress / 100) * circumference

  return (
    <div className="relative inline-flex items-center justify-center">
      <svg width={size} height={size} className="transform -rotate-90">
        {/* Background circle */}
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          stroke="currentColor"
          strokeWidth={strokeWidth}
          fill="none"
          className="text-gray-200 dark:text-gray-800"
        />
        {/* Progress circle */}
        <circle
          cx={size / 2}
          cy={size / 2}
          r={radius}
          stroke="currentColor"
          strokeWidth={strokeWidth}
          fill="none"
          strokeDasharray={circumference}
          strokeDashoffset={offset}
          strokeLinecap="round"
          className="text-primary transition-all duration-500 ease-out"
        />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className="text-2xl font-bold text-foreground">{Math.round(progress)}%</span>
        {label && <span className="text-xs text-muted-foreground mt-1">{label}</span>}
      </div>
    </div>
  )
}

/**
 * Step Progress Indicator
 */

interface Step {
  label: string
  status: 'completed' | 'current' | 'upcoming'
}

interface StepProgressProps {
  steps: Step[]
  orientation?: 'horizontal' | 'vertical'
}

export function StepProgress({ steps, orientation = 'horizontal' }: StepProgressProps) {
  if (orientation === 'vertical') {
    return (
      <div className="space-y-4">
        {steps.map((step, index) => (
          <div key={index} className="flex items-start gap-4">
            <div className="flex flex-col items-center">
              <div
                className={`w-8 h-8 rounded-full flex items-center justify-center font-semibold text-sm transition-colors ${
                  step.status === 'completed'
                    ? 'bg-primary text-primary-foreground'
                    : step.status === 'current'
                    ? 'bg-primary text-primary-foreground ring-4 ring-primary/20'
                    : 'bg-gray-200 dark:bg-gray-800 text-muted-foreground'
                }`}
              >
                {step.status === 'completed' ? '✓' : index + 1}
              </div>
              {index < steps.length - 1 && (
                <div
                  className={`w-0.5 h-12 ${
                    step.status === 'completed' ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-800'
                  }`}
                />
              )}
            </div>
            <div className="flex-1 pt-1">
              <p
                className={`text-sm font-medium ${
                  step.status === 'upcoming' ? 'text-muted-foreground' : 'text-foreground'
                }`}
              >
                {step.label}
              </p>
            </div>
          </div>
        ))}
      </div>
    )
  }

  return (
    <div className="flex items-center justify-between">
      {steps.map((step, index) => (
        <div key={index} className="flex items-center flex-1">
          <div className="flex flex-col items-center flex-1">
            <div
              className={`w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors ${
                step.status === 'completed'
                  ? 'bg-primary text-primary-foreground'
                  : step.status === 'current'
                  ? 'bg-primary text-primary-foreground ring-4 ring-primary/20'
                  : 'bg-gray-200 dark:bg-gray-800 text-muted-foreground'
              }`}
            >
              {step.status === 'completed' ? '✓' : index + 1}
            </div>
            <p
              className={`mt-2 text-xs font-medium ${
                step.status === 'upcoming' ? 'text-muted-foreground' : 'text-foreground'
              }`}
            >
              {step.label}
            </p>
          </div>
          {index < steps.length - 1 && (
            <div
              className={`flex-1 h-0.5 ${
                step.status === 'completed' ? 'bg-primary' : 'bg-gray-200 dark:bg-gray-800'
              }`}
            />
          )}
        </div>
      ))}
    </div>
  )
}

/**
 * Indeterminate Loading Bar (for top of page)
 */

export function LoadingBar({ isLoading }: { isLoading: boolean }) {
  if (!isLoading) return null

  return (
    <div className="fixed top-0 left-0 right-0 z-50 h-1 bg-gray-200 dark:bg-gray-800 overflow-hidden">
      <div className="h-full bg-primary animate-[loading_1.5s_ease-in-out_infinite]" />
      <style>{`
        @keyframes loading {
          0% { transform: translateX(-100%); }
          50% { transform: translateX(0%); }
          100% { transform: translateX(100%); }
        }
      `}</style>
    </div>
  )
}
