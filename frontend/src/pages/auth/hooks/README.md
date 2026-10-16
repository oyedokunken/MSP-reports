# Auth Hooks

Custom React hooks for authentication functionality.

## `useAuthForm`

A custom hook that handles authentication form state and submission logic.

### Usage

```tsx
import { useAuthForm } from '@/pages/auth/hooks'

function LoginPage() {
  const {
    email,
    password,
    error,
    isSubmitting,
    setEmail,
    setPassword,
    handleSubmit,
    resetForm,
  } = useAuthForm({
    redirectTo: '/dashboard',
    onSuccess: () => console.log('Login successful!'),
    onError: (err) => console.error('Login failed:', err)
  })

  return (
    <form onSubmit={handleSubmit}>
      <input 
        type="email" 
        value={email} 
        onChange={(e) => setEmail(e.target.value)} 
      />
      <input 
        type="password" 
        value={password} 
        onChange={(e) => setPassword(e.target.value)} 
      />
      {error && <div>{error}</div>}
      <button type="submit" disabled={isSubmitting}>
        {isSubmitting ? 'Signing in...' : 'Sign in'}
      </button>
    </form>
  )
}
```

### Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `redirectTo` | `string` | `'/dashboard'` | Route to navigate to after successful login |
| `onSuccess` | `() => void` | `undefined` | Callback fired after successful login |
| `onError` | `(error: Error) => void` | `undefined` | Callback fired when login fails |

### Return Values

| Property | Type | Description |
|----------|------|-------------|
| `email` | `string` | Current email value |
| `password` | `string` | Current password value |
| `error` | `string` | Error message (empty if no error) |
| `isSubmitting` | `boolean` | Whether form is currently submitting |
| `setEmail` | `(email: string) => void` | Update email value |
| `setPassword` | `(password: string) => void` | Update password value |
| `setError` | `(error: string) => void` | Manually set error message |
| `handleSubmit` | `(e: FormEvent) => Promise<void>` | Form submit handler |
| `resetForm` | `() => void` | Reset all form fields to initial state |

### Features

- Built-in form validation
- Loading state management
- Error handling with user-friendly messages
- Automatic navigation on success
- Success/error callbacks
- Form reset functionality
- TypeScript support
