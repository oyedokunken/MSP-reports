# Test Report Commands

Here are the console commands to generate and send test reports for various integrations.

## BitDefender

Generate and send a test BitDefender Summary report PDF.

```bash
php artisan app:test-bitdefender-report admin@example.com --customer=ID
```

## Autotask

Generate and send a test Autotask Summary report PDF.

```bash
php artisan app:test-autotask-report admin@example.com --customer=ID
```

## CIPP

Generate and send a test CIPP M365 Summary report PDF.

```bash
php artisan app:test-cipp-report admin@example.com --customer=ID
```

## Datto RMM

Generate and send a test Datto RMM Summary report PDF.

```bash
php artisan app:test-datto-report admin@example.com --customer=ID
```

## ITGlue

Generate and send a test ITGlue Summary report PDF.

```bash
php artisan app:test-itglue-report admin@example.com --customer=ID
```

## LibreNMS

Generate and send a test LibreNMS Executive Summary report PDF.

```bash
php artisan app:test-librenms-report admin@example.com --customer=ID
```

## SaaS Alerts

Generate and send a test SaaS Alerts Security report PDF.

```bash
php artisan app:test-saas-report admin@example.com --customer=ID
```

## Sendmarc

Generate and send a test Sendmarc Summary report PDF.

```bash
php artisan app:test-sendmarc-report admin@example.com --customer=ID
```

## ThreatLocker

Generate and send a test ThreatLocker Summary report PDF.

```bash
php artisan app:test-threatlocker-report admin@example.com --customer=ID
```

### Options

- `email`: The email address to send the report to (Required).
- `--customer=ID`: The Customer ID to generate the report for (Required).
- `--months=1`: How many months back to fetch data for (Optional, default: 1).
