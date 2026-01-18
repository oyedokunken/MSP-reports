// Knowledge base for MSP System support chatbot
// Last updated: January 2026

export const knowledgeBase = {
  integrations: {
    keywords: ['integration', 'connect', 'add integration', 'setup', 'autotask', 'datto', 'itglue', 'sendmarc', 'bitdefender', 'sync', 'disconnect', 'credentials', 'api key', 'test connection', 'preview'],
    answers: {
      'how to add': `**Adding an Integration:**

1. Navigate to **Integrations** page from the sidebar
2. Click **"Add Integration"** button
3. Select your integration type (Autotask, Datto RMM, ITGlue, Sendmarc, BitDefender, etc.)
4. Enter the required credentials:
   - API URL
   - API Key/Username
   - Password (if required)
5. Click **"Test Connection"** to verify
6. Click **"Save"** to activate

The integration will start syncing automatically.`,
      
      'sync': `**Integration Syncing:**

- **Auto-sync**: Runs every 4 hours by default
- **Manual sync**: Click the sync button on the integration card
- **Sync status**: View in the Integrations page
- **Failed syncs**: Check the error message and verify credentials

You can view sync history and logs in the integration details.`,
      
      'source of truth': `**Source of Truth Integration:**

The Source of Truth (SOT) integration is your primary customer list source.

- Only **one** integration can be SOT
- SOT customers appear in the **Master List**
- Other integrations map TO these customers
- Set in **Settings → Company Settings**

This ensures consistent customer naming across all systems.`,

      'preview': `**Integration Data Preview:**

Before generating reports, you can preview integration data:

1. Go to **Integrations**
2. Click on an integration card
3. Click **"Preview Data"**
4. View live data from the integration
5. Export to PDF if needed

This helps verify data quality before report generation.`,

      'disconnect': `**Disconnecting an Integration:**

1. Go to **Integrations**
2. Open the integration you want to disconnect
3. Choose **Disconnect** (or disable)
4. Confirm the action

If you reconnect later, you may need to re-test credentials and re-sync.`,
    },
  },

  customers: {
    keywords: ['customer', 'mapping', 'map', 'unmapped', 'master list', 'secondary list', 'merge', 'split', 'duplicate'],
    answers: {
      'mapping': `**Customer Mapping:**

When customers from different integrations have different names, you need to map them:

1. Go to **Customers → Map Customers**
2. You'll see unmapped customers with suggested matches
3. Options:
   - **Map to existing**: Link to a customer in your Master List
   - **Create new**: Create a new customer record
   - **Ignore**: Skip this customer

**Auto-mapping** suggests matches based on name similarity (85%+ confidence).`,
      
      'master vs secondary': `**Master List vs Secondary List:**

**Master List:**
- Customers from your Source of Truth integration
- Your primary customer database
- All other customers map to these

**Secondary List:**
- Customers from non-SOT integrations
- Already mapped to Master List customers
- Shows which integrations each customer appears in`,
      
      'merge split': `**Merge & Split Customers:**

**Merge**: Combine duplicate customers
- Select primary customer
- Choose customer to merge
- All mappings transfer to primary

**Split**: Separate incorrectly combined customers
- Select customer to split
- Choose which integrations to move
- Create new customer for moved integrations`,
    },
  },

  reports: {
    keywords: ['report', 'schedule', 'generate', 'configure', 'pdf', 'email', 'download', 'run now', 'on-demand'],
    answers: {
      'configure': `**Configuring Reports:**

1. Go to **Customers** and select a customer
2. Click **"Configure Reports"**
3. Add report schedules:
   - Select report type
   - Choose frequency (daily/weekly/monthly)
   - Set time of day
   - Configure delivery email
4. Click **"Save Schedule"**

Reports generate automatically based on your schedule.`,
      
      'schedules': `**Report Schedules:**

View all schedules in **Schedules** page:

- **Active/Inactive**: Toggle schedule status
- **Edit**: Modify frequency or time
- **Delete**: Remove schedule
- **Run Now**: Generate report immediately

Next generation time is shown on each schedule card.`,
      
      'delivery': `**Report Delivery:**

Reports are automatically:
- Generated at scheduled time
- Converted to PDF with your branding
- Emailed to configured recipients
- Stored in the system for download

Check delivery status in the **Reports** page.`,

      'download': `**Downloading Reports:**

1. Go to **Reports**
2. Open the report you want
3. Click **Download** (PDF)

If you don’t see a report you expect, check the customer’s schedule and whether generation succeeded.`,
    },
  },

  monitoring: {
    keywords: ['monitoring', 'system status', 'health', 'metrics', 'dashboard', 'queue', 'workers'],
    answers: {
      'system status': `**System Monitoring:**

The **Monitoring** page shows:

**System Status:**
- Database health
- Cache status
- Queue workers
- Disk space

**Metrics:**
- Integration sync statistics
- Report generation success rates
- Queue job processing
- Activity trends (7-day graph)

Auto-refreshes every 30 seconds.`,
      
      'alerts': `**System Alerts:**

You'll be notified when:
- Integration syncs fail
- Reports fail to generate
- Queue workers stop
- Disk space is low

Check the **Notifications** bell icon in the header for all alerts.`,
    },
  },

  apiKeys: {
    keywords: ['api key', 'api keys', 'programmatic', 'automation', 'token', 'access token'],
    answers: {
      'manage': `**API Key Management:**

API keys allow programmatic access to the platform:

1. Go to **Admin → API Keys**
2. Click **"Create API Key"**
3. Set a display name
4. Configure permissions (same as user permissions)
5. Optionally set IP whitelist
6. Click **"Create"**

**Important:** Copy the key immediately - it's only shown once!`,

      'permissions': `**API Key Permissions:**

API keys have the same permission structure as users:

- **Integrations**: view/manage/sync
- **Customers**: view/edit/map
- **Reports**: view/configure/generate/download
- **Company settings**: view/edit

Keys can also be scoped to specific customers for limited access.`,
    },
  },

  auditLogs: {
    keywords: ['audit', 'audit log', 'history', 'activity', 'who did', 'track', 'compliance'],
    answers: {
      'view': `**Audit Logs:**

Track all system activity in **Admin → Audit Logs**:

- **User actions**: logins, permission changes
- **Integration events**: syncs, credential updates
- **Report activity**: generation, delivery, downloads
- **System events**: configuration changes

Filter by date, user, event type, or search for specific actions.

Audit logs are retained for 7 years for compliance.`,
    },
  },

  templates: {
    keywords: ['template', 'report template', 'branding', 'customize', 'logo', 'colors'],
    answers: {
      'manage': `**Report Templates:**

Customize report appearance in **Templates**:

- View available report types
- Preview template layouts
- Configure branding (logo, colors)
- Set default formatting options

Templates use your company branding from Settings.`,
    },
  },

  licenses: {
    keywords: ['license', 'subscription', 'plan', 'features', 'upgrade'],
    answers: {
      'view': `**Licenses:**

View your license status in **Admin → Licenses**:

- Current subscription tier
- Licensed integrations
- Data retention limits
- Expiry dates

Contact your administrator to upgrade or add integrations.`,
    },
  },

  users: {
    keywords: ['user', 'permission', 'access', 'admin', 'role', 'invite', 'remove user'],
    answers: {
      'manage users': `**User Management:**

Admins can manage users in **Admin → Users**:

1. Click **"Add User"**
2. Enter user details (name, email)
3. Set permissions:
   - **Integrations**: view/manage/sync
   - **Customers**: view/edit/map
   - **Reports**: view/configure/generate
   - **Users**: view/manage
   - **Settings**: view/edit
4. Click **"Create User"**

Users receive login credentials via email.`,
      
      'permissions': `**User Permissions:**

Granular permissions control what users can do:

- **View**: Read-only access
- **Manage**: Create, edit, delete
- **Sync**: Trigger integration syncs
- **Configure**: Set up reports and schedules
- **Generate**: Create reports on-demand

Set permissions per user based on their role.`,
    },
  },

  general: {
    keywords: ['help', 'support', 'guide', 'how to', 'what is', 'dashboard', 'where', 'find', 'navigate', 'page', 'settings', 'docs', 'documentation'],
    answers: {
      'dashboard': `**Dashboard Overview:**

The dashboard shows:
- System health status
- Active integrations count
- Recent reports generated
- Activity trends chart
- Quick access to key features

Navigate using the sidebar menu to access all features.`,
      
      'getting started': `**Getting Started:**

1. **Add Integration**: Connect your first system (Autotask, Datto RMM, ITGlue, etc.)
2. **Wait for Sync**: Integration will pull customer data automatically
3. **Map Customers**: Review and map unmapped customers in Customers → Map
4. **Configure Reports**: Set up report schedules for customers
5. **Monitor**: Check the Monitoring page for system health

Need help? Use this chatbot, check the **Docs** page, or contact support.`,

      'navigation': `**Where do I find that?**

**Main Features:**
- **Dashboard**: System overview and quick stats
- **Integrations**: Connect systems, run syncs, preview data
- **Customers**: Master list, Secondary list, Map customers
- **Reports**: Generated reports, delivery status, downloads
- **Schedules**: Configure when reports run

**Admin Features:**
- **Users**: User accounts and permissions
- **API Keys**: Programmatic access tokens
- **Audit Logs**: Activity history and compliance
- **Licenses**: Subscription and feature status
- **Settings**: Company branding and configuration

**Help:**
- **Docs**: Full documentation and guides
- **Notifications**: System alerts (bell icon)

Tell me what you're trying to do and I'll point you to the exact page.`,

      'settings': `**Settings:**

Access **Settings** from the sidebar to configure:

**Company Settings:**
- Company name, email, phone, address
- Source of Truth integration
- Default timezone

**Branding:**
- Logo upload
- Primary and secondary colors
- Report header text
- Email templates

**Security:**
- Password policy
- Session timeout
- Two-factor authentication`,

      'documentation': `**Documentation:**

Access full documentation in the **Docs** page:

- **Getting Started**: Platform overview, first login
- **Integrations**: How to connect and configure
- **Customers**: Mapping and management
- **Reports**: Scheduling and delivery
- **Administration**: Users, permissions, settings

Use the search bar to find specific topics.`,
    },
  },

  authentication: {
    keywords: ['login', 'sign in', 'password', 'forgot password', 'reset', 'sso', 'saml', 'ldap', 'microsoft', 'm365'],
    answers: {
      'login': `**Logging In:**

1. Go to the login page
2. Enter your email and password
3. Click **Sign In**

**Alternative login methods** (if configured):
- **Microsoft 365**: Click "Sign in with Microsoft"
- **SSO/SAML**: Click "Enterprise SSO"
- **LDAP**: Use your Active Directory credentials

Contact your admin if you can't log in.`,

      'forgot password': `**Forgot Password:**

1. Click **"Forgot Password?"** on the login page
2. Enter your email address
3. Click **"Send Reset Link"**
4. Check your email for the reset link
5. Click the link and set a new password

If you don't receive the email, check spam or contact your admin.`,
    },
  },

  notifications: {
    keywords: ['notification', 'alert', 'bell', 'notify', 'email notification'],
    answers: {
      'view': `**Notifications:**

Click the **bell icon** in the header to view notifications:

- Integration sync failures
- Report generation status
- System alerts
- Important updates

Click a notification to see details or take action.

Configure notification preferences in **Settings → Notifications**.`,
    },
  },
}

