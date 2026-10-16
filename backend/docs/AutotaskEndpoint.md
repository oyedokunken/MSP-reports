# Autotask API Endpoints Documentation

## TTS Production Integration Configuration

**Integration Type:** Autotask PSA

**Display Name:** TTS Autotask PSA Production

**Status:** Active

**Source of Truth:** Yes (Primary customer data source)

### Credentials
```json
{
  "username": "<api-user@your-domain>",
  "secret": "<api-secret>",
  "api_integration_code": "<integration-code>",
  "endpoint": "https://webservices26.autotask.net/atservicesrest/v1.0"
}
```

### Configuration
```json
{
  "sync_interval": 3600,
  "sync_tickets": true,
  "sync_time_entries": true,
  "sync_contracts": false
}
```

---

## Base URL
```
https://webservices26.autotask.net/atservicesrest/v1.0
```

## Authentication Headers (Required for ALL requests)
```
Username: <api-user@your-domain>
Secret: <api-secret>
APIIntegrationcode: <integration-code>
Content-Type: application/json
```

---

## 1. Tickets Query (POST)
**Endpoint:** `/Tickets/query`

**Method:** POST

**Body Required:** Yes

**Purpose:** Retrieve ticket records with filtering and pagination

**Request Body:**
```json
{
    "filter": [
        {
            "field": "id",
            "op": "gt",
            "value": 0
        },
        {
            "field": "createDate",
            "op": "gte",
            "value": "2024-01-01T00:00:00Z"
        }
    ],
    "MaxRecords": 100,
    "SortBy": "createDate",
    "SortOrder": "ASC"
}
```

**Response Example:**
```json
{
    "items": [
        {
            "id": 7689,
            "ticketNumber": "T20240625.0001",
            "title": "Milestone Headend works",
            "description": "From: James Cussen\nSent: Wednesday, 19 June 2024...",
            "companyID": 178,
            "companyLocationID": 9,
            "contactID": 30682936,
            "assignedResourceID": 29682897,
            "assignedResourceRoleID": 29683355,
            "creatorResourceID": 29682887,
            "completedByResourceID": 29682887,
            "status": 5,
            "priority": 3,
            "ticketType": 1,
            "ticketCategory": 3,
            "issueType": 13,
            "subIssueType": 166,
            "source": 4,
            "queueID": 29683485,
            "contractID": 29683383,
            "billingCodeID": 29683498,
            "createDate": "2024-06-24T21:13:33.913Z",
            "dueDateTime": "2024-12-04T02:30:00.000Z",
            "completedDate": "2024-12-03T20:55:16.633Z",
            "resolvedDateTime": "2024-12-03T20:55:16.650Z",
            "lastActivityDate": "2024-12-03T21:40:55.070Z",
            "estimatedHours": 0.5000,
            "hoursToBeScheduled": 0.0000,
            "purchaseOrderNumber": "PO046313",
            "resolution": "",
            "userDefinedFields": []
        }
    ]
}
```

**Key Fields:**
- `id` - Unique ticket identifier
- `ticketNumber` - Human-readable ticket number (e.g., T20240625.0001)
- `companyID` - Links to Companies table (customer)
- `assignedResourceID` - Links to Resources table (technician)
- `status` - Ticket status (5 = Complete)
- `priority` - Priority level (1-4, lower = higher priority)
- `createDate` - When ticket was created
- `completedDate` - When ticket was completed

**Filter Operators:**
- `gt` - Greater than
- `gte` - Greater than or equal
- `lt` - Less than
- `lte` - Less than or equal
- `eq` - Equal
- `ne` - Not equal

**Used in Code:** Lines 736, 896

---

## 2. Ticket Notes Query (POST)
**Endpoint:** `/TicketNotes/query`

**Method:** POST

**Body Required:** Yes

**Purpose:** Retrieve notes/comments associated with tickets

**Request Body:**
```json
{
    "filter": [
        {
            "field": "id",
            "op": "gt",
            "value": 0
        },
        {
            "field": "createDateTime",
            "op": "gte",
            "value": "2024-01-01T00:00:00Z"
        }
    ],
    "MaxRecords": 100,
    "SortBy": "createDateTime",
    "SortOrder": "ASC"
}
```

