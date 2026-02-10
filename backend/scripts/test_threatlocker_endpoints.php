#!/usr/bin/env php
<?php

/**
 * ThreatLocker Portal API Endpoint Tester
 *
 * Tests all implemented endpoints against the live API.
 *
 * Usage:
 *   php scripts/test_threatlocker_endpoints.php
 *
 * Set environment variables before running:
 *   export THREATLOCKER_API_URL="https://api.g.threatlocker.com"
 *   export THREATLOCKER_API_SECRET="your-api-secret-here"
 */

require __DIR__ . '/../vendor/autoload.php';

// ── Configuration ──────────────────────────────────────────────────────────────

$apiUrl    = getenv('THREATLOCKER_API_URL') ?: 'https://api.g.threatlocker.com';
$apiSecret = getenv('THREATLOCKER_API_SECRET') ?: '';

if (empty($apiSecret)) {
    echo "\033[31m✗ ERROR: THREATLOCKER_API_SECRET environment variable is required.\033[0m\n";
    echo "  Usage:\n";
    echo "    export THREATLOCKER_API_URL=\"https://api.g.threatlocker.com\"\n";
    echo "    export THREATLOCKER_API_SECRET=\"your-64-char-hex-token\"\n";
    echo "    php scripts/test_threatlocker_endpoints.php\n\n";
    exit(1);
}

// ── Resolve Portal API base URL ────────────────────────────────────────────────

function resolveBaseUrl(string $apiUrl): string
{
    $parsed = parse_url($apiUrl);
    $host = $parsed['host'] ?? $apiUrl;

    // Extract instance letter: api.{instance}.threatlocker.com
    if (preg_match('/^(?:portal)?api\.([a-z])\.threatlocker\.com$/', $host, $m)) {
        return "https://portalapi.{$m[1]}.threatlocker.com/portalApi/";
    }

    // Beta or custom
    if (preg_match('/^(?:beta)?portalapi\.([a-z])\.threatlocker\.com/', $host, $m)) {
        $path = rtrim($parsed['path'] ?? '/portalApi', '/');
        return "https://{$host}{$path}/";
    }

    return rtrim($apiUrl, '/') . '/';
}

$baseUrl = resolveBaseUrl($apiUrl);

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║        ThreatLocker Portal API — Endpoint Tester            ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";
echo "  Base URL:   {$baseUrl}\n";
echo "  Secret:     " . substr($apiSecret, 0, 8) . str_repeat('•', 16) . "\n\n";

// ── HTTP Helper ────────────────────────────────────────────────────────────────

function apiRequest(
    string $baseUrl,
    string $apiSecret,
    string $method,
    string $endpoint,
    array $body = [],
    array $query = [],
    ?string $orgId = null
): array {
    $url = rtrim($baseUrl, '/') . '/' . ltrim($endpoint, '/');

    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'Authorization: ' . $apiSecret,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    if ($orgId) {
        $headers[] = 'managedorganizationid: ' . $orgId;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'error' => $error, 'code' => 0, 'data' => null, 'headers' => ''];
    }

    $responseHeaders = substr($response, 0, $headerSize);
    $responseBody = substr($response, $headerSize);
    $data = json_decode($responseBody, true);

    // Extract pagination header
    $pagination = null;
    if (preg_match('/Pagination:\s*(.+)/i', $responseHeaders, $m)) {
        $pagination = json_decode(trim($m[1]), true);
    }

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'code' => $httpCode,
        'data' => $data,
        'pagination' => $pagination,
        'headers' => $responseHeaders,
        'error' => $httpCode >= 400 ? ($data['message'] ?? $responseBody) : null,
        'raw_body_length' => strlen($responseBody),
    ];
}

// ── Test Runner ────────────────────────────────────────────────────────────────

$results = [];
$testNum = 0;
$passed = 0;
$failed = 0;