// Simple pattern matching to find relevant answers
export function findAnswer(question: string): string {
  const normalized = question
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()

  if (!normalized) {
    return `Type a question and I’ll do my best to help.`
  }

  const stopwords = new Set([
    'a',
    'an',
    'and',
    'are',
    'as',
    'at',
    'be',
    'by',
    'can',
    'do',
    'does',
    'for',
    'from',
    'how',
    'i',
    'in',
    'is',
    'it',
    'me',
    'my',
    'of',
    'on',
    'or',
    'the',
    'to',
    'we',
    'what',
    'where',
    'when',
    'who',
    'with',
    'you',
    'your',
  ])

  const rawTokens = normalized.split(' ').filter(Boolean)
  const tokens = rawTokens.filter((t) => !stopwords.has(t))
  const tokenSet = new Set(tokens)

  const synonymGroups: Record<string, string[]> = {
    sync: ['sync', 'synchronise', 'synchronize', 'pull', 'refresh', 'update'],
    integration: ['integration', 'connector', 'connect', 'connected', 'autotask', 'datto', 'itglue'],
    customer: ['customer', 'client', 'account'],
    mapping: ['mapping', 'map', 'match', 'link', 'merge'],
    report: ['report', 'pdf', 'export', 'download', 'generated'],
    schedule: ['schedule', 'scheduled', 'frequency', 'cron', 'timing', 'run'],
    permissions: ['permission', 'permissions', 'role', 'roles', 'access'],
    monitoring: ['monitoring', 'health', 'status', 'metrics', 'uptime'],
    apikey: ['api', 'apikey', 'token', 'automation', 'programmatic'],
    audit: ['audit', 'log', 'history', 'activity', 'compliance'],
    login: ['login', 'signin', 'password', 'forgot', 'reset', 'sso', 'saml'],
    settings: ['settings', 'configure', 'branding', 'logo', 'colors'],
  }

  const expandedTokenSet = new Set<string>([...tokenSet])
  for (const [canonical, words] of Object.entries(synonymGroups)) {
    if (words.some((w) => tokenSet.has(w))) {
      expandedTokenSet.add(canonical)
      for (const w of words) expandedTokenSet.add(w)
    }
  }

  const isGreeting = /(\bhi\b|\bhello\b|\bhey\b|\bgood\s*(morning|afternoon|evening)\b)/i.test(normalized)
  if (isGreeting) {
    return `Hi! I can help with:\n\n- **Integrations** - Connect Autotask, Datto, ITGlue, etc.\n- **Customers** - Mapping and management\n- **Reports & Schedules** - Generation and delivery\n- **Admin** - Users, API keys, audit logs\n- **System** - Monitoring, settings, notifications\n\nWhat are you trying to do?`
  }

  const isThanks = /(\bthanks\b|\bthank you\b|\bthx\b)/i.test(normalized)
  if (isThanks) {
    return `You’re welcome. If you tell me what screen you’re on and what you’re trying to achieve, I can guide you step-by-step.`
  }

  type ScoredAnswer = {
    score: number
    answer: string
  }

  const scoreKeyword = (keyword: string): number => {
    const k = keyword
      .toLowerCase()
      .replace(/[^a-z0-9\s]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
    if (!k) return 0

    if (normalized.includes(k)) return 6

    const parts = k.split(' ').filter(Boolean)
    if (parts.length === 0) return 0

    const hitCount = parts.filter((p) => expandedTokenSet.has(p)).length
    if (hitCount === 0) return 0

    return hitCount >= Math.max(1, Math.ceil(parts.length * 0.6)) ? 4 : 2
  }

  const scoreAnswerKey = (key: string): number => {
    const parts = key
      .toLowerCase()
      .replace(/[^a-z0-9\s]/g, ' ')
      .replace(/\s+/g, ' ')
      .trim()
      .split(' ')
      .filter(Boolean)
      .filter((p) => !stopwords.has(p))

    if (parts.length === 0) return 0

    const hitCount = parts.filter((p) => expandedTokenSet.has(p)).length
    if (hitCount === 0) return 0

    if (hitCount === parts.length) return 8
    if (hitCount >= Math.max(1, Math.ceil(parts.length * 0.6))) return 5
    return 3
  }

  let best: ScoredAnswer | null = null

  for (const [, data] of Object.entries(knowledgeBase)) {
    const categoryScore = Math.max(0, ...data.keywords.map(scoreKeyword))
    if (categoryScore === 0) continue

    for (const [key, answer] of Object.entries(data.answers)) {
      let score = categoryScore + scoreAnswerKey(key)

      if (/(where|find|navigate|go to)\b/.test(normalized) && key.includes('navigation')) {
        score += 3
      }

      if (!best || score > best.score) {
        best = { score, answer }
      }
    }
  }

  if (best && best.score >= 6) {
    return best.answer
  }

  if (/(where|find|navigate|page)\b/.test(normalized)) {
    return knowledgeBase.general.answers.navigation
  }

  // Default response if no match found
  return `I'm not sure about that. Here are some topics I can help with:

**Integrations**
- Adding integrations (Autotask, Datto, ITGlue, etc.)
- Syncing and previewing data
- Source of Truth setup

**Customers**
- Customer mapping
- Master vs Secondary lists
- Merge and split customers

**Reports & Schedules**
- Configure report schedules
- Report delivery and downloads
- Generate reports on-demand

**Admin Features**
- User management and permissions
- API keys for automation
- Audit logs and compliance
- Licenses and subscriptions

**System**
- Monitoring and health status
- Notifications and alerts
- Settings and branding

**Help**
- Login and password reset
- Documentation and guides

Try asking about any of these topics!`
}