**Response Example:**
```json
{
    "items": [
        {
            "id": 29683367,
            "ticketID": 7689,
            "title": "Workflow Rule \"On Hold\" fired.",
            "description": "When a ticket is:\r\nEdited\r\nAnd the following conditions are met:\r\nQueue not equal to  \"On Hold\"\r\nStatus in list \"Awaiting Parts or Waiting Customer or Awaiting Third Party or On Test\"\r\nThen execute the following actions:\r\nAssign To Queue \"On Hold\"\r\nInitiated by Kristy Fagan",
            "noteType": 13,
            "publish": 1,
            "createDateTime": "2024-06-25T02:10:57.583Z",
            "lastActivityDate": "2024-06-25T02:10:57.583Z",
            "creatorResourceID": 4,
            "createdByContactID": null,
            "impersonatorCreatorResourceID": null,
            "impersonatorUpdaterResourceID": null
        },
        {
            "id": 29683368,
            "ticketID": 7689,
            "title": "Service called scheduled",
            "description": "Service call has been scheduled. Automatic change of status and queue.",
            "noteType": 91,
            "publish": 2,
            "createDateTime": "2024-06-25T04:11:40.853Z",
            "lastActivityDate": "2024-06-25T04:11:40.853Z",
            "creatorResourceID": 4,
            "createdByContactID": null,
            "impersonatorCreatorResourceID": null,
            "impersonatorUpdaterResourceID": null
        }
    ]
}
```

**Key Fields:**
- `id` - Unique note identifier
- `ticketID` - Links to parent ticket
- `noteType` - Type of note (13 = Workflow, 91 = System, etc.)
- `publish` - Visibility (1 = Internal, 2 = All, 3 = Customer Portal)
- `creatorResourceID` - Who created the note

**Used in Code:** Line 131

---

## 3. Companies Query (POST)
**Endpoint:** `/Companies/query`

**Method:** POST

**Body Required:** Yes

**Purpose:** Retrieve customer/company records (primary customer data source)

**Request Body:**
```json
{
    "filter": [
        {
            "field": "id",
            "op": "gt",
            "value": 0
        }
    ],
    "MaxRecords": 100
}
```

**Response Example:**
```json
{
    "items": [
        {
            "id": 174,
            "companyName": "Autotask Corporation",
            "companyNumber": "AUTO",
            "companyType": 1,
            "isActive": true,
            "address1": "26 Tech Valley Drive",
            "address2": "Suite 2",
            "city": "East Greenbush",
            "state": "NY",
            "postalCode": "12061",
            "countryID": 237,
            "phone": "(518) 720-3500",
            "fax": "",
            "webAddress": "www.autotask.com",
            "billingAddress1": "26 Tech Valley Drive",
            "billingAddress2": "Suite 2",
            "billToCity": "East Greenbush",
            "billToState": "NY",
            "billToZipCode": "12061",
            "billToCountryID": 237,
            "classification": 200,
            "companyCategoryID": 1,
            "ownerResourceID": 4,
            "createDate": "2002-05-16T10:56:33.810Z",
            "lastActivityDate": "2024-05-15T06:04:42.810Z",
            "lastTrackedModifiedDateTime": "2024-04-28T04:16:25.077Z",
            "isClientPortalActive": true,
            "isTaxExempt": false,
            "currencyID": 1,
            "userDefinedFields": []
        }
    ]
}
```

**Key Fields:**
- `id` - Unique company identifier (used as external_id in IntegrationCustomer)
- `companyName` - Customer name (canonical name)
- `companyNumber` - Short code/reference
- `isActive` - Whether company is active
- `companyType` - Type (1 = Customer, 2 = Lead, etc.)
- `ownerResourceID` - Account manager

**Used in Code:** Line 461

---

## 4. Contracts Query (POST)
**Endpoint:** `/Contracts/query`

**Method:** POST

**Body Required:** Yes

**Purpose:** Retrieve contract records (service agreements, support contracts)

**Request Body:**
```json
{
    "filter": [
        {
            "field": "id",
            "op": "gt",
            "value": 0
        }
    ],
    "MaxRecords": 100,
    "SortBy": "id",
    "SortOrder": "ASC"
}
```

