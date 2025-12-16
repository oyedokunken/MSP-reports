// Knowledge base for MSP System support chatbot

export const knowledgeBase = {
  integrations: {
    keywords: ['integration', 'connect', 'add integration', 'setup', 'autotask', 'datto', 'sync', 'disconnect', 'credentials', 'api key', 'test connection'],
    answers: {
      'how to add': `**Adding an Integration:**

1. Navigate to **Integrations** page from the sidebar
2. Click **"Add Integration"** button
3. Select your integration type (Autotask, Datto, etc.)
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
- Set during integration setup or in settings

This ensures consistent customer naming across all systems.`,

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
    keywords: ['monitoring', 'system status', 'health', 'metrics', 'dashboard'],
    answers: {
      'system status': `**System Monitoring:**

The **Monitoring** page shows:

**System Status:**
- Database health
- Redis status
- Queue workers
- Disk space
- Uptime

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

Check the **Notifications** page for all alerts.`,
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
    keywords: ['help', 'support', 'guide', 'how to', 'what is', 'dashboard', 'where', 'find', 'navigate', 'page'],
    answers: {
      'dashboard': `**Dashboard Overview:**

The dashboard shows:
- Total customers count
- Active integrations
- Recent reports generated
- Unmapped customers alert
- Quick access to key features

Navigate using the sidebar menu to access all features.`,
      
      'getting started': `**Getting Started:**

1. **Add Integration**: Connect your first system (Autotask, Datto, etc.)
2. **Map Customers**: Review and map unmapped customers
3. **Configure Reports**: Set up report schedules for customers
4. **Monitor**: Check the Monitoring page for system health

Need help? Use this chatbot or contact support.`,

      'navigation': `**Where do I find that?**

Here are the most common places:

- **Integrations**: connect systems, run syncs, view sync status
- **Customers**: master list, mapping, merge/split
- **Reports**: generated reports, delivery status, downloads
- **Schedules**: configure and manage when reports run
- **Monitoring**: system health and metrics
- **Admin → Users**: user accounts and permissions

Tell me what you’re trying to do and I’ll point you to the exact page.`,
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
    integration: ['integration', 'connector', 'connect', 'connected'],
    customer: ['customer', 'client', 'account'],
    mapping: ['mapping', 'map', 'match', 'link', 'merge'],
    report: ['report', 'pdf', 'export', 'download', 'generated'],
    schedule: ['schedule', 'scheduled', 'frequency', 'cron', 'timing', 'run'],
    permissions: ['permission', 'permissions', 'role', 'roles', 'access'],
    monitoring: ['monitoring', 'health', 'status', 'metrics', 'uptime'],
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
    return `Hi! I can help with **Integrations**, **Customers**, **Reports**, **Schedules**, **Monitoring**, and **User permissions**.\n\nWhat are you trying to do?`
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
- How to add an integration
- Integration syncing
- Source of Truth

**Customers**
- Customer mapping
- Master vs Secondary lists
- Merge and split customers

**Reports**
- Configure report schedules
- Report delivery
- Generate reports

**Monitoring**
- System status
- Health metrics
- Alerts

**Users**
- User management
- Permissions

Try asking about any of these topics!`
}
