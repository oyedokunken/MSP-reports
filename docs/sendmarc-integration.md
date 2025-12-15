# Sendmarc Integration Guide

## Overview
The Sendmarc integration provides email security and DMARC monitoring. It uses a **Split Sync Strategy** to balance frequent status updates with heavy historical data storage.

## Architecture

### 1. Connection
-   **API**: Sendmarc Portal API v1
-   **Auth**: Bearer Token
-   **Rate Limits**: 60 req/min, 1000 req/hour

### 2. Regular Sync (`sync()`)
**Schedule**: Every 6 Hours (`0 */6 * * *`)
**Purpose**: Update "Current State" and Identity.
**Data Fetched**:
1.  **Accounts**: Access to customer organizations.
2.  **Domains**: List of protected domains (e.g., `example.com`).
3.  **Health Score**: The current DMARC score (0-100) and configuration status (Active/Inactive), stored in `metadata`.

> **Why?** This ensures your customer list and their "Health Score" are always up to date (within 6 hours) without downloading heavy logs.

### 3. Historical Import (`getHistoricalData()`)
**Schedule**: Nightly / On-Demand
**Purpose**: Build graphs and detailed reports.
**Range**: Last 90 Days (Industry Standard).
**Data Fetched**:
1.  **Volume Trends**: Daily count of emails sent, rejected, and passed.
2.  **Sender Stats**: Identity of services sending email (Google, Mailchimp, etc.).
3.  **IP Reputation**: Logs of specific IP addresses and their threat status.
4.  **Threats**: DMARC failure events (Spoofing attempts).

## Data Storage Strategy
-   **Customers/Domains**: Stored in `integration_customers`.
-   **Stats/Logs**: Stored in `integration_data` (TimescaleDB).
-   **Pattern**: Insert-Only (`create()`) for all historical metrics.

## Key Files
-   `SendmarcIntegration.php`: Main logic.
-   `SendmarcClient.php`: API handling.
-   `SendmarcMapper.php`: Data standardization.