**Response Example:**
```json
{
    "items": [
        {
            "id": 29683348,
            "billingPreference": 2,
            "billToCompanyContactID": null,
            "billToCompanyID": null,
            "companyID": 0,
            "contactID": null,
            "contactName": null,
            "contractCategory": null,
            "contractExclusionSetID": null,
            "contractName": "TTS Internal",
            "contractNumber": null,
            "contractPeriodType": null,
            "contractType": 1,
            "description": "Ensuring all work is non billable",
            "endDate": "2026-06-30T00:00:00.000Z",
            "estimatedCost": 0.0000,
            "estimatedHours": 0.0000,
            "estimatedRevenue": 0.00,
            "exclusionContractID": null,
            "internalCurrencyOverageBillingRate": null,
            "internalCurrencySetupFee": 0.0000,
            "isCompliant": true,
            "isDefaultContract": true,
            "lastModifiedDateTime": "2025-05-28T23:49:39.380Z",
            "opportunityID": null,
            "organizationalLevelAssociationID": null,
            "overageBillingRate": null,
            "purchaseOrderNumber": "",
            "renewedContractID": null,
            "serviceLevelAgreementID": null,
            "setupFee": 0.0000,
            "setupFeeBillingCodeID": null,
            "startDate": "2024-01-01T00:00:00.000Z",
            "status": 1,
            "timeReportingRequiresStartAndStopTimes": 1,
            "userDefinedFields": []
        }
    ]
}
```

**Key Fields:**
- `id` - Unique contract identifier
- `contractName` - Contract name/title
- `companyID` - Links to Companies table (customer)
- `contractType` - Type (1 = Time & Materials, 2 = Fixed Price, etc.)
- `status` - Contract status (1 = Active)
- `startDate` - Contract start date
- `endDate` - Contract end date
- `isDefaultContract` - Whether this is the default contract for the company
- `estimatedHours` - Total hours allocated
- `estimatedRevenue` - Expected revenue

---

**Used in Code:** TBD (Contracts sync to be implemented)

---

## 5. Contacts Query (POST)
**Endpoint:** `/Contacts/query`

**Method:** POST

**Body Required:** Yes

**Request Body:**
```json
{
    "filter": [
        {
            "field": "id",
            "op": "gt",
            "value": 0
        }
    ],
    "MaxRecords": 100,
    "SortBy": "id",
    "SortOrder": "ASC"
}
```

**Used in Code:** Line 225

---

## 6. Resources Query (POST)
**Endpoint:** `/Resources/query`

**Method:** POST

**Body Required:** Yes

**Purpose:** Retrieve employee/technician records (for ticket assignment and time entry tracking)

**Request Body:**
```json
{
    "filter": [
        {
            "field": "id",
            "op": "gt",
            "value": 0
        }
    ],
    "MaxRecords": 100
}
```

**Response Example:**
```json
{
    "items": [
        {
            "id": 4,
            "accountingReferenceID": "",
            "dateFormat": "MM/dd/yyyy",
            "defaultServiceDeskRoleID": null,
            "email": "",
            "email2": "",
            "email3": "",
            "emailTypeCode": "PRIMARY",
            "emailTypeCode2": null,
            "emailTypeCode3": null,
            "firstName": "Autotask",
            "gender": null,
            "greeting": null,
            "hireDate": "0001-01-01T00:00:00.000Z",
            "homePhone": "",
            "initials": "",
            "internalCost": 1.0000,
            "isActive": true,
            "lastName": "Administrator",
            "licenseType": 1,
            "locationID": 90682,
            "middleName": "",
            "mobilePhone": "",
            "numberFormat": "X,XXX.XX",
            "officeExtension": "",
            "officePhone": "(518) 720-3500",
            "payrollIdentifier": "",
            "payrollType": 1,
            "resourceType": "Employee",
            "suffix": null,
            "surveyResourceRating": null,
            "timeFormat": "hh:mm tt",
            "title": "",
            "travelAvailabilityPct": null,
            "userName": "administrator",
            "userType": 10
        },
        {
            "id": 29682885,
            "accountingReferenceID": "",
            "dateFormat": "dd/MM/yyyy",
            "defaultServiceDeskRoleID": 29683355,
            "email": "michael@techsolutions.com.au",
            "email2": "",
            "email3": "",
            "emailTypeCode": "PRIMARY",
            "emailTypeCode2": null,
            "emailTypeCode3": null,
            "firstName": "Michael",
            "gender": "M",
            "greeting": null,
            "hireDate": "2012-12-01T00:00:00.000Z",
            "homePhone": "",
            "initials": "12",
            "internalCost": 0.0000,
            "isActive": true,
            "lastName": "Feldbauer",
            "licenseType": 1,
            "locationID": 90682,
            "middleName": "",
            "mobilePhone": "0409321976",
            "numberFormat": "X,XXX.XX",
            "officeExtension": "",
            "officePhone": "0889442222",
            "payrollIdentifier": "12",
            "payrollType": 1,
            "resourceType": "Employee",
            "suffix": null,
            "surveyResourceRating": null,
            "timeFormat": "hh:mm tt",
            "title": "Managing Director",
            "travelAvailabilityPct": null,
            "userName": "michael",
            "userType": 29
        }
    ]
}
```

