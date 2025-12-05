# User Guide: Generating Reports

This guide explains how to generate reports for your customers using the Reporting Platform UI.

## Prerequisites
*   The customer must be **mapped** to an integration (e.g., Autotask, Datto).
*   Data must have been synced or imported for the customer.

---

## 1. Navigate to the Customer
1.  Go to the **Customers** page from the main sidebar.
2.  Find the customer you want to generate a report for.
3.  Click on the customer's name to open their details.

## 2. Open the Reports Tab
1.  In the customer details view, look for the tabs at the top (e.g., "Overview", "Integrations", "Reports").
2.  Click on the **Reports** tab.
    *   *Note: You will only see reports that are available for this customer's specific integrations. For example, you won't see "Backup Status" if the customer doesn't have a backup integration like Datto.*

## 3. Generate a One-Off Report (Manual)
If you need a report *right now* (e.g., for a meeting):

1.  Find the report type you want (e.g., "Monthly Comprehensive Report").
2.  Click the **Generate Now** button next to it.
3.  A dialog will appear asking for the **Date Range**:
    *   **Last Month:** (Default) Generates data for the previous complete month.
    *   **Custom Range:** Allows you to pick specific start and end dates.
4.  Click **Confirm**.
5.  The system will start generating the report. You will receive a notification (and an email) when it is ready.

## 4. Schedule a Report (Automatic)
To have reports sent automatically every week or month:

1.  In the **Reports** tab, find the report type.
2.  Click the **Schedule** button (or "Edit Schedule" if one exists).
3.  Configure the schedule:
    *   **Frequency:** Daily, Weekly, or Monthly.
    *   **Time:** What time of day to send it (e.g., 9:00 AM).
    *   **Recipients:** Enter the email addresses who should receive the report.
4.  Click **Save**.
5.  The report will now run automatically at the specified time.

---

## Troubleshooting
*   **"No Data Available":** If a report fails with this message, it means there is no data in the system for the selected date range. Try running a **Sync** or **Historical Import** for that customer first.
*   **"Report Type Not Found":** Ensure the customer is mapped to the correct integration. A "Ticket Summary" report requires an Autotask or Kaseya integration.
