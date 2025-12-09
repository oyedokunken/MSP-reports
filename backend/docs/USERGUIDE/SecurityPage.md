# Security Page - User Guide

## Overview
The Security page allows users to manage their personal security settings including password changes, two-factor authentication (2FA), API keys, and session management. This page focuses on individual user security, not company-wide security settings.

---

## Page Layout

### Header Section
- **Title**: "Security" with shield icon
- **Description**: "Manage your account security settings"

### Security Sections
1. **Password**: Change your password
2. **Two-Factor Authentication (2FA)**: Enable/disable 2FA
3. **API Access**: Manage personal API keys
4. **Active Sessions**: View and manage login sessions
5. **Security Log**: View your security-related activities

---

## Features & How to Use

### 1. Change Password

**Purpose:** Update your account password for security.

**When to Change:**
- Regular security maintenance (every 90 days recommended)
- Password compromised or suspected breach
- Shared password with someone
- Company policy requires change
- After leaving shared device logged in

**Steps:**
1. Click **"Change Password"** button
2. Enter **Current Password**
3. Enter **New Password**
   - Minimum 8 characters
   - At least 1 uppercase letter (A-Z)
   - At least 1 lowercase letter (a-z)
   - At least 1 number (0-9)
   - At least 1 special character (!@#$%^&*)
4. Enter **Confirm New Password** (must match)
5. Click **"Update Password"**
6. Success notification appears
7. All other sessions logged out (security measure)

**Password Strength Indicator:**
- 🔴 **Weak**: Doesn't meet requirements
- 🟡 **Fair**: Meets minimum requirements
- 🟢 **Strong**: Exceeds requirements, good entropy
- 🟢 **Very Strong**: Excellent password

**Password Tips:**
- Use a passphrase (e.g., "Coffee!Morning@2025")
- Avoid personal information (name, birthday)
- Don't reuse passwords from other sites
- Use a password manager
- Longer is better (12+ characters recommended)

**Permissions Required:** None (own account)

---

### 2. Two-Factor Authentication (2FA)

**Purpose:** Add extra security layer requiring second verification method.

#### What is 2FA?
- **First Factor**: Your password (something you know)
- **Second Factor**: Time-based code from authenticator app (something you have)
- **Result**: Even if password stolen, attacker cannot access account without your phone

#### Enable 2FA

**Steps:**
1. Click **"Enable 2FA"** button
2. **Install Authenticator App** (if not already):
   - Google Authenticator (iOS/Android)
   - Microsoft Authenticator (iOS/Android)
   - Authy (iOS/Android/Desktop)
   - 1Password (if using password manager)
3. **Scan QR Code**:
   - Open authenticator app
   - Click "Add Account" or "+"
   - Scan QR code displayed on screen
   - Or manually enter secret key
4. **Enter Verification Code**:
   - App generates 6-digit code
   - Enter code in platform
   - Code changes every 30 seconds
5. **Save Backup Codes**:
   - Platform generates 10 backup codes
   - **CRITICAL**: Save these securely!
   - Use if you lose phone or can't access app
   - Each code can only be used once
6. Click **"Enable 2FA"**
7. Success notification appears

**Backup Codes:**
- Download as text file
- Print and store securely
- Store in password manager
- **DO NOT** share or lose these!

**Using 2FA After Enabled:**
1. Enter username and password (first factor)
2. Prompted for 6-digit code
3. Open authenticator app
4. Enter current code
5. Click "Verify"
6. Logged in successfully

---

#### Disable 2FA

**Steps:**
1. Click **"Disable 2FA"** button
2. Enter current password (security confirmation)
3. Enter 6-digit code from authenticator app
4. Click **"Disable 2FA"**
5. Confirmation message appears
6. 2FA removed from account

**Warning:** Disabling 2FA reduces account security. Only disable if absolutely necessary.

---

#### Lost Phone / Cannot Access 2FA

**Recovery Options:**

**Option 1: Use Backup Code**
1. Click "Use Backup Code" on login screen
2. Enter one of your saved backup codes
3. Login successful
4. Immediately re-enable 2FA with new device

**Option 2: Contact Administrator**
1. Email admin or support
2. Verify identity (may require video call)
3. Admin disables 2FA for your account
4. Login with password only
5. Immediately re-enable 2FA

**Prevention:**
- Save backup codes securely
- Have authenticator app on multiple devices
- Use authenticator with cloud backup (Authy, Microsoft Authenticator)

---

### 3. API Access

**Purpose:** Generate API keys for programmatic access to the platform.

#### What are API Keys?
- Alternative to username/password for applications
- Used for integrations, scripts, automation
- Can be revoked without changing password
- Have same permissions as your user account

#### Generate API Key

**Steps:**
1. Click **"Generate API Key"** button
2. Enter **Key Name** (descriptive name)
   - Example: "Python Reporting Script"
   - Example: "PowerBI Integration"
3. Select **Permissions** (optional):
   - Inherit from user (default)
   - Or restrict to specific permissions
4. Set **Expiration** (optional):
   - Never (default)
   - 30 days
   - 90 days
   - 1 year
   - Custom date
5. Click **"Generate Key"**
6. **CRITICAL**: Copy API key immediately!
   - Key shown ONLY ONCE
   - Cannot be retrieved later
   - If lost, must regenerate
7. Store key securely (password manager, environment variable)

**API Key Format:**
```
athena_live_1234567890abcdefghijklmnopqrstuvwxyz1234567890abcdefghijklmnopqr
```

**Using API Key:**
```bash
# Example API request
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://api.yourcompany.com/reports
```

---

#### View Active API Keys

**Information Displayed:**
- **Key Name**: Descriptive name you provided
- **Key Preview**: First 8 and last 4 characters (e.g., `athena_l...pqr`)
- **Created**: When key was generated
- **Last Used**: Most recent API request
- **Expires**: Expiration date (if set)
- **Status**: Active, Expired, Revoked

**Actions Available:**
- **Rename**: Change key name
- **Revoke**: Immediately disable key
- **View Usage**: See API request history

---

#### Revoke API Key

**When to Revoke:**
- Key compromised or exposed
- No longer needed
- Employee left company
- Rotating keys (security best practice)

**Steps:**
1. Find key in active keys list
2. Click **"Revoke"** button
3. Confirm revocation
4. Key immediately disabled
5. All requests with this key will fail

**Note:** Cannot be undone. Generate new key if needed.

---

#### API Key Best Practices

✅ **DO:**
- Use descriptive key names
- Store keys securely (environment variables, secrets manager)
- Rotate keys every 6-12 months
- Use separate keys for different applications
- Set expiration dates
- Revoke unused keys
- Monitor key usage

❌ **DON'T:**
- Hardcode keys in source code
- Commit keys to version control (Git)
- Share keys via email or chat
- Use same key for multiple purposes
- Leave keys in logs or error messages
- Use keys on untrusted devices

---

### 4. Active Sessions

**Purpose:** View and manage all devices/browsers where you're logged in.

#### View Active Sessions

**Information Displayed:**
- **Device**: Browser and operating system
  - Example: "Chrome on Windows 10"
  - Example: "Safari on iPhone"
- **IP Address**: Where login originated
- **Location**: Approximate location (city, country)
- **Login Time**: When session started
- **Last Activity**: Most recent action
- **Current Session**: Highlighted (this device)

**Example:**
```
🖥️ Chrome on Windows 10
   IP: 203.123.45.67
   Location: Brisbane, Australia
   Login: 2 hours ago
   Last Activity: 5 minutes ago
   [Current Session]

📱 Safari on iPhone
   IP: 203.123.45.68
   Location: Brisbane, Australia
   Login: 1 day ago
   Last Activity: 3 hours ago
   [Revoke Session]
```

---

#### Revoke Session

**Purpose:** Log out specific device/browser remotely.

**When to Use:**
- Left yourself logged in on shared computer
- Lost or stolen device
- Suspicious session from unknown location
- Want to force re-login on all devices

**Steps:**
1. Review active sessions list
2. Identify session to revoke
3. Click **"Revoke Session"** button
4. Confirm revocation
5. Session immediately terminated
6. That device must login again

**Revoke All Sessions:**
1. Click **"Revoke All Other Sessions"** button
2. Confirm action
3. All sessions except current logged out
4. Forces re-login on all other devices

**Use Cases:**
- Security concern (password may be compromised)
- Left logged in at client site
- Shared device at conference
- Regular security maintenance

---

### 5. Security Log (Activity History)

**Purpose:** Audit trail of security-related activities on your account.

#### Events Logged

**Authentication Events:**
- ✅ Successful login
- ❌ Failed login attempt
- 🔓 Logout
- 🔄 Password changed
- 🔐 2FA enabled/disabled
- 🔑 API key generated/revoked

**Session Events:**
- 📱 New device login
- 🌍 Login from new location
- ⏰ Session expired
- 🚪 Session revoked

**Permission Events:**
- 👤 Permissions changed
- 👥 Role assigned/removed
- 🔒 Access denied

**Security Events:**
- ⚠️ Suspicious activity detected
- 🚨 Account locked (too many failed logins)
- 🔓 Account unlocked
- 📧 Password reset requested

---

#### View Security Log

**Information Displayed:**
- **Event Type**: What happened
- **Timestamp**: When it occurred
- **IP Address**: Where it came from
- **Device**: Browser/OS used
- **Location**: Approximate location
- **Status**: Success or Failed
- **Details**: Additional context

**Example Log Entry:**
```
✅ Successful Login
   Time: 2025-12-10 09:15:23 AEST
   IP: 203.123.45.67
   Device: Chrome on Windows 10
   Location: Brisbane, Australia
   Details: Login successful with 2FA
```

**Filtering:**
- **Date Range**: Last 7 days, 30 days, 90 days, Custom
- **Event Type**: All, Logins, Password Changes, API Keys, etc.
- **Status**: All, Success, Failed
- **Search**: Search by IP, location, device

---

#### Suspicious Activity Alerts

**What Triggers Alerts:**
- Login from new country
- Login from unusual IP address
- Multiple failed login attempts
- Login at unusual time (e.g., 3 AM)
- API key used from unexpected location

**Alert Actions:**
1. Email notification sent immediately
2. Event highlighted in security log
3. Optional: Require password change
4. Optional: Automatically lock account

**If You See Suspicious Activity:**
1. Review security log details
2. If legitimate (you traveling, VPN, etc.):
   - Mark as "Not Suspicious"
3. If NOT you:
   - **Immediately change password**
   - **Revoke all sessions**
   - **Enable 2FA** (if not already)
   - **Revoke all API keys**
   - **Contact administrator**
   - **Review recent account activity**

---

## Security Best Practices

### Password Security
✅ **DO:**
- Use unique password (not used elsewhere)
- Use password manager
- Change password every 90 days
- Use 12+ character passphrase
- Enable 2FA

❌ **DON'T:**
- Share password with anyone
- Write password on sticky note
- Use personal information in password
- Reuse passwords
- Save password in browser on shared device

---

### 2FA Security
✅ **DO:**
- Enable 2FA immediately
- Save backup codes securely
- Use authenticator app (not SMS if possible)
- Have authenticator on multiple devices
- Test backup codes periodically

❌ **DON'T:**
- Share 2FA codes
- Screenshot QR code and store insecurely
- Lose backup codes
- Disable 2FA unless absolutely necessary
- Use same 2FA for multiple critical accounts

---

### API Key Security
✅ **DO:**
- Store keys in environment variables
- Use secrets manager (AWS Secrets Manager, Azure Key Vault)
- Rotate keys every 6 months
- Set expiration dates
- Use separate keys per application
- Revoke unused keys

❌ **DON'T:**
- Commit keys to Git
- Hardcode in source code
- Share keys via email/chat
- Use same key everywhere
- Leave keys in logs
- Store keys in plain text files

---

### Session Security
✅ **DO:**
- Review active sessions weekly
- Revoke unknown sessions immediately
- Logout when leaving shared device
- Use "Remember Me" only on personal devices
- Monitor security log regularly

❌ **DON'T:**
- Stay logged in on public computers
- Ignore suspicious session alerts
- Share login session
- Leave device unlocked and logged in

---

## Troubleshooting

### Cannot Login After Enabling 2FA
**Cause:** Time sync issue or lost authenticator
**Solution:**
1. Verify device time is correct (auto-sync enabled)
2. Try backup code
3. Contact administrator for 2FA reset
4. Re-enable 2FA after login

### API Key Not Working
**Cause:** Key revoked, expired, or incorrect format
**Solution:**
1. Verify key is active in API Keys list
2. Check expiration date
3. Ensure full key copied (no spaces/truncation)
4. Verify correct authorization header format
5. Generate new key if needed

### Locked Out of Account
**Cause:** Too many failed login attempts
**Solution:**
1. Wait 30 minutes (auto-unlock)
2. Or contact administrator for immediate unlock
3. Verify password is correct
4. Check Caps Lock is off
5. Try password reset if forgotten

### Suspicious Activity Alert
**Cause:** Login from new location or unusual pattern
**Solution:**
1. Review security log for details
2. If legitimate: Mark as "Not Suspicious"
3. If NOT you: Change password immediately, revoke sessions
4. Enable 2FA if not already enabled

### Lost 2FA Device
**Cause:** Phone lost, stolen, or broken
**Solution:**
1. Use backup code to login
2. Disable 2FA
3. Re-enable 2FA with new device
4. Or contact administrator for 2FA reset

---

## FAQ

**Q: How often should I change my password?**
A: Every 90 days recommended, or immediately if compromised.

**Q: Is 2FA required?**
A: Depends on company policy. Highly recommended for all users, required for admins.

**Q: Can I use SMS for 2FA instead of app?**
A: Not currently supported. Authenticator app is more secure.

**Q: What happens if I lose my backup codes?**
A: Contact administrator to disable 2FA. You'll need to verify identity.

**Q: Can I have multiple API keys?**
A: Yes, unlimited. Recommended to have separate keys per application.

**Q: Do API keys expire?**
A: Only if you set expiration date. Otherwise, valid until revoked.

**Q: Can administrator see my password?**
A: No, passwords are hashed. Admins cannot view passwords.

**Q: What if I see a session I don't recognize?**
A: Revoke it immediately, change password, enable 2FA, contact admin.

**Q: Can I use same 2FA app for multiple accounts?**
A: Yes, most authenticator apps support multiple accounts.

**Q: How do I know if my account was compromised?**
A: Check security log for suspicious logins, failed attempts, or unexpected changes.

---

## Security Incident Response

### If You Suspect Account Compromise

**Immediate Actions:**
1. ✅ Change password immediately
2. ✅ Revoke all active sessions
3. ✅ Enable 2FA (if not already)
4. ✅ Revoke all API keys
5. ✅ Review security log for unauthorized activity
6. ✅ Contact administrator/security team
7. ✅ Document what you observed

**Follow-Up Actions:**
1. Review recent account activity
2. Check if any data was accessed/modified
3. Notify affected parties if needed
4. Update passwords on other accounts (if reused)
5. Enable 2FA on all critical accounts
6. Consider using password manager

**Prevention:**
- Use unique passwords
- Enable 2FA everywhere possible
- Don't click suspicious links
- Keep software updated
- Use antivirus software
- Be cautious on public WiFi

---

## Related Pages
- **Settings**: Company-wide security settings (admins)
- **Users**: Manage user accounts and permissions (admins)
- **Audit Logs**: Company-wide security audit trail (admins)
- **API Keys**: Company API keys (separate from personal keys)