**Key Fields:**
- `id` - Unique resource identifier
- `firstName`, `lastName`, `middleName` - Full name
- `userName` - Login username
- `email`, `email2`, `email3` - Email addresses
- `emailTypeCode` - Email type (PRIMARY, etc.)
- `title` - Job title
- `officePhone`, `mobilePhone`, `homePhone` - Contact numbers
- `officeExtension` - Phone extension
- `isActive` - Whether resource is active
- `resourceType` - Type (Employee, Contractor, etc.)
- `userType` - User type code
- `licenseType` - License type (1 = Full, etc.)
- `locationID` - Office location
- `hireDate` - Date hired
- `internalCost` - Hourly cost rate
- `payrollType` - Payroll type
- `payrollIdentifier` - Payroll ID
- `defaultServiceDeskRoleID` - Default role for service desk
- `gender` - Gender (M/F)
- `initials` - Employee initials
- `accountingReferenceID` - Accounting system reference
- `dateFormat`, `timeFormat`, `numberFormat` - User preferences

**Used in Code:** Line 543

---

## 7. Time Entries Query (POST)
**Endpoint:** `/TimeEntries/query`

**Method:** POST

**Body Required:** Yes

**Purpose:** Retrieve time entry records (billable/non-billable hours worked on tickets)

**Request Body:**
```json
{
    "filter": [
        {
            "field": "id",
            "op": "gt",
            "value": 0
        },
        {
            "field": "dateWorked",
            "op": "gte",
            "value": "2024-01-01T00:00:00Z"
        }
    ],
    "MaxRecords": 100
}
```

**Response Example:**
```json
{
    "items": [
        {
            "id": 7,
            "ticketID": 7783,
            "resourceID": 29682888,
            "roleID": 29683355,
            "dateWorked": "2024-06-24T00:00:00.000Z",
            "startDateTime": "2024-06-24T03:15:00.000Z",
            "endDateTime": "2024-06-24T04:15:00.000Z",
            "hoursWorked": 1.0000,
            "hoursToBill": 1.0000,
            "offsetHours": 0.0000,
            "isNonBillable": false,
            "billingCodeID": 29682800,
            "internalBillingCodeID": 29682800,
            "summaryNotes": "**Onsite**\n\nAttended site and checked alarm panel. Found warehouse slam is offline. Pulled power to reset and confirmed now functional. Checked and updated controller firmware. All ok. Tested and confirmed functioning as normal.\n\nFound the slam was offline but the ethernet bridge was online, so issue is with the slam. Need to raise job to see if replaceable under warranty. More time required.\n\n**In Progress**",
            "internalNotes": null,
            "showOnInvoice": true,
            "timeEntryType": 2,
            "createDateTime": "2024-06-28T03:46:14.710Z",
            "lastModifiedDateTime": "2024-06-28T03:46:14.710Z",
            "creatorUserID": 29682888,
            "lastModifiedUserID": 29682888,
            "contractID": null,
            "contractServiceID": null,
            "contractServiceBundleID": null,
            "billingApprovalDateTime": null,
            "billingApprovalResourceID": null,
            "billingApprovalLevelMostRecent": 0
        }
    ]
}
```

**Key Fields:**
- `id` - Unique time entry identifier
- `ticketID` - Links to parent ticket
- `resourceID` - Links to Resources table (who worked)
- `dateWorked` - Date work was performed
- `hoursWorked` - Actual hours worked
- `hoursToBill` - Billable hours
- `isNonBillable` - Whether entry is non-billable
- `billingCodeID` - Billing/task code
- `summaryNotes` - Work description

**Used in Code:** Line 642

---

## 8. Entity Field Information (GET)
**Endpoint:** `/{EntityType}/EntityInformation/fields`

**Method:** GET

**Body Required:** No

