# Customers Navigation Restructure Plan

## Overview
Restructure the Customers navigation in the sidebar to include a collapsible menu with two submenus:
1. **Master List** - Shows customers from Source of Truth integrations (SOT = true)
2. **Secondary Customers** - Shows customers from non-Source of Truth integrations (SOT = false)

Additionally, move the unmapped customers functionality to the Master List page.

---

## Current State Analysis

### Sidebar Structure (`AppSidebar.tsx`)
- **Location**: `/home/kristian/reporting/frontend/src/components/layout/AppSidebar.tsx`
- **Current Customers Item**: Single flat menu item at line 47
  ```tsx
  { path: '/platform/customers', icon: Users, label: 'Customers', show: hasPermission(user, 'customers', 'view') }
  ```
- **UI Components Available**: 
  - `SidebarMenu`, `SidebarMenuItem`, `SidebarMenuButton` (basic items)
  - `SidebarMenuSub`, `SidebarMenuSubItem`, `SidebarMenuSubButton` (submenu components exist in sidebar.tsx)
  - Collapsible functionality available via Radix UI

### Current Customers Page (`CustomerList.tsx`)
- **Location**: `/home/kristian/reporting/frontend/src/pages/customers/CustomerList.tsx`
- **Features**:
  - Shows all mapped customers (no SOT filtering currently)
  - UnmappedBanner component (lines 65-68)
  - MapCustomerModal for mapping workflow
  - Filters: search, status, report config, integration type
  - Customer actions: edit, view integrations, merge, split

### API Endpoint
- **Endpoint**: `GET /api/customers`
- **New Filter Parameter**: `source_of_truth_only` (true/false/null)
  - `null` or omitted = all customers
  - `true` = customers with **at least one** SOT integration
  - `false` = customers with **at least one** non-SOT integration
  
**Important Behavior**: 
- Customers can have 1-4 different integration types
- If a customer has **any** SOT integration, they appear in Master List
- If a customer has **any** non-SOT integration, they appear in Secondary List
- Customers with both SOT and non-SOT integrations will appear in **BOTH** lists
- This is the correct behavior - each list shows customers based on integration presence

### Store (`useCustomersStore.ts`)
- **Location**: `/home/kristian/reporting/frontend/src/stores/useCustomersStore.ts`
- **Current State**: Fetches all customers without SOT filtering
- **Unmapped Customers**: Separate fetch via `fetchUnmappedCustomers()`

---

## Proposed Changes

### 1. Sidebar Navigation Structure

#### A. Update AppSidebar.tsx
**File**: `/home/kristian/reporting/frontend/src/components/layout/AppSidebar.tsx`

**Changes**:
1. Replace single Customers menu item with collapsible menu group
2. Add two submenu items:
   - Master List → `/platform/customers/master`
   - Secondary Customers → `/platform/customers/secondary`
3. Import `Collapsible` from `@radix-ui/react-collapsible` or use existing sidebar submenu components
4. Add state management for expanded/collapsed submenu
5. Update `isActive()` function to handle submenu paths

**Implementation Pattern**:
```tsx
// Import additional icons
import { ChevronDown, ChevronRight, Users, Database, Building2 } from 'lucide-react'

// Add state for customers submenu
const [customersExpanded, setCustomersExpanded] = useState(false)

// Replace customers nav item with collapsible group
<Collapsible
  open={customersExpanded}
  onOpenChange={setCustomersExpanded}
>
  <CollapsibleTrigger>
    <SidebarMenuButton>
      <Users className="h-4 w-4" />
      <span>Customers</span>
      {customersExpanded ? <ChevronDown /> : <ChevronRight />}
    </SidebarMenuButton>
  </CollapsibleTrigger>
  <CollapsibleContent>
    <SidebarMenuSub>
      <SidebarMenuSubItem>
        <SidebarMenuSubButton asChild>
          <Link to="/platform/customers/master">
            <Database className="h-4 w-4" />
            <span>Master List</span>
          </Link>
        </SidebarMenuSubButton>
      </SidebarMenuSubItem>
      <SidebarMenuSubItem>
        <SidebarMenuSubButton asChild>
          <Link to="/platform/customers/secondary">
            <Building2 className="h-4 w-4" />
            <span>Secondary Customers</span>
          </Link>
        </SidebarMenuSubButton>
      </SidebarMenuSubItem>
    </SidebarMenuSub>
  </CollapsibleContent>
</Collapsible>
```

