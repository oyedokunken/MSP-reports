import type { DocCategory } from '../types'

export const gettingStartedCategory: DocCategory = {
  id: 'getting-started',
  title: 'Getting Started',
  description: 'Learn the basics of using the reporting platform',
  icon: 'Rocket',
  articles: [
    {
      id: 'platform-overview',
      title: 'Platform Overview',
      description: 'Introduction to the reporting platform and its key features',
      icon: 'LayoutDashboard',
      content: `
# Platform Overview

Welcome to the MSP Reporting Platform! This comprehensive guide will help you understand the platform's capabilities and how to make the most of its features.

## What is the Reporting Platform?

The Reporting Platform is a centralised solution designed for Managed Service Providers (MSPs) and telecommunications companies to:

- **Consolidate data** from multiple business systems (PSA, RMM, backup solutions, etc.)
- **Intelligently map customers** across different systems despite naming inconsistencies
- **Generate professional, branded reports** on flexible schedules
- **Deliver reports automatically** via email or API
- **Analyse historical data** and trends

## Key Benefits

### Time Savings
Save 10-20 hours per month per staff member on report compilation by automating the entire process.

### Professional Image
Deliver consistent, white-labelled reports that enhance your professional image with customers.

### Data Accuracy
Eliminate manual data gathering errors with automated integration syncing.

### Customer Satisfaction
Provide timely, accurate reports that demonstrate the value of your services.

## Main Features

### 1. Dashboard
Your central hub showing system health, recent activity, and key metrics at a glance.

### 2. Integrations
Connect to your business systems like Autotask, Datto RMM, ITGlue, CIPP (Microsoft 365), LibreNMS, Sendmarc, BitDefender and more. Each integration syncs data automatically with live data preview capabilities.

### 3. Customer Management
Manage your master customer list and map customers across different integrations for unified reporting. The auto-matching engine suggests mappings with confidence scores.

### 4. Reports
Generate and manage reports for your customers with customisable templates, section selection, and white-label branding. Available report types include Autotask Summary, Microsoft 365 Summary (CIPP), Datto Backup, IT Glue Documentation, and Combined Monthly.

### 5. Schedules
Set up automated report generation on daily, weekly, monthly, or yearly schedules with flexible timing options.

### 6. Profile & Settings
Manage your profile picture (with crop and zoom), change your password, and configure security settings from the Profile section in the sidebar.

### 7. AI Assistant
Click the chat bubble in the bottom-right corner to get instant help. The AI Assistant can answer questions about any feature and guide you step-by-step.

## Getting Help

If you need assistance:
- **AI Assistant** - Click the chat bubble for instant answers
- **Documentation** - Browse these guides for detailed information
- **System Administrator** - Contact for account and permission issues
- **Support** - Reach out via the contact information in your settings
      `,
      relatedArticles: ['first-login', 'dashboard-guide', 'user-roles']
    },
    {
      id: 'first-login',
      title: 'First Login & Setup',
      description: 'Step-by-step guide for your first login and initial setup',
      icon: 'LogIn',
      content: `
# First Login & Setup

This guide walks you through your first login and initial setup of the reporting platform.

## Receiving Your Credentials

Your system administrator will provide you with:
- **Login URL** - The web address to access the platform
- **Email address** - Your username for logging in
- **Temporary password** - You'll be prompted to change this on first login

## Logging In

1. Navigate to the login URL provided by your administrator
2. Enter your email address
3. Enter your temporary password
4. Click **Sign In**

### Alternative Login Methods

Depending on your organisation's configuration, you may also see:
- **Sign in with Microsoft** - Use your Microsoft 365 account
- **Enterprise SSO** - Use your organisation's single sign-on (Keycloak, SAML)
- **LDAP** - Use your Active Directory credentials

## Changing Your Password

On your first login, you should change your password:

1. Click on your profile icon in the sidebar footer
2. Navigate to **Settings** or **Security**
3. Select **Change Password**
4. Enter your current password
5. Enter and confirm your new password
6. Click **Save**

### Password Requirements
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character

## Setting Up Two-Factor Authentication (Recommended)

For enhanced security, enable two-factor authentication:

1. Go to **Security** settings
2. Click **Enable 2FA**
3. Scan the QR code with your authenticator app
4. Enter the verification code
5. Save your backup codes in a secure location

## Exploring the Dashboard

After logging in, you'll see the main dashboard with:
- **System health indicators**
- **Recent activity feed**
- **Quick action buttons**
- **Integration status overview**
- **Activity trends chart**

## Meet Your AI Assistant

Look for the chat bubble in the bottom-right corner - this is your AI Assistant! On first login, it will introduce itself and offer to help you get started. You can ask it questions like:
- "How do I add an integration?"
- "Where do I map customers?"
- "How do I configure reports?"

## Next Steps

1. Familiarise yourself with the navigation sidebar
2. Review your user permissions with your administrator
3. Explore the integrations section
4. Check out the customer management features
      `,
      relatedArticles: ['platform-overview', 'dashboard-guide', 'security-settings']
    },
    {
      id: 'dashboard-guide',
      title: 'Understanding the Dashboard',
      description: 'Navigate and use the main dashboard effectively',
      icon: 'LayoutDashboard',
      content: `
# Understanding the Dashboard

The dashboard is your central command centre for monitoring system health and accessing key features quickly.

## Dashboard Layout

### Header Section
- **Company logo** - Click to return to dashboard from anywhere
- **Search** - Quick search across the platform (if enabled)
- **Notifications** - Bell icon showing unread notifications
- **Theme toggle** - Switch between light and dark modes

### Sidebar Navigation
The left sidebar provides access to all platform sections:

**Platform Section:**
- Dashboard
- Integrations
- Customers
- Reports
- Schedules
- Notifications

**Admin Section (if you have permissions):**
- Users
- API Keys
- Licenses
- Monitoring
- Settings

**Profile Section:**
- My Profile (profile picture, name, security settings)

### Main Content Area
The central area displays:

#### System Health Cards
- **Integration Status** - Shows connected integrations and their sync status
- **Customer Count** - Total customers and mapping status
- **Report Statistics** - Recent report generation metrics
- **System Alerts** - Any warnings or issues requiring attention

#### Recent Activity
A timeline of recent actions including:
- Integration syncs
- Report generations
- User logins
- Configuration changes

#### Quick Actions
Buttons for common tasks:
- Add new integration
- Generate report
- View customers
- Check schedules

## Customising Your View

### Collapsing the Sidebar
Click the rail on the right edge of the sidebar to collapse it to icon-only mode, giving you more screen space.

### Dark Mode
Toggle dark mode using the theme switch in the header for comfortable viewing in low-light conditions.

## Dashboard Widgets

### Integration Health
Shows each connected integration with:
- **Green** - Syncing normally
- **Yellow** - Warning (check for issues)
- **Red** - Error (requires attention)

### Customer Mapping Status
Displays:
- Total customers in master list
- Unmapped customers requiring attention
- Recently mapped customers

### Report Generation Stats
Shows:
- Reports generated today/this week/this month
- Success rate
- Pending scheduled reports
      `,
      relatedArticles: ['platform-overview', 'navigation-guide', 'notifications']
    },
    {
      id: 'user-roles',
      title: 'User Roles & Permissions',
      description: 'Understanding different user roles and what they can access',
      icon: 'Shield',
      content: `
# User Roles & Permissions

The platform uses a granular permission system to control what each user can see and do.

## Permission Categories

### Integrations
- **View** - See integration list and status
- **Manage** - Add, edit, or remove integrations
- **Sync** - Trigger manual syncs

### Customers
- **View** - See customer lists and details
- **Edit** - Modify customer information
- **Map** - Map customers across integrations

### Reports
- **View** - See report list and history
- **Configure** - Set up report templates and settings
- **Generate** - Manually generate reports
- **Download** - Download generated reports

### Users
- **View** - See user list
- **Manage** - Create, edit, or deactivate users

### Company Settings
- **View** - See company configuration
- **Edit** - Modify company settings, branding, etc.

## Common Role Configurations

### Administrator
Full access to all features:
- All permissions enabled
- Can manage users and settings
- Can configure integrations
- Can access all reports

### Report Manager
Focused on report operations:
- View and manage integrations
- Full customer access
- Full report access
- No user management
- Limited settings access

### Viewer
Read-only access:
- View integrations (no management)
- View customers (no editing)
- View and download reports (no configuration)
- No admin access

### Integration Specialist
Focused on data management:
- Full integration access
- Full customer access including mapping
- View reports only
- No admin access

## Checking Your Permissions

To see what you can access:
1. Note which menu items appear in your sidebar
2. Items you don't have permission for won't be visible
3. Contact your administrator if you need additional access

## Requesting Permission Changes

If you need different permissions:
1. Identify what access you need and why
2. Contact your system administrator
3. They can adjust your permissions in the Users section
      `,
      relatedArticles: ['first-login', 'user-management', 'security-settings']
    }
  ]
}