**Purpose:** Retrieve metadata about entity fields including data types, picklist values, and field constraints

**Entity Types:**
- `Tickets`
- `Companies`
- `Contracts`
- `Resources`
- `TimeEntries`
- `Contacts`
- `TicketNotes`

**Example URLs:**
```
https://webservices26.autotask.net/atservicesrest/v1.0/Tickets/EntityInformation/fields
https://webservices26.autotask.net/atservicesrest/v1.0/Companies/EntityInformation/fields
https://webservices26.autotask.net/atservicesrest/v1.0/Contracts/EntityInformation/fields
https://webservices26.autotask.net/atservicesrest/v1.0/Resources/EntityInformation/fields
https://webservices26.autotask.net/atservicesrest/v1.0/TimeEntries/EntityInformation/fields
https://webservices26.autotask.net/atservicesrest/v1.0/Contacts/EntityInformation/fields
```

**Response Example:**
```json
{
    "fields": [
        {
            "name": "status",
            "dataType": "integer",
            "length": 0,
            "isRequired": true,
            "isReadOnly": false,
            "isQueryable": true,
            "isReference": false,
            "referenceEntityType": "",
            "isPickList": true,
            "picklistValues": [
                {
                    "value": "1",
                    "label": "New",
                    "isDefaultValue": false,
                    "sortOrder": 1,
                    "parentValue": "",
                    "isActive": true,
                    "isSystem": true
                },
                {
                    "value": "5",
                    "label": "Complete",
                    "isDefaultValue": false,
                    "sortOrder": 5,
                    "parentValue": "",
                    "isActive": true,
                    "isSystem": true
                }
            ],
            "picklistParentValueField": "",
            "isSupportedWebhookField": false
        },
        {
            "name": "changeApprovalStatus",
            "dataType": "integer",
            "length": 0,
            "isRequired": false,
            "isReadOnly": false,
            "isQueryable": true,
            "isReference": false,
            "referenceEntityType": "",
            "isPickList": true,
            "picklistValues": [
                {
                    "value": "1",
                    "label": "Requested",
                    "isDefaultValue": false,
                    "sortOrder": 0,
                    "parentValue": "",
                    "isActive": true,
                    "isSystem": true
                },
                {
                    "value": "5",
                    "label": "Approved",
                    "isDefaultValue": false,
                    "sortOrder": 0,
                    "parentValue": "",
                    "isActive": true,
                    "isSystem": true
                },
                {
                    "value": "6",
                    "label": "Rejected",
                    "isDefaultValue": false,
                    "sortOrder": 0,
                    "parentValue": "",
                    "isActive": true,
                    "isSystem": true
                }
            ],
            "picklistParentValueField": "",
            "isSupportedWebhookField": false
        }
    ]
}
```

**Use Cases:**
- Decode status codes (e.g., status 5 = "Complete")
- Decode priority codes (e.g., priority 3 = "Normal")
- Validate field values before creating/updating records
- Build dynamic UI forms

**Used in Code:** Line 308

---

## Complete Endpoint Summary

| Endpoint | Method | Body Required | Purpose | Sync Priority | Code Line |
|----------|--------|---------------|---------|---------------|----------|
| `/Companies/query` | POST | Yes | Get customer/company records | Critical (Source of Truth) | 461 |
| `/Tickets/query` | POST | Yes | Get ticket records | High (Enabled) | 736, 896 |
| `/TimeEntries/query` | POST | Yes | Get time entry records | High (Enabled) | 642 |
| `/Contracts/query` | POST | Yes | Get contract records | Medium (To be enabled) | TBD |
| `/Resources/query` | POST | Yes | Get resource (employee) records | Medium | 543 |
| `/TicketNotes/query` | POST | Yes | Get ticket notes | Medium | 131 |
| `/Contacts/query` | POST | Yes | Get contact records | Medium | 225 |
| `/{Entity}/EntityInformation/fields` | GET | No | Get field metadata/schema | Low (Metadata) | 308 |

**Sync Configuration:**
- `sync_tickets`: true
- `sync_time_entries`: true
- `sync_contracts`: false (to be enabled)
- `sync_interval`: 3600 seconds (1 hour)

---

## Common Patterns

### Pagination
All query endpoints support pagination using the `id` field:
```json
{
    "filter": [
        {
            "field": "id",
            "op": "gt",
            "value": 12345
        }
    ],
    "MaxRecords": 100
}
```

