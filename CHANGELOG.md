# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Multi-tenant reporting platform with white-label support
- Integration framework with modular architecture
- Customer mapping system with auto-matching and manual mapping
- Report scheduling system with flexible frequencies (daily, weekly, monthly, yearly)
- Integration preview with PDF export functionality
- Customer selection modal for schedule creation
- Toast notifications for all user actions
- Active sidebar state for nested routes
- Settings page with company branding configuration
- User management with role-based permissions
- API key management system
- Dark mode support with theme toggle
- Responsive design for mobile, tablet, and desktop
- Real-time notifications system
- Dashboard with integration and customer summaries

### Changed
- Customer pagination increased to 21 items per page (3x7 grid)
- Settings link now properly navigates to `/admin/settings`
- Integration preview export now generates actual PDFs via browser print dialog
- Create Schedule button now opens customer selection modal
- Sidebar active states persist through nested navigation

### Fixed
- Customer card overflow issues with proper text wrapping
- Navigation URLs corrected to use `/platform` and `/admin` prefixes
- Settings sidebar item now shows active state correctly
- Integration preview toast notifications working
- Report download notifications functional

## [0.1.0] - 2025-11-26

### Added
- Initial project setup
- Backend Laravel 12 API with TimescaleDB
- Frontend React 19 with TypeScript and Tailwind CSS
- Docker Compose configuration for development
- Authentication system with Sanctum
- Database migrations and seeders
- Basic CRUD operations for all main entities

---

## Release Notes

### Version 0.1.0 - Initial Release (2025-11-26)

This is the initial release of the Multi-Tenant Reporting Platform. The platform provides a comprehensive solution for MSPs and telcos to consolidate data from multiple business systems, intelligently map customers across systems, and generate branded reports on flexible schedules.

**Key Features:**
- ✅ Multi-tenant architecture with complete data isolation
- ✅ White-label branding per company
- ✅ Integration framework (currently supports Autotask and Datto)
- ✅ Customer mapping with fuzzy matching
- ✅ Report scheduling and generation
- ✅ User management with granular permissions
- ✅ API key system for programmatic access
- ✅ Dark mode support
- ✅ Responsive design

**Known Issues:**
- Historical data import for integrations not yet implemented
- PDF generation via backend API pending (currently using browser print)
- Email delivery system not yet configured
- Data retention policies not yet enforced

**Next Steps:**
- Implement backend PDF generation API
- Add email delivery system with SMTP configuration
- Complete historical data import functionality
- Add more integration types (Kaseya, ConnectWise, etc.)
- Implement data retention cleanup jobs
- Add comprehensive error tracking and monitoring