function test(string $name, callable $fn): void
{
    global $results, $testNum, $passed, $failed;
    $testNum++;

    echo "  [{$testNum}] Testing: {$name} ... ";

    try {
        $result = $fn();
        if ($result['success']) {
            $count = 0;
            if (is_array($result['data'])) {
                $count = isset($result['data'][0]) ? count($result['data']) : 1;
            }
            echo "\033[32m✓ PASS\033[0m (HTTP {$result['code']}, {$count} records";
            if ($result['pagination']) {
                echo ", page {$result['pagination']['currentPage']}/{$result['pagination']['totalPages']}";
            }
            echo ")\n";
            $passed++;
            $results[$name] = ['status' => 'PASS', 'code' => $result['code'], 'records' => $count];

            // Return data for chaining
            $results[$name]['data'] = $result['data'];
        } else {
            echo "\033[31m✗ FAIL\033[0m (HTTP {$result['code']}: " . substr((string) $result['error'], 0, 100) . ")\n";
            $failed++;
            $results[$name] = ['status' => 'FAIL', 'code' => $result['code'], 'error' => $result['error']];
        }

        return;
    } catch (\Throwable $e) {
        echo "\033[31m✗ ERROR\033[0m ({$e->getMessage()})\n";
        $failed++;
        $results[$name] = ['status' => 'ERROR', 'error' => $e->getMessage()];
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// Test 1: Get Organizations (child orgs)
// ══════════════════════════════════════════════════════════════════════════════

echo "── Organizations ──────────────────────────────────────────────\n";

$firstOrgId = null;
$firstGroupId = null;
$firstAppId = null;

test('OrganizationGetChildOrganizationsByParameters', function () use ($baseUrl, $apiSecret, &$firstOrgId) {
    $result = apiRequest($baseUrl, $apiSecret, 'POST', 'Organization/OrganizationGetChildOrganizationsByParameters', [
        'pageSize' => 25,
        'pageNumber' => 1,
    ]);

    // Capture first org ID for subsequent tests
    if ($result['success'] && is_array($result['data']) && !empty($result['data'])) {
        $first = $result['data'][0] ?? null;
        if ($first) {
            $firstOrgId = $first['organizationId'] ?? $first['OrganizationId'] ?? null;
            echo "    → First org: " . ($first['name'] ?? 'N/A') . " (ID: {$firstOrgId})\n";
        }
    }

    return $result;
});

test('OrganizationGetListForNav', function () use ($baseUrl, $apiSecret) {
    return apiRequest($baseUrl, $apiSecret, 'GET', 'Organization/OrganizationGetListForNav', [], [
        'search' => '',
        'pageSize' => '25',
        'pageNumber' => '1',
    ]);
});

// ══════════════════════════════════════════════════════════════════════════════
// Test 2: Computer Groups (requires org ID)
// ══════════════════════════════════════════════════════════════════════════════

echo "\n── Computer Groups ────────────────────────────────────────────\n";

if ($firstOrgId) {
    test('ComputerGroupGetGroupAndComputer', function () use ($baseUrl, $apiSecret, $firstOrgId, &$firstGroupId) {
        $result = apiRequest($baseUrl, $apiSecret, 'GET', 'ComputerGroup/ComputerGroupGetGroupAndComputer', [], [
            'osType' => '0',
            'includeGlobal' => 'true',
            'includeAllPolicies' => 'false',
            'includeOrganizations' => 'false',
            'includeParentGroups' => 'false',
            'includeLoggedInObjects' => 'false',
        ], $firstOrgId);

        if ($result['success'] && is_array($result['data'])) {
            foreach ($result['data'] as $section) {
                $label = $section['label'] ?? 'unknown';
                $count = count($section['items'] ?? []);
                echo "    → Section: {$label} ({$count} items)\n";

                // Capture first group ID
                if (in_array($label, ['Other', 'Computer Groups']) && !$firstGroupId && $count > 0) {
                    $firstGroupId = $section['items'][0]['value'] ?? null;
                }
            }
        }

        return $result;
    });
} else {
    echo "  \033[33m⚠ SKIP\033[0m (no org ID available from previous test)\n";
}

// ══════════════════════════════════════════════════════════════════════════════
// Test 3: Computers
// ══════════════════════════════════════════════════════════════════════════════

echo "\n── Computers ──────────────────────────────────────────────────\n";

if ($firstOrgId) {
    test('ComputerGetByAllParameters', function () use ($baseUrl, $apiSecret, $firstOrgId) {
        $result = apiRequest($baseUrl, $apiSecret, 'POST', 'Computer/ComputerGetByAllParameters', [
            'searchText' => '',
            'computerGroup' => '00000000-0000-0000-0000-000000000000',
            'orderBy' => 'computername',
            'pageSize' => 10,
            'pageNumber' => 1,
            'computerId' => '00000000-0000-0000-0000-000000000000',
            'childOrganizations' => false,
            'showLastCheckIn' => true,
            'isAscending' => true,
            'kindOfAction' => '',
            'hasComputerPassword' => false,
        ], [], $firstOrgId);

        if ($result['success'] && is_array($result['data']) && !empty($result['data'])) {
            $first = $result['data'][0] ?? null;
            if ($first) {
                $name = $first['computerName'] ?? $first['ComputerName'] ?? 'N/A';
                $lastCheckin = $first['lastCheckin'] ?? $first['LastCheckin'] ?? 'N/A';
                echo "    → Sample: {$name} (last checkin: {$lastCheckin})\n";
            }
        }

        return $result;
    });
} else {
    echo "  \033[33m⚠ SKIP\033[0m (no org ID)\n";
}

// ══════════════════════════════════════════════════════════════════════════════
// Test 4: Application Control Policies
// ══════════════════════════════════════════════════════════════════════════════

echo "\n── Application Control Policies ───────────────────────────────\n";

if ($firstOrgId && $firstGroupId) {
    test('PolicyGetByParameters', function () use ($baseUrl, $apiSecret, $firstOrgId, $firstGroupId) {
        $result = apiRequest($baseUrl, $apiSecret, 'POST', 'Policy/PolicyGetByParameters', [
            'activeOnly' => true,
            'filter' => '',
            'isEnabled' => true,
            'searchText' => '',
            'status' => 0,
            'utcOffset' => 0,
            'computerGroupId' => $firstGroupId,
            'pageSize' => 10,
            'pageNumber' => 1,
        ], [], $firstOrgId);

        if ($result['success'] && is_array($result['data']) && !empty($result['data'])) {
            $first = $result['data'][0] ?? null;
            if ($first) {
                $name = $first['name'] ?? $first['Name'] ?? 'N/A';
                $action = $first['action'] ?? $first['Action'] ?? 'N/A';
                echo "    → Sample: {$name} (action: {$action})\n";
            }
        }

        return $result;
    });
} elseif ($firstOrgId) {
    echo "  \033[33m⚠ SKIP\033[0m (no group ID — policies require a group or computer)\n";
} else {
    echo "  \033[33m⚠ SKIP\033[0m (no org ID)\n";
}

// ══════════════════════════════════════════════════════════════════════════════
// Test 5: Applications
// ══════════════════════════════════════════════════════════════════════════════

echo "\n── Applications ───────────────────────────────────────────────\n";

if ($firstOrgId) {
    test('ApplicationGetByParameters', function () use ($baseUrl, $apiSecret, $firstOrgId, &$firstAppId) {
        $result = apiRequest($baseUrl, $apiSecret, 'POST', 'Application/ApplicationGetByParameters', [
            'searchText' => '',
            'searchBy' => 'app',
            'orderBy' => 'name',
            'isAscending' => true,
            'includeMaster' => true,
            'permittedApplications' => true,
            'isBuiltInApplication' => false,
            'isHidden' => false,
            'isTemporary' => false,
            'category' => 1,
            'osType' => 0,
            'includeChildOrganizations' => false,
            'countries' => [],
            'categories' => [],
            'pageSize' => 10,
            'pageNumber' => 1,
        ], [], $firstOrgId);

        if ($result['success'] && is_array($result['data']) && !empty($result['data'])) {
            $first = $result['data'][0] ?? null;
            if ($first) {
                $firstAppId = $first['applicationId'] ?? $first['ApplicationId'] ?? null;
                $name = $first['name'] ?? $first['Name'] ?? 'N/A';
                echo "    → Sample: {$name} (ID: {$firstAppId})\n";
            }
        }

        return $result;
    });
} else {
    echo "  \033[33m⚠ SKIP\033[0m (no org ID)\n";
}

// ══════════════════════════════════════════════════════════════════════════════
// Test 6: Application by ID
// ══════════════════════════════════════════════════════════════════════════════

if ($firstOrgId && $firstAppId) {
    test('ApplicationGetById', function () use ($baseUrl, $apiSecret, $firstOrgId, $firstAppId) {
        return apiRequest($baseUrl, $apiSecret, 'GET', 'Application/ApplicationGetById', [], [
            'applicationId' => $firstAppId,
        ], $firstOrgId);
    });
} else {
    echo "  \033[33m⚠ SKIP ApplicationGetById\033[0m (no app ID)\n";
}

// ══════════════════════════════════════════════════════════════════════════════
// Test 7: Application Files
// ══════════════════════════════════════════════════════════════════════════════

if ($firstOrgId && $firstAppId) {
    test('ApplicationFileGetByApplicationId', function () use ($baseUrl, $apiSecret, $firstOrgId, $firstAppId) {
        return apiRequest($baseUrl, $apiSecret, 'GET', 'ApplicationFile/ApplicationFileGetByApplicationId', [], [
            'applicationId' => $firstAppId,
            'searchText' => '',
            'pageSize' => '10',
            'pageNumber' => '1',
        ], $firstOrgId);
    });
} else {
    echo "  \033[33m⚠ SKIP ApplicationFileGetByApplicationId\033[0m (no app ID)\n";
}

// ══════════════════════════════════════════════════════════════════════════════
// Test 8: Storage Control Policies
// ══════════════════════════════════════════════════════════════════════════════

echo "\n── Storage Control ────────────────────────────────────────────\n";

if ($firstOrgId && $firstGroupId) {
    test('StoragePolicyGetByParameters', function () use ($baseUrl, $apiSecret, $firstOrgId, $firstGroupId) {
        return apiRequest($baseUrl, $apiSecret, 'POST', 'StoragePolicy/StoragePolicyGetByParameters', [
            'activeOnly' => true,
            'filter' => '',
            'isEnabled' => true,
            'searchText' => '',
            'status' => 0,
            'computerGroupId' => $firstGroupId,
            'pageSize' => 10,
            'pageNumber' => 1,
        ], [], $firstOrgId);
    });
} elseif ($firstOrgId) {
    echo "  \033[33m⚠ SKIP StoragePolicyGetByParameters\033[0m (no group ID)\n";
} else {
    echo "  \033[33m⚠ SKIP StoragePolicyGetByParameters\033[0m (no org ID)\n";
}

// ══════════════════════════════════════════════════════════════════════════════
// Test 9: Storage Devices
// ══════════════════════════════════════════════════════════════════════════════

if ($firstOrgId) {
    test('StorageDeviceGetByParameters', function () use ($baseUrl, $apiSecret, $firstOrgId) {
        return apiRequest($baseUrl, $apiSecret, 'POST', 'StorageDevice/StorageDeviceGetByParameters', [
            'pageSize' => 10,
            'pageNumber' => 1,
        ], [], $firstOrgId);
    });
} else {
    echo "  \033[33m⚠ SKIP StorageDeviceGetByParameters\033[0m (no org ID)\n";
}

// ══════════════════════════════════════════════════════════════════════════════
// Summary
// ══════════════════════════════════════════════════════════════════════════════

echo "\n══════════════════════════════════════════════════════════════\n";
echo "  Results: \033[32m{$passed} passed\033[0m, \033[31m{$failed} failed\033[0m, {$testNum} total\n";
echo "══════════════════════════════════════════════════════════════\n\n";

if ($failed > 0) {
    echo "\033[31mFailing tests:\033[0m\n";
    foreach ($results as $name => $r) {
        if ($r['status'] !== 'PASS') {
            $code = $r['code'] ?? 'N/A';
            $err = substr((string) ($r['error'] ?? ''), 0, 200);
            echo "  ✗ {$name}: HTTP {$code} — {$err}\n";
        }
    }
    echo "\n";
}

exit($failed > 0 ? 1 : 0);
