# Authentication API Endpoints

## Base URL

`/api`

## Overview

These endpoints handle user authentication, including standard email/password login, password management, and SSO (Single Sign-On) via Microsoft, Keycloak, and SAML.

---

## 1. Auth Configuration

**Endpoint:** `GET /auth/config`

**Description:** Returns the public authentication configuration, indicating which auth methods (e.g., SAML, Microsoft, Password) are enabled for the frontend to display appropriate login options.

**Auth Required:** No

---

## 2. Login

**Endpoint:** `POST /auth/login`

**Description:** Authenticates a user using email and password. Returns an API token (Sanctum) for subsequent requests.

**Auth Required:** No

**Request Body:**

- `email` (required): User's email address
- `password` (required): User's password
- `device_name` (optional): Name of the device for token tracking

---

## 3. Get Current User (Me)

**Endpoint:** `GET /auth/me`

**Description:** Retrieves the profile and permissions of the currently authenticated user.

**Auth Required:** Yes (Sanctum)

---

## 4. Logout

**Endpoint:** `POST /auth/logout`

**Description:** Logs out the current user and invalidates their API token.

**Auth Required:** Yes (Sanctum)

---

## 5. Request Password Reset Link

**Endpoint:** `POST /auth/password/request`

**Description:** Sends a password reset link to the user's email address.

**Auth Required:** No

**Request Body:**

- `email` (required): User's email address

---

## 6. Reset Password

**Endpoint:** `POST /auth/password/reset`

**Description:** Resets the user's password using the token received via email.

**Auth Required:** No

**Request Body:**

- `token` (required): Reset token
- `email` (required): User's email address
- `password` (required): New password
- `password_confirmation` (required): Confirm new password

---

## 7. Change Password

**Endpoint:** `POST /auth/password/change`

**Description:** Allows an authenticated user to change their current password.

**Auth Required:** Yes (Sanctum)

**Request Body:**

- `current_password` (required): Current password
- `new_password` (required): New password
- `new_password_confirmation` (required): Confirm new password

---

## 8. Verify Password

**Endpoint:** `POST /auth/verify-password`

**Description:** Verifies the user's current password (often used before sensitive actions).

**Auth Required:** Yes (Sanctum)

**Request Body:**

- `password` (required): Password to verify

---

## 9. SAML Metadata

**Endpoint:** `GET /saml/metadata`

**Description:** Returns the SP (Service Provider) metadata XML for SAML configuration.

**Auth Required:** No

---

## SSO Endpoints (Microsoft / Keycloak / SAML)

### Microsoft

- `GET /auth/microsoft/redirect`: Redirects user to Microsoft login.
- `GET /auth/microsoft/callback`: Handle callback from Microsoft.

### Keycloak

- `GET /auth/keycloak/redirect`: Redirects user to Keycloak login.
- `GET /auth/keycloak/callback`: Handle callback from Keycloak.

### SAML

- `GET /auth/saml/login`: Initiates SAML login.
- `POST /auth/saml/acs`: Assertion Consumer Service (ACS) endpoint.
- `GET/POST /auth/saml/sls`: Single Logout Service (SLS) endpoints.
