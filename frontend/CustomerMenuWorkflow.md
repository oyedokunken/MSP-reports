# Customers Menu Implementation Workflow

## 1. Sidebar Navigation
- Moved Customers menu to appear after Integrations
- Created collapsible menu with chevron icon
- Added two submenus:
  - Master List (SOT=true customers)
  - Secondary List (SOT=false customers)
- Implemented visual design with vertical border lines and dots for each menu item
- Added collapsed state handling (icons only, no borders)

## 2. Backend API Filtering
- Combined integration type and SOT filters into single query
- Added `source_of_truth_only` parameter to customer endpoint
- Fixed filter logic to prevent showing customers with mixed integrations

## 3. Frontend Data Store
- Added `sourceOfTruthOnly` parameter to fetch method
- Integrated parameter with API service layer

## 4. Custom Hooks
- Created `useMasterCustomerList` hook with SOT=true filtering
- Created `useSecondaryCustomerList` hook with SOT=false filtering
- Added SOT integration name fetching for Master List title
- Implemented filter override to prevent double-fetching

## 5. Page Components
- Built Master Customer List page with SOT integration name badge
- Built Secondary Customer List page
- Moved unmapped customer features to Master List only
- Added custom header with dynamic title and badge styling

## 6. Routing
- Set up redirect from old customers route to Master List
- Created routes for both Master and Secondary lists