**Collapsed Sidebar Behavior**:
- When sidebar is collapsed (icon mode), show tooltip with "Customers" and submenu items
- Clicking opens a popover/dropdown with submenu options

---

### 2. Routing Updates

#### A. Update App.tsx Routes
**File**: `/home/kristian/reporting/frontend/src/App.tsx`

**Changes**:
1. Replace single `/platform/customers` route with two new routes
2. Keep existing detail routes unchanged

**New Routes**:
```tsx
// Remove old route (line 213-219)
// Add new routes:
<Route
  path="/platform/customers/master"
  element={
    <ProtectedRoute permission={{ section: 'customers', action: 'view' }}>
      <MasterCustomerList />
    </ProtectedRoute>
  }
/>

<Route
  path="/platform/customers/secondary"
  element={
    <ProtectedRoute permission={{ section: 'customers', action: 'view' }}>
      <SecondaryCustomerList />
    </ProtectedRoute>
  }
/>

// Redirect old path to master list for backwards compatibility
<Route
  path="/platform/customers"
  element={<Navigate to="/platform/customers/master" replace />}
/>

// Keep existing detail routes
<Route path="/platform/customers/:customerId/integrations" ... />
<Route path="/platform/customers/:id/configure-reports" ... />
<Route path="/platform/customers/:id/reports" ... />
```

---

### 3. Create New Page Components

#### A. Master Customer List Page
**File**: `/home/kristian/reporting/frontend/src/pages/customers/MasterCustomerList.tsx` (NEW)

**Purpose**: Show customers who have at least one Source of Truth integration + unmapped customers functionality

**Features**:
- Fetch customers with `source_of_truth_only=true`
- Shows customers with **at least one** SOT integration (they may also have non-SOT integrations)
- Include UnmappedBanner component (moved from CustomerList)
- Include MapCustomerModal for mapping workflow
- All existing filters and actions
- Same grid layout and modals
- Badge indicator if customer also appears in Secondary List

**Structure**:
```tsx
import FloatingLayout from "@/components/layout/FloatingLayout";
import {
    CustomersHeader,
    CustomersFilters,
    UnmappedBanner, // Keep this here
    CustomersGrid,
    MapCustomerModal,
    Pagination,
} from "./components";
// ... other imports

export default function MasterCustomerList() {
    const {
        // ... all hooks from useCustomerList but with SOT filter
    } = useMasterCustomerList(); // New hook

    return (
        <FloatingLayout>
            <div className="space-y-6 max-w-full overflow-hidden">
                <CustomersHeader 
                    title="Master Customer List"
                    subtitle="Customers from your Source of Truth integration"
                />

                <CustomersFilters ... />

                {/* Unmapped banner stays here */}
                <UnmappedBanner
                    count={unmappedCount}
                    onStartMapping={handleStartMapping}
                />

                <CustomersGrid ... />

                <Pagination ... />

                {/* All modals */}
                <MapCustomerModal ... />
                <EditCustomerModal ... />
                <ViewCustomerIntegrationsModal ... />
                <MergeCustomersModal ... />
                <SplitCustomerModal ... />
            </div>
        </FloatingLayout>
    );
}
```

#### B. Secondary Customer List Page
**File**: `/home/kristian/reporting/frontend/src/pages/customers/SecondaryCustomerList.tsx` (NEW)

**Purpose**: Show customers who have at least one non-Source of Truth integration

**Features**:
- Fetch customers with `source_of_truth_only=false`
- Shows customers with **at least one** non-SOT integration (they may also have SOT integrations)
- NO UnmappedBanner (only on Master List)
- Same filters and actions as Master List
- Same grid layout and modals
- Badge indicator if customer also appears in Master List

**Structure**:
```tsx
// Similar to MasterCustomerList but:
// 1. No UnmappedBanner
// 2. No MapCustomerModal (mapping only happens on Master List)
// 3. Different header title/subtitle
// 4. Uses useSecondaryCustomerList hook

export default function SecondaryCustomerList() {
    const {
        // ... hooks without unmapped customer logic
    } = useSecondaryCustomerList(); // New hook

    return (
        <FloatingLayout>
            <div className="space-y-6 max-w-full overflow-hidden">
                <CustomersHeader 
                    title="Secondary Customers"
                    subtitle="Customers from non-source of truth integrations"
                />

                <CustomersFilters ... />

                {/* NO UnmappedBanner here */}

                <CustomersGrid ... />

                <Pagination ... />

                {/* Modals (except MapCustomerModal) */}
                <EditCustomerModal ... />
                <ViewCustomerIntegrationsModal ... />
                <MergeCustomersModal ... />
                <SplitCustomerModal ... />
            </div>
        </FloatingLayout>
    );
}
```

