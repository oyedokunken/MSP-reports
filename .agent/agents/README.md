# AI Agent Personas

Specialized AI agent definitions for the MSP Reporting Platform project.

## Available Agents

### 🎨 [Frontend Engineer](./frontend-engineer.md)
**Expertise**: React 19, TypeScript, Tailwind CSS, Zustand, Apache eCharts  
**Triggers**: `frontend`, `ui`, `component`, `react`, `styling`, `state`

Specializes in building the React-based admin portal with white-label support, data visualization, and responsive design.

---

### ⚙️ [Backend Engineer](./backend-engineer.md)
**Expertise**: Laravel 12, PHP 8.2+, TimescaleDB, PostgreSQL, Redis  
**Triggers**: `backend`, `api`, `database`, `laravel`, `controller`, `model`

Specializes in RESTful API development, TimescaleDB optimization, multi-tenancy, and integration framework.

---

### 🐛 [Debugger](./debugger.md)
**Expertise**: Root Cause Analysis, Performance Profiling, Error Tracing  
**Triggers**: `debug`, `bug`, `error`, `issue`, `troubleshoot`, `fix`, `broken`

Systematically diagnoses and fixes bugs across the full stack with focus on root cause analysis.

---

### 🚀 [DevOps Engineer](./devops-engineer.md)
**Expertise**: Docker, Docker Compose, NGINX, Linux, CI/CD, Monitoring  
**Triggers**: `devops`, `docker`, `deployment`, `infrastructure`, `nginx`, `ci/cd`

Manages containerization, deployment, infrastructure, and monitoring for on-premise and future SaaS deployments.

---

### ✅ [QA Engineer](./qa-engineer.md)
**Expertise**: Pest, Vitest, E2E Testing, Test Automation  
**Triggers**: `test`, `testing`, `qa`, `quality`, `coverage`, `e2e`

Develops comprehensive testing strategies with unit, integration, and E2E tests for quality assurance.

---

### 🔗 [API Contract Specialist](./api-contract-specialist.md)
**Expertise**: API Design, TypeScript Types, OpenAPI, Zod Validation, Contract Testing  
**Triggers**: `api`, `contract`, `response`, `type`, `interface`, `dto`, `schema`

Ensures type-safe frontend-backend communication by defining clear API contracts, eliminating response structure guessing, and maintaining type safety.

---

### 🔌 [Integration Engineer](./integration-engineer.md)
**Expertise**: API Integration, Datto, Autotask, ITGlue, Webhooks, Rate Limiting, Data Mapping  
**Triggers**: `integration`, `datto`, `autotask`, `itglue`, `webhook`, `sync`, `api integration`

Builds and maintains integrations with third-party systems (PSA, RMM, backup, monitoring) using the modular integration framework.

---

### 🗄️ [Database Architect](./database-architect.md)
**Expertise**: TimescaleDB, PostgreSQL, Hypertables, Compression, Retention Policies, Multi-Tenancy  
**Triggers**: `database`, `timescaledb`, `hypertable`, `compression`, `retention`, `partition`, `query optimization`

Designs and optimizes TimescaleDB schemas, manages time-series data, implements compression and retention policies, and ensures multi-tenant data isolation.

---

### 🔐 [Security Engineer](./security-engineer.md)
**Expertise**: Multi-Tenant Security, ISO 27001, Encryption, Authentication, Audit Logging, OWASP  
**Triggers**: `security`, `authentication`, `authorization`, `encryption`, `audit`, `compliance`, `vulnerability`

Implements security best practices, ensures multi-tenant isolation, maintains ISO 27001 compliance, and manages authentication, authorization, and audit logging.

---

## How to Use

Each agent persona file defines:
- **Role & Expertise**: What the agent specializes in
- **Tech Stack**: Specific technologies and tools
- **Project Context**: MSP reporting platform specifics
- **Approach**: Methodologies and best practices
- **Common Tasks**: Typical work scenarios
- **Communication Style**: How the agent explains concepts

## Project Context

All agents are configured for the **MSP Reporting Platform**:
- Multi-tenant architecture
- TimescaleDB for time-series data
- Laravel 12 backend + React 19 frontend
- Docker-based deployment
- White-label branding support
- Integration framework (Datto, Autotask, ITGlue, etc.)

## Related Documentation

- **Project Rules**: `../.windsurf/rules/` (if migrating from Windsurf)
- **Tech Stack**: See `technology-stack.md` in rules
- **Architecture**: See `architecture.md` in rules
- **Agents (Data Collection)**: `/agents/windows_server/` (PowerShell agents)

## Adding New Agents

To create a new agent persona:

1. Create `agent-name.md` in this directory
2. Use the following frontmatter:
```yaml
---
role: Agent Role Name
expertise: [Tech1, Tech2, Tech3]
trigger: keyword1, keyword2, keyword3
---
```
3. Include sections: Role, Expertise, Approach, Common Tasks
4. Update this README with the new agent

## Notes

- These are **AI agent personas**, not data collection agents
- Data collection agents (PowerShell) are in `/agents/windows_server/`
- Agent personas reference project rules and tech stack
- Keep agents focused on specific domains