After processing results, use the last record's `id` as the new `value` for the next request.

### Date Filtering
Dates must be in ISO 8601 format with Z suffix:
```
2024-01-01T00:00:00Z
```

Common date fields:
- `createDate` - When record was created
- `createDateTime` - When record was created (with time)
- `dateWorked` - When work was performed (time entries)
- `lastActivityDate` - Last activity on record

### Sorting
```json
{
    "SortBy": "createDate",
    "SortOrder": "ASC"
}
```

Options:
- `SortOrder`: `ASC` or `DESC`
- `SortBy`: Any queryable field name

---

## Error Responses

### Missing Body Error
```json
{
    "errors": [
        "Value cannot be null.\r\nParameter name: model"
    ]
}
```
**Solution:** Add required request body with filter and MaxRecords

### Authentication Error
```json
{
    "errors": [
        "Invalid credentials"
    ]
}
```
**Solution:** Check Username, Secret, and APIIntegrationcode headers

### Rate Limit Error
**Rate Limit:** 10,000 requests per hour

**Solution:** Implement rate limiting and retry logic

---

## Testing with cURL

### GET Request (EntityInformation)
```bash
curl -X GET "https://webservices26.autotask.net/atservicesrest/v1.0/Tickets/EntityInformation/fields" \
  -H "Username: your-email@example.com" \
  -H "Secret: your-secret" \
  -H "APIIntegrationcode: your-integration-code" \
  -H "Content-Type: application/json"
```

### POST Request (Query)
```bash
curl -X POST "https://webservices26.autotask.net/atservicesrest/v1.0/Companies/query" \
  -H "Username: your-email@example.com" \
  -H "Secret: your-secret" \
  -H "APIIntegrationcode: your-integration-code" \
  -H "Content-Type: application/json" \
  -d '{
    "filter": [
        {
            "field": "id",
            "op": "gt",
            "value": 0
        }
    ],
    "MaxRecords": 100
}'
```

---

## Important Ticket Status Codes

Based on EntityInformation response:

| Code | Label | Description |
|------|-------|-------------|
| 1 | New | Newly created ticket |
| 2 | In Progress | Work started |
| 5 | Complete | Ticket completed |
| 8 | Waiting Customer | Awaiting customer response |
| 9 | Waiting Vendor | Awaiting third party |

## Important Priority Codes

| Code | Label | Description |
|------|-------|-------------|
| 1 | Critical | Highest priority |
| 2 | High | High priority |
| 3 | Medium/Normal | Standard priority |
| 4 | Low | Lowest priority |

## Data Mapping for Reports

### Ticket Summary Report
**Data Sources:**
- Tickets → `IntegrationData` (data_type: 'ticket')
- TimeEntries → `IntegrationData` (data_type: 'time_entry')

**Key Mappings:**
```
Ticket.companyID → IntegrationCustomer.external_id → Customer.customer_id
Ticket.assignedResourceID → Resource.id (for technician name)
Ticket.status → Status label (via EntityInformation)
TimeEntry.ticketID → Ticket.id (link time to ticket)
TimeEntry.resourceID → Resource.id (who worked)
```

**Metrics Calculated:**
- Total tickets (count)
- Completed tickets (status = 5)
- Open tickets (status != 5)
- Average resolution time (completedDate - createDate)
- Total hours (sum of hoursWorked)
- Billable hours (sum where isNonBillable = false)
- Non-billable hours (sum where isNonBillable = true)
- Billing efficiency (billable / total * 100)

### Customer Mapping
**Source of Truth:** Autotask Companies

**Process:**
1. Sync Companies → Create/update IntegrationCustomer records
2. Auto-match IntegrationCustomer.name to existing Customer.canonical_name
3. Create CustomerMapping linking IntegrationCustomer to Customer
4. Use companyID from Tickets/TimeEntries to link data to correct customer

---

## Notes

- All query endpoints use POST method (not GET)
- Only EntityInformation endpoints use GET method
- MaxRecords limit is typically 500 per request
- Rate limit is 10,000 requests per hour
- Dates are always in UTC with Z suffix
- The base URL may vary (webservices26, webservices2, etc.) - check your Autotask instance
- **TTS Production uses:** webservices26.autotask.net
- Autotask is configured as **Source of Truth** for customer data
- Historical data import: 90 days recommended initially