---

### 4. Update Hooks

#### A. Create useMasterCustomerList Hook
**File**: `/home/kristian/reporting/frontend/src/pages/customers/hooks.ts`

**Changes**:
1. Duplicate `useCustomerList` hook
2. Rename to `useMasterCustomerList`
3. Add SOT filter to store fetch call

**Implementation**:
```tsx
export const useMasterCustomerList = () => {
  // ... same as useCustomerList but:
  
  useEffect(() => {
    // Fetch with SOT filter
    fetchCustomers(1, false, true) // Pass sourceOfTruthOnly=true
      .catch((err) => {
        toast.error(
          "Failed to load customers",
          err.response?.data?.message || err.message
        )
      })
  }, [fetchCustomers, toast])

  // ... rest of logic stays the same
  // Keep unmapped customer logic
}
```

#### B. Create useSecondaryCustomerList Hook
**File**: `/home/kristian/reporting/frontend/src/pages/customers/hooks.ts`

**Changes**:
1. Duplicate `useCustomerList` hook
2. Rename to `useSecondaryCustomerList`
3. Add SOT filter (false) to store fetch call
4. Remove unmapped customer logic

**Implementation**:
```tsx
export const useSecondaryCustomerList = () => {
  // ... same as useCustomerList but:
  
  useEffect(() => {
    // Fetch with SOT filter = false
    fetchCustomers(1, false, false) // Pass sourceOfTruthOnly=false
      .catch((err) => {
        toast.error(
          "Failed to load customers",
          err.response?.data?.message || err.message
        )
      })
  }, [fetchCustomers, toast])

  // Remove unmapped customer logic:
  // - No unmappedCount
  // - No unmappedCustomers
  // - No handleStartMapping
  // - No handleMapSuccess
  // - No MapCustomerModal state
}
```

---

### 5. Update Store

#### A. Update useCustomersStore
**File**: `/home/kristian/reporting/frontend/src/stores/useCustomersStore.ts`

**Changes**:
1. Update `fetchCustomers` to accept `sourceOfTruthOnly` parameter
2. Pass parameter to API call

**Implementation**:
```tsx
interface CustomersStore {
  // ... existing state
  
  // Update method signature
  fetchCustomers: (page?: number, silent?: boolean, sourceOfTruthOnly?: boolean | null) => Promise<void>
}

// In store implementation:
fetchCustomers: async (page = 1, silent = false, sourceOfTruthOnly = null) => {
  if (!silent) {
    set({ isLoading: true })
  }

  try {
    const response = await customerApi.getCustomers({
      page,
      search: get().searchQuery,
      statuses: get().statusFilters,
      report_filters: get().reportFilters,
      integration_types: get().integrationFilters,
      source_of_truth_only: sourceOfTruthOnly, // Add this parameter
    })

    set({
      customers: response.data,
      currentPage: response.pagination.current_page,
      totalPages: response.pagination.last_page,
      total: response.pagination.total,
      isLoading: false,
    })
  } catch (err) {
    console.error('Failed to fetch customers:', err)
    set({ isLoading: false })
    throw err
  }
}
```

---

### 6. Update API Service

#### A. Update customerApi.getCustomers
**File**: `/home/kristian/reporting/frontend/src/services/api/customers.ts` (or similar)

**Changes**:
1. Add `source_of_truth_only` to request parameters type
2. Include in query string if provided

**Implementation**:
```tsx
interface GetCustomersParams {
  page?: number
  search?: string
  statuses?: string[]
  report_filters?: string[]
  integration_types?: string[]
  source_of_truth_only?: boolean | null // Add this
}

export const getCustomers = async (params: GetCustomersParams) => {
  const queryParams = new URLSearchParams()
  
  if (params.page) queryParams.append('page', params.page.toString())
  if (params.search) queryParams.append('search', params.search)
  if (params.statuses?.length) {
    params.statuses.forEach(s => queryParams.append('statuses[]', s))
  }
  if (params.report_filters?.length) {
    params.report_filters.forEach(f => queryParams.append('report_filters[]', f))
  }
  if (params.integration_types?.length) {
    params.integration_types.forEach(t => queryParams.append('integration_types[]', t))
  }
  // Add SOT filter
  if (params.source_of_truth_only !== null && params.source_of_truth_only !== undefined) {
    queryParams.append('source_of_truth_only', params.source_of_truth_only.toString())
  }

  const response = await api.get(`/customers?${queryParams.toString()}`)
  return response.data
}
```

