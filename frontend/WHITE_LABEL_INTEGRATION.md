# White-Label Branding Integration - Frontend

## ✅ Implementation Complete

The white-label branding system is now integrated into the frontend UI. It dynamically applies company branding from the backend without requiring any backend changes.

---

## **What Was Implemented**

### **1. Type Definitions** (`src/types/auth.ts`)
Added TypeScript interfaces for:
- `Company` - Company data structure
- `CompanySettings` - Company settings including white_label
- `WhiteLabelSettings` - All branding fields
- Updated `LoginResponse` to include `company` data

### **2. BrandingContext** (`src/contexts/BrandingContext.tsx`)
Created a global branding context that:
- ✅ Stores branding settings in state
- ✅ Persists branding to localStorage
- ✅ Applies branding dynamically:
  - CSS variables for colors (`--brand-primary`, `--brand-secondary`)
  - Page title (company name)
  - Favicon (if provided)
  - Meta theme color
- ✅ Provides `useBranding()` hook for components

### **3. AuthContext Updates** (`src/contexts/AuthContext.tsx`)
Enhanced authentication to:
- ✅ Store `company` data from login response
- ✅ Automatically set branding on login
- ✅ Clear branding on logout
- ✅ Persist company data to localStorage

### **4. App.tsx Updates**
- ✅ Wrapped app with `BrandingProvider`
- ✅ Proper provider hierarchy: Theme → Toast → Branding → Auth

### **5. CSS Variables** (`src/index.css`)
- ✅ Added `--brand-primary` and `--brand-secondary` CSS variables
- ✅ Can be overridden dynamically by JavaScript

---

## **How It Works**

### **Login Flow**
```
1. User logs in
   ↓
2. Backend returns:
   {
     user: {...},
     company: {
       settings: {
         white_label: {
           company_name: "TTS",
           primary_color: "#1a56db",
           secondary_color: "#7c3aed",
           logo_url: "...",
           ...
         }
       }
     },
     token: "..."
   }
   ↓
3. AuthContext stores company data
   ↓
4. BrandingContext applies branding:
   - Sets CSS variables
   - Updates page title
   - Sets favicon
   - Stores in localStorage
```

### **Branding Application**
The `BrandingContext` automatically applies branding whenever it changes:

```typescript
const applyBranding = () => {
  const root = document.documentElement
  root.style.setProperty('--brand-primary', branding.primary_color)
  root.style.setProperty('--brand-secondary', branding.secondary_color)
  document.title = branding.company_name
  // ... favicon, meta tags, etc.
}
```

---

## **Using Branding in Components**

### **Access Branding Data**
```typescript
import { useBranding } from '@/contexts/BrandingContext'

function MyComponent() {
  const { branding } = useBranding()
  
  return (
    <div>
      <h1>{branding.company_name}</h1>
      <p>Support: {branding.support_email}</p>
      {branding.logo_url && <img src={branding.logo_url} alt="Logo" />}
    </div>
  )
}
```

### **Use Brand Colors in CSS**
```css
.my-button {
  background-color: var(--brand-primary);
  border-color: var(--brand-secondary);
}
```

### **Use Brand Colors in Tailwind** (if needed)
You can extend Tailwind config to use CSS variables:

```javascript
// tailwind.config.js
module.exports = {
  theme: {
    extend: {
      colors: {
        brand: {
          primary: 'var(--brand-primary)',
          secondary: 'var(--brand-secondary)',
        }
      }
    }
  }
}
```

Then use: `className="bg-brand-primary text-white"`

---

## **Available Branding Fields**

From `WhiteLabelSettings` interface:

| Field | Type | Description |
|-------|------|-------------|
| `enabled` | boolean | Whether white-labeling is enabled |
| `company_name` | string | Company display name |
| `logo_url` | string \| null | URL to company logo |
| `primary_color` | string | Primary brand color (hex) |
| `secondary_color` | string | Secondary brand color (hex) |
| `favicon_url` | string \| null | URL to favicon |
| `email_from_name` | string | Email sender name |
| `email_from_address` | string | Email sender address |
| `support_email` | string | Support contact email |
| `support_phone` | string | Support phone number |
| `footer_text` | string | Footer copyright text |
| `report_header_text` | string | Report header text |

---

## **Example: Login Page with Branding**

```typescript
import { useBranding } from '@/contexts/BrandingContext'

function LoginPage() {
  const { branding } = useBranding()
  
  return (
    <div className="min-h-screen flex items-center justify-center">
      <div className="w-full max-w-md">
        {/* Company Logo */}
        {branding.logo_url && (
          <img 
            src={branding.logo_url} 
            alt={branding.company_name}
            className="h-12 mx-auto mb-8"
          />
        )}
        
        {/* Login Form */}
        <div className="bg-white rounded-lg shadow-lg p-8">
          <h1 className="text-2xl font-bold mb-6">
            Sign in to {branding.company_name}
          </h1>
          
          {/* Form fields... */}
          
          {/* Support Contact */}
          <p className="mt-6 text-sm text-gray-600 text-center">
            Need help? Contact {branding.support_email}
          </p>
        </div>
        
        {/* Footer */}
        <p className="mt-8 text-center text-sm text-gray-500">
          {branding.footer_text}
        </p>
      </div>
    </div>
  )
}
```

---

## **Testing**

### **1. Check Current Branding**
Open browser console:
```javascript
// Check stored branding
JSON.parse(localStorage.getItem('branding'))

// Check applied CSS variables
getComputedStyle(document.documentElement).getPropertyValue('--brand-primary')
```

### **2. Test Different Branding**
Manually set branding in console:
```javascript
const testBranding = {
  enabled: true,
  company_name: "Test Company",
  primary_color: "#ff0000",
  secondary_color: "#00ff00",
  logo_url: "https://via.placeholder.com/150",
  // ... other fields
}

localStorage.setItem('branding', JSON.stringify(testBranding))
location.reload()
```

---

## **Next Steps (Optional Enhancements)**

### **1. Logo Component**
Create a reusable logo component:
```typescript
// src/components/BrandLogo.tsx
import { useBranding } from '@/contexts/BrandingContext'

export function BrandLogo({ className }: { className?: string }) {
  const { branding } = useBranding()
  
  if (!branding.logo_url) {
    return <span className={className}>{branding.company_name}</span>
  }
  
  return (
    <img 
      src={branding.logo_url} 
      alt={branding.company_name}
      className={className}
    />
  )
}
```

### **2. Branded Button Component**
```typescript
// Use brand colors for primary buttons
function BrandButton({ children, ...props }: ButtonProps) {
  return (
    <button
      style={{
        backgroundColor: 'var(--brand-primary)',
        color: 'white'
      }}
      {...props}
    >
      {children}
    </button>
  )
}
```

### **3. Settings Page**
Create a settings page where admins can update branding (requires backend endpoints).

---

## **Summary**

✅ **Branding loads automatically on login**
✅ **Applied globally via CSS variables**
✅ **Persisted to localStorage**
✅ **No backend changes required**
✅ **Easy to use in any component via `useBranding()` hook**

The frontend is now fully white-label capable! 🎨
