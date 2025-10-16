# User Management Pages

Admin-only pages for creating and managing user accounts.

## Pages

### UsersList (`/users`)
- **Purpose:** Display all users in the company
- **Access:** Admin only (protected route)
- **Features:**
  - View all users with their details
  - See user status (active/inactive/locked)
  - See user roles (admin/user)
  - View last login time
  - Quick access to create new user

### CreateUser (`/users/create`)
- **Purpose:** Create new user accounts
- **Access:** Admin only (protected route)
- **Features:**
  - Full name input
  - Email address (used for login)
  - Password (minimum 6 characters)
  - Role selection (Admin/User)
  - Status selection (Active/Inactive/Locked)
  - Auth method selection (Local/SAML/M365/LDAP)
  - Success confirmation with auto-redirect
  - Error handling with user-friendly messages

## API Integration

### Create User
```typescript
POST /api/users
Authorization: Bearer {token}

Request:
{
  "name": "John Doe",
  "email": "john.doe@company.com",
  "password": "password123",
  "role_id": 2,
  "status": "active",
  "auth_method": "local"
}

Response:
{
  "status": "success",
  "data": {
    "user_id": 3,
    "name": "John Doe",
    "email": "john.doe@company.com",
    ...
  }
}
```

### Get Users
```typescript
GET /api/users
Authorization: Bearer {token}

Response:
{
  "status": "success",
  "data": [
    {
      "user_id": 1,
      "name": "Admin User",
      "email": "admin@test.com",
      "status": "active",
      "role": {
        "id": 1,
        "name": "admin"
      },
      ...
    }
  ]
}
```

## Routes

- `/users` - List all users
- `/users/create` - Create new user form

## Components Used

- `Card`, `CardHeader`, `CardTitle`, `CardDescription`, `CardContent` - Layout
- `Button` - Actions
- `Input` - Form fields
- `Label` - Form labels
- `Select` - Dropdowns (role, status, auth method)
- `Badge` - Status and role indicators
- `Spinner` - Loading states
- Icons from `lucide-react`

## Security

- ✅ **Authentication Required:** All routes protected
- ✅ **Authorization:** Admin-only access (enforced by backend)
- ✅ **Tenant Isolation:** Users scoped to authenticated user's company
- ✅ **Input Validation:** Client-side and server-side validation
- ✅ **Password Requirements:** Minimum 6 characters

## Installation Note

**Required Package:**
```bash
npm install @radix-ui/react-select
```

This package is required for the Select component to work properly.

## Usage

### Navigate to User Management
```typescript
import { useNavigate } from 'react-router-dom'

const navigate = useNavigate()
navigate('/users')
```

### Create User Button
```typescript
<Button onClick={() => navigate('/users/create')}>
  <UserPlus className="mr-2 h-4 w-4" />
  Create User
</Button>
```

## Future Enhancements

- [ ] Edit user functionality
- [ ] Delete/deactivate user
- [ ] Bulk user operations
- [ ] User permissions management
- [ ] Password reset functionality
- [ ] User activity logs
- [ ] Search and filter users
- [ ] Pagination for large user lists