---

### 7. Update Components

#### A. Update CustomersHeader Component
**File**: `/home/kristian/reporting/frontend/src/pages/customers/components/CustomersHeader.tsx`

**Changes**:
1. Accept optional `title` and `subtitle` props
2. Use props if provided, otherwise use defaults

**Implementation**:
```tsx
interface CustomersHeaderProps {
  title?: string
  subtitle?: string
}

export function CustomersHeader({ 
  title = "Customers", 
  subtitle = "Manage your customer mappings and integrations" 
}: CustomersHeaderProps) {
  return (
    <div className="space-y-2">
      <h1 className="text-3xl font-bold tracking-tight">{title}</h1>
      <p className="text-muted-foreground">{subtitle}</p>
    </div>
  )
}
```

---

### 8. Naming Suggestion for Second Menu

**Options for "Secondary Customers" name**:
1. **Secondary Customers** - Clear but generic
2. **Other Integrations** - Descriptive but vague
3. **Additional Sources** - Professional
4. **Non-Primary Customers** - Accurate but wordy
5. **Reference Data** - Technical
6. **Supplementary Customers** - Formal
7. **External Sources** - Clear purpose

**Recommended**: **"Secondary Customers"** or **"Additional Sources"**

---

## Implementation Checklist

### Phase 1: Backend Verification
- [x] Backend API filter implemented (`source_of_truth_only` parameter)
- [ ] Test API with all three states (null, true, false)
- [ ] Verify pagination works with filter
- [ ] Verify other filters work in combination with SOT filter

### Phase 2: Sidebar Navigation
- [ ] Update `AppSidebar.tsx` with collapsible Customers menu
- [ ] Add submenu items (Master List, Secondary Customers)
- [ ] Implement expand/collapse state management
- [ ] Handle collapsed sidebar behavior (tooltips/popovers)
- [ ] Update `isActive()` function for submenu paths
- [ ] Test navigation in both expanded and collapsed states

### Phase 3: Routing
- [ ] Update `App.tsx` with new routes
- [ ] Add redirect from old `/platform/customers` to `/platform/customers/master`
- [ ] Verify all existing detail routes still work
- [ ] Test navigation from sidebar to pages

### Phase 4: API Service
- [ ] Update API service types to include `source_of_truth_only`
- [ ] Update `getCustomers` function to pass SOT filter
- [ ] Test API calls with different filter values

### Phase 5: Store Updates
- [ ] Update `useCustomersStore` to accept `sourceOfTruthOnly` parameter
- [ ] Update `fetchCustomers` method signature
- [ ] Pass SOT filter to API service
- [ ] Test store with different filter values

### Phase 6: New Page Components
- [ ] Create `MasterCustomerList.tsx`
- [ ] Create `SecondaryCustomerList.tsx`
- [ ] Move UnmappedBanner to Master List only
- [ ] Update headers with appropriate titles/subtitles
- [ ] Test both pages independently

### Phase 7: Hooks
- [ ] Create `useMasterCustomerList` hook
- [ ] Create `useSecondaryCustomerList` hook
- [ ] Remove unmapped logic from secondary hook
- [ ] Test hooks with proper SOT filtering

### Phase 8: Component Updates
- [ ] Update `CustomersHeader` to accept title/subtitle props
- [ ] Verify all shared components work on both pages
- [ ] Test all modals on both pages

### Phase 9: Testing
- [ ] Test Master List shows only SOT customers
- [ ] Test Secondary List shows only non-SOT customers
- [ ] Test unmapped customers only appear on Master List
- [ ] Test mapping workflow on Master List
- [ ] Test all filters work on both pages
- [ ] Test pagination on both pages
- [ ] Test all customer actions (edit, merge, split) on both pages
- [ ] Test navigation between pages
- [ ] Test sidebar expand/collapse
- [ ] Test permissions still work correctly

### Phase 10: Cleanup
- [ ] Remove old `CustomerList.tsx` (if not needed)
- [ ] Update any imports referencing old component
- [ ] Remove unused code
- [ ] Update any documentation

