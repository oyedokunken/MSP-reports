# Multi-Tenant Reporting Platform

A centralised reporting platform for MSPs and telcos that consolidates data from multiple business systems, intelligently maps customers across integrations, and generates branded reports on flexible schedules.

## About

This platform solves the critical pain point of fragmented customer data across multiple systems (PSA, RMM, backup solutions, etc.). It automatically integrates with various tools, maps customers despite naming inconsistencies, and generates professional, white-labeled reports for delivery to end customers.

**Key Features:**
- Multi-tenant architecture with complete tenant isolation
- Modular integration framework (Autotask, Datto, etc.)
- Intelligent customer mapping across systems
- Flexible report generation and scheduling
- White-label branding per company
- ISO 27001 compliant with full audit trails

## Documentation

- **[Project Overview](PROJECT_OVERVIEW.md)** - Concise summary of features, technical stack, and development guidelines
- **[Project Plan](PROJECT_PLAN.md)** - Comprehensive technical architecture, data models, and implementation phases
- **[Quick Start](QUICK_START.md)** - Step-by-step guide to get started with the platform

## Further Reading

- **[Multi-Developer Setup](docs/MULTI_DEVELOPER_SETUP.md)** - Avoid port conflicts on shared servers
- **[Encryption](docs/ENCRYPTION_SUMMARY.md)** - How encryption is configured in the platform
- **[Docker](docs/DOCKER_SUMMARY.md)** - How Docker is configured in the platform
- **[Docker Setup](docs/DOCKER_SETUP.md)** - How to set up Docker for the platform

## Technology Stack

- **Frontend:** React 19, Tailwind CSS
- **Backend:** Laravel 12, PHP 8.2+
- **Database:** TimescaleDB (PostgreSQL with time-series extensions)
- **Infrastructure:** Docker, Redis, NGINX

## Getting Started

*Development setup instructions will be added as the project progresses.*

---

**Built by Athena Networks for Territory Technology Solutions and future MSP/telco clients.**
