# User API Endpoints

## Base URL

`/api`

## Authentication

All endpoints require authentication (Sanctum) and appropriate permissions.

---

## 1. Get All Users

**Endpoint:** `GET /users`

**Description:** Retrieves a paginated list of all users in the system.

**Permission Required:** `users,view`

**Response:**

- `data`: Array of user objects
- `pagination`: Pagination details (current_page, per_page, total, etc.)

---

## 2. Get User Roles

**Endpoint:** `GET /users/roles`

**Description:** Retrieves a list of all available roles (e.g., Admin, User).

**Permission Required:** `users,view`

**Response:**

- `id`: Role ID
- `name`: Role Name

---

## 3. Create User / Customer Scooping

**Endpoint:** `POST /users`

**Description:** Creates a new user. Can also set customer scoping (assignment of customers to user) and permissions during creation.

**Permission Required:** `users,manage`

**Request Body:**

- `name` (required): Full name
- `email` (required): Email address
- `password` (required): Password
- `role_id` (required): ID of the role to assign
- `status` (optional): User status (active/inactive)
- `auth_method` (optional): Authentication method
- `scoped_customers` (optional): Array of customer IDs to scope this user to
- `permissions` (optional): Custom permission overrides

---

## 4. Update User / Customer Scooping

**Endpoint:** `PATCH /users/{id}`

**Description:** Updates user details, including customer scoping and role.

**Permission Required:** `users,manage`

**Request Body:**

- `name` (optional)
- `email` (optional)
- `password` (optional): Update password
- `role_id` (optional)
- `scoped_customers` (optional): Array of customer IDs to update scope
- `permissions` (optional)

---

## 5. Update User Permission

**Endpoint:** `PATCH /users/{id}/permissions`

**Description:** Updates specific permission overrides for a user.

**Permission Required:** `users,manage`

**Request Body:**

- `permissions` (required): Object/Array defining permission states

---

## 6. Deactivate User

**Endpoint:** `PATCH /users/{id}/deactivate`

**Description:** Deactivates a user account, preventing login.

**Permission Required:** `users,manage`

---

## 7. Reactivate User

**Endpoint:** `PATCH /users/{id}/reactivate`

**Description:** Reactivates a disabled user account.

**Permission Required:** `users,manage`

---

## 8. Delete User

**Endpoint:** `DELETE /users/{id}`

**Description:** Permanently deletes a user account.

**Permission Required:** `users,manage`

---

## 9. Reset User Password (Admin)

**Endpoint:** `POST /users/{id}/reset-password`

**Description:** Admin function to force-reset a user's password.

**Permission Required:** `users,manage`

**Request Body:**

- `new_password` (required): The new password for the user

---

## 10. Upload Profile Picture

**Endpoint:** `POST /users/{id}/profile-picture`

**Description:** Uploads or updates a user's profile picture.

**Permission Required:** `users,manage`

**Request Body:**

- `profile_picture` (required): File upload (image)

---

## 11. Delete Profile Picture

**Endpoint:** `DELETE /users/{id}/profile-picture`

**Description:** Removes a user's profile picture.

**Permission Required:** `users,manage`

---

## 12. Force Password Change (User)

**Endpoint:** `POST /users/force-password-change`

**Description:** Endpoint for a user to change their password when "Force Password Change" is active.

**Permission Required:** Authenticated User

**Request Body:**

- `current_password` (required)
- `new_password` (required)
