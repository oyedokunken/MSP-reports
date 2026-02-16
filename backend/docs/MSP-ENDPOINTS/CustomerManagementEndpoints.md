# Customer Management API Endpoints

## Base URL

`/api`

## Authentication

All endpoints require authentication (Sanctum) and appropriate permissions (`customers,view`, `customers,map`, `customers,edit`).

---

## 1. List All Customers

**Endpoint:** `GET /customers`

**Description:** Retrieves a paginated list of all customers, with optional filtering.

**Permission Required:** `customers,view`

**Query Parameters:**

- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 15)
- `search` (optional): Search by customer name
- `statuses` (optional): Array of statuses to filter by
- `source_of_truth_only` (optional): Boolean.
    - `true`: Return only customers that are "Source of Truth" (linked to SOT integration or static).
    - `false`: Return only customers that are NOT "Source of Truth".
- `integration_types` (optional): Filter by linked integration types.
- `report_filters` (optional): Filter by report configuration status ('configured', 'not-configured').

---

## 2. List Unmapped Customers

**Endpoint:** `GET /customers/unmapped`

**Description:** Retrieves a list of integration customers that have not yet been mapped to a canonical customer. Includes smart suggestions for matching.

**Permission Required:** `customers,map`

**Query Parameters:**

- `page` (optional): Page number
- `per_page` (optional): Items per page
- `search` (optional): Search by name or external ID
- `skip_suggestions` (optional): Boolean. If true, skips high-cost smart matching logic.

---

## 3. Map Customer

**Endpoint:** `POST /customers/map`

**Description:** Maps an unmapped integration customer to a canonical customer (either existing or new).

**Permission Required:** `customers,map`

**Request Body:**

- `integration_customer_id` (required): ID of the unmapped integration customer.
- `customer_id` (optional): ID of the existing canonical customer to map to.
- `create_new` (optional): Boolean. If true, creates a new canonical customer.
- `canonical_name` (optional): Name for the new customer (if creating new).
- `status` (optional): Status for the new customer.

---

## 4. Create Static Customer

**Endpoint:** `POST /customers/static`

**Description:** Creates a "Static" customer that is not linked to any external integration.

**Permission Required:** `customers,map`

**Request Body:**

- `canonical_name` (required): Name of the customer.
- `status` (optional): Initial status (default: 'active').

---

## 5. View Customer Integrations

**Endpoint:** `GET /customers/{id}/integrations`

**Description:** Retrieves all integration mappings for a specific customer.

**Permission Required:** `customers,view`

---

## 6. Update Customer Details

**Endpoint:** `PATCH /customers/{id}`

**Description:** Updates the details of a canonical customer.

**Permission Required:** `customers,edit`

**Request Body:**

- `canonical_name` (optional): New name.
- `status` (optional): New status.
- `report_destination` (optional): Email destination for reports.

---

## 7. Merge Customers

**Endpoint:** `POST /customers/{id}/merge`

**Description:** Merges another customer into the target customer (`{id}`). The "other" customer will be deleted, and its mappings moved to `{id}`.

**Permission Required:** `customers,edit`

**Request Body:**

- `merge_customer_id` (required): ID of the customer to be merged (will be deleted).

---

## 8. Split Customer

**Endpoint:** `POST /customers/{id}/split`

**Description:** Splits specific integration mappings out of a customer into a new customer. Used to fix incorrect mappings.

**Permission Required:** `customers,edit`

**Request Body:**

- `new_customer_name` (required): Name for the new customer.
- `integration_customer_ids` (required): Array of integration mapping IDs to move to the new customer.

---

## 9. Delete Mapping

**Endpoint:** `DELETE /customers/{customerId}/mappings/{mappingId}`

**Description:** Removes a specific link between an integration customer and a canonical customer. The integration customer returns to the "unmapped" pool.

**Permission Required:** `customers,edit`

---

## 10. Get All Customer IDs

**Endpoint:** `GET /customers/all-ids`

**Description:** Retrieves a simple list of all customer IDs. Useful for bulk operations.

**Permission Required:** `customers,view`

---

## 11. Smart Match Suggestions

**Endpoint:** `POST /customers/smart-match`

**Description:** Generates AI/Embeddings-based matching suggestions for specific integration customers.

**Permission Required:** `customers,map`

**Request Body:**

- `integration_customer_ids` (required): Array of IDs to find matches for.

---

## 12. Batch Auto-Match

**Endpoint:** `POST /customers/batch-auto-match`

**Description:** Automatically applies high-confidence smart matches for a batch of customers.

**Permission Required:** `customers,map`

**Request Body:**

- `integration_customer_ids` (optional): Array of IDs to process.