---

## Potential Issues & Solutions

### Issue 1: Customers Appearing in Both Lists ✅ EXPECTED BEHAVIOR
**Scenario**: If a customer has integrations from both SOT and non-SOT sources, they will appear in both lists.

**Why This Is Correct**: 
- Backend query uses `whereHas` which checks if ANY integration matches the filter
- A customer with 4 integrations (2 SOT, 2 non-SOT) should appear in BOTH lists
- Master List shows them because they have SOT integrations
- Secondary List shows them because they have non-SOT integrations
- This allows users to see all relevant data for each integration type

**UI Considerations**:
- Add badge or indicator showing "Also in Secondary List" on Master List cards
- Add badge or indicator showing "Also in Master List" on Secondary List cards
- In customer detail view, clearly show which integrations are SOT vs non-SOT
- Consider adding a filter toggle to hide/show customers that appear in both lists

### Issue 2: Unmapped Customers from Non-SOT Integrations
**Problem**: What happens to unmapped customers from non-SOT integrations?

**Solution**:
- Only show unmapped customers from SOT integrations on Master List
- Non-SOT unmapped customers can be handled separately or ignored
- Update `fetchUnmappedCustomers` API to filter by SOT if needed

### Issue 3: Sidebar State Persistence
**Problem**: Customers submenu expand/collapse state not persisting across page refreshes.

**Solution**:
- Store submenu state in localStorage
- Auto-expand if user is on a customers submenu page
- Use existing sidebar state management pattern

### Issue 4: Mobile Navigation
**Problem**: Collapsible submenu might be awkward on mobile.

**Solution**:
- Test mobile sidebar behavior
- Consider flat menu on mobile if needed
- Use existing mobile sidebar patterns from shadcn/ui

### Issue 5: Backwards Compatibility
**Problem**: External links or bookmarks to `/platform/customers` will break.

**Solution**:
- Add redirect route from old path to Master List
- Update any internal links in codebase
- Add migration notice if needed

---

## Testing Strategy

### Unit Tests
- Test hooks with different SOT filter values
- Test store methods with SOT parameter
- Test API service parameter handling

### Integration Tests
- Test navigation flow between pages
- Test filter combinations with SOT filter
- Test pagination with SOT filter

### E2E Tests
- Test complete mapping workflow on Master List
- Test customer actions on both pages
- Test sidebar navigation and state
- Test mobile responsiveness

### Manual Testing Checklist
- [ ] Navigate to Master List from sidebar
- [ ] Navigate to Secondary List from sidebar
- [ ] Verify correct customers appear on each page
- [ ] Test unmapped banner only on Master List
- [ ] Map an unmapped customer on Master List
- [ ] Edit customer on both pages
- [ ] Merge customers on both pages
- [ ] Split customer on both pages
- [ ] Test all filters on both pages
- [ ] Test pagination on both pages
- [ ] Test search on both pages
- [ ] Collapse sidebar and test navigation
- [ ] Test on mobile device
- [ ] Test with different permission levels

---

## Rollback Plan

If issues arise:
1. Revert sidebar changes (restore single Customers menu item)
2. Revert routing changes (restore single `/platform/customers` route)
3. Keep old `CustomerList.tsx` as fallback
4. Backend API filter is backwards compatible (optional parameter)

---

## Estimated Timeline

- **Phase 1 (Backend Verification)**: 30 minutes
- **Phase 2 (Sidebar)**: 2 hours
- **Phase 3 (Routing)**: 30 minutes
- **Phase 4 (API Service)**: 30 minutes
- **Phase 5 (Store)**: 1 hour
- **Phase 6 (Pages)**: 2 hours
- **Phase 7 (Hooks)**: 1 hour
- **Phase 8 (Components)**: 30 minutes
- **Phase 9 (Testing)**: 3 hours
- **Phase 10 (Cleanup)**: 30 minutes

**Total Estimated Time**: ~12 hours

---

## Future Enhancements

1. **Badge Counts**: Show customer count on each submenu item
2. **Quick Switch**: Add toggle to switch between lists without sidebar
3. **Combined View**: Option to see all customers with SOT indicator
4. **Bulk Actions**: Move customers between SOT and non-SOT integrations
5. **Analytics**: Track which list is used more frequently
6. **Customization**: Allow users to rename submenu items
7. **Filters Persistence**: Remember filter state per list type
