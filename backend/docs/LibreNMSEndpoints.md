# LibreNMS API - Postman Testing Guide

## TTS Use Case

**Purpose:** Determine device availability per group for monthly reporting

**Key Workflow:**

1. Fetch device groups (network segments, infrastructure types)
2. Get devices within each group
3. Pull availability metrics (24h, 7d, 30d, 365d)
4. Calculate group-level uptime statistics
5. Generate monthly availability reports per customer/group

---

## Production Credentials

**Base URL:** `https://nms.arafuracloud.com.au/api/v0`  
**API Token:** `96c9989e1c4a0a9ce532c91d27d05840`  
**Source ID:** `11`  
**Source Name:** `NMS`  
**Type:** `pull`  
**Driver:** `librenms`  
**Status:** `active`

---

## API Endpoints

### 1. Get All Device Groups

**Endpoint:** `GET /devicegroups`  
**URL:** `https://nms.arafuracloud.com.au/api/v0/devicegroups`

**Headers:**

```
X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840
```

**Response:**

```json
{
    "status": "ok",
    "groups": [
        {
            "id": 5,
            "name": "Arafura Core Infrastructure",
            "desc": "Arafura Core Infrastructure",
            "type": "dynamic",
            "rules": {
                "condition": "OR",
                "rules": [
                    {
                        "id": "devices.hostname",
                        "field": "devices.hostname",
                        "type": "string",
                        "input": "text",
                        "operator": "begins_with",
                        "value": "192.168.225"
                    },
                    {
                        "id": "devices.hostname",
                        "field": "devices.hostname",
                        "type": "string",
                        "input": "text",
                        "operator": "begins_with",
                        "value": "192.168.228"
                    }
                ],
                "valid": true,
                "joins": []
            },
            "pattern": null
        },
        {
            "id": 2,
            "name": "Arafura Wireless",
            "desc": "Arafura Wireless",
            "type": "dynamic",
            "rules": {
                "condition": "OR",
                "rules": [
                    {
                        "id": "ipv4_addresses.ipv4_address",
                        "field": "ipv4_addresses.ipv4_address",
                        "type": "string",
                        "input": "text",
                        "operator": "begins_with",
                        "value": "10.10.10"
                    }
                ],
                "valid": true,
                "joins": [
                    ["ports", "devices.device_id", "ports.device_id"],
                    [
                        "ipv4_addresses",
                        "ports.port_id",
                        "ipv4_addresses.port_id"
                    ]
                ]
            },
            "pattern": null
        }
    ]
}
```

**cURL:**

```bash
curl -X GET "https://nms.arafuracloud.com.au/api/v0/devicegroups" \
  -H "X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840"
```

---

### 2. Get Devices in Group

**Endpoint:** `GET /devicegroups/{group_id}`  
**URL:** `https://nms.arafuracloud.com.au/api/v0/devicegroups/{group_id}`

**Headers:**

```
X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840
```

**Response:**

```json
{
    "status": "ok",
    "devices": [
        {
            "device_id": 7
        },
        {
            "device_id": 70
        },
        {
            "device_id": 71
        },
        {
            "device_id": 101
        },
        {
            "device_id": 125
        }
    ]
}
```

**cURL:**

```bash
curl -X GET "https://nms.arafuracloud.com.au/api/v0/devicegroups/1" \
  -H "X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840"
```

---

### 3. Get All Devices

**Endpoint:** `GET /devices`  
**URL:** `https://nms.arafuracloud.com.au/api/v0/devices`

**Headers:**

```
X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840
```

**Response:**

```json
{
    "status": "ok",
    "devices": [
        {
            "device_id": 264,
            "inserted": "2025-04-22 19:10:03",
            "hostname": "10.1.21.10",
            "sysName": "tcu-drw-ad10.tcu.local",
            "display": null,
            "ip": "10.1.21.10",
            "overwrite_ip": null,
            "community": "pt7fp",
            "snmpver": "v2c",
            "port": 161,
            "transport": "udp",
            "snmp_disable": 0,
            "sysObjectID": ".1.3.6.1.4.1.311.1.1.3.1.3",
            "sysDescr": "Hardware: Intel64 Family 15 Model 107 Stepping 1 AT/AT COMPATIBLE - Software: Windows Version 6.3 (Build 26100 Multiprocessor Free)",
            "sysContact": null,
            "version": "Server 2025 Datacenter (24H2)",
            "hardware": "Intel x64",
            "features": "Multiprocessor",
            "location_id": null,
            "os": "windows",
            "status": 1,
            "status_reason": "",
            "ignore": 0,
            "disabled": 0,
            "uptime": 151283,
            "agent_uptime": 0,
            "last_polled": "2025-12-03 22:43:20",
            "last_poll_attempted": null,
            "last_polled_timetaken": 2.0419299602509,
            "last_discovered_timetaken": 90.022,
            "last_discovered": "2025-12-03 22:46:06",
            "last_ping": "2025-12-03 22:43:19",
            "last_ping_timetaken": 1.03,
            "purpose": null,
            "type": "server",
            "serial": null,
            "icon": "windows.svg",
            "poller_group": 0,
            "override_sysLocation": 0,
            "notes": null,
            "port_association_mode": 1,
            "max_depth": 0,
            "disable_notify": 0,
            "ignore_status": 0,
            "dependency_parent_id": null,
            "dependency_parent_hostname": null,
            "location": null,
            "lat": null,
            "lng": null
        },
        {
            "device_id": 886,
            "inserted": "2025-09-18 15:56:29",
            "hostname": "10.1.21.15",
            "sysName": "tcu-drw-ad11.tcu.local",
            "display": null,
            "ip": "10.1.21.15",
            "overwrite_ip": null,
            "community": "pt7fp",
            "snmpver": "v2c",
            "port": 161,
            "transport": "udp",
            "snmp_disable": 0,
            "sysObjectID": ".1.3.6.1.4.1.311.1.1.3.1.3",
            "sysDescr": "Hardware: Intel64 Family 15 Model 107 Stepping 1 AT/AT COMPATIBLE - Software: Windows Version 6.3 (Build 26100 Multiprocessor Free)",
            "sysContact": null,
            "version": "Server 2025 Datacenter (24H2)",
            "hardware": "Intel x64",
            "features": "Multiprocessor",
            "location_id": null,
            "os": "windows",
            "status": 1,
            "status_reason": "",
            "ignore": 0,
            "disabled": 0,
            "uptime": 151212,
            "agent_uptime": 0,
            "last_polled": "2025-12-03 22:43:21",
            "last_poll_attempted": null,
            "last_polled_timetaken": 1.9853880405426,
            "last_discovered_timetaken": 5.757,
            "last_discovered": "2025-12-03 22:47:20",
            "last_ping": "2025-12-03 22:43:20",
            "last_ping_timetaken": 0.64,
            "purpose": null,
            "type": "server",
            "serial": null,
            "icon": "windows.svg",
            "poller_group": 0,
            "override_sysLocation": 0,
            "notes": null,
            "port_association_mode": 1,
            "max_depth": 0,
            "disable_notify": 0,
            "ignore_status": 0,
            "dependency_parent_id": null,
            "dependency_parent_hostname": null,
            "location": null,
            "lat": null,
            "lng": null
        }
    ]
}
```

**cURL:**

```bash
curl -X GET "https://nms.arafuracloud.com.au/api/v0/devices" \
  -H "X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840"
```

---

### 4. Get Single Device

**Endpoint:** `GET /devices/{device_id}`  
**URL:** `https://nms.arafuracloud.com.au/api/v0/devices/{device_id}`

**Headers:**

```
X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840
```

**Response:**

```json
{
    "status": "ok",
    "devices": [
        {
            "device_id": 1,
            "inserted": "2024-12-18T03:13:03.000000Z",
            "hostname": "192.168.225.233",
            "sysName": "ara-asp-232core-2",
            "display": null,
            "ip": "192.168.225.233",
            "overwrite_ip": null,
            "community": "public",
            "snmpver": "v2c",
            "port": 161,
            "transport": "udp",
            "snmp_disable": false,
            "sysObjectID": ".1.3.6.1.4.1.4526.100.11.16",
            "sysDescr": "M4100-26G ProSafe 24-port Gigabit L2+ Intelligent Edge Managed Switch, 10.0.1.28, B1.0.1.0",
            "sysContact": "Michael Feldbauer",
            "version": "10.0.1.28",
            "hardware": "M4100-26G",
            "features": null,
            "location_id": 16,
            "os": "netgear",
            "status": true,
            "status_reason": "",
            "ignore": false,
            "disabled": false,
            "uptime": 14506224,
            "agent_uptime": 0,
            "last_polled": "2025-12-03T13:18:14.000000Z",
            "last_poll_attempted": null,
            "last_polled_timetaken": 3.3547070026398,
            "last_discovered_timetaken": 3.543,
            "last_discovered": "2025-12-03T11:35:29.000000Z",
            "last_ping": "2025-12-03T13:18:12.000000Z",
            "last_ping_timetaken": 2.82,
            "purpose": null,
            "type": "network",
            "serial": "3924595800012",
            "icon": "images/os/netgear.svg",
            "poller_group": 0,
            "override_sysLocation": false,
            "notes": null,
            "port_association_mode": 1,
            "max_depth": 0,
            "disable_notify": false,
            "ignore_status": false,
            "location": "Rack A -  23",
            "lat": null,
            "lng": null
        }
    ],
    "count": 1
}
```

**cURL:**

```bash
curl -X GET "https://nms.arafuracloud.com.au/api/v0/devices/101" \
  -H "X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840"
```

---

### 5. Get Device Availability

**Endpoint:** `GET /devices/{device_id}/availability`  
**URL:** `https://nms.arafuracloud.com.au/api/v0/devices/{device_id}/availability`

**Headers:**

```
X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840
```

**Response:**

```json
{
    "status": "ok",
    "availability": [
        {
            "duration": 86400,
            "availability_perc": "100.000000"
        },
        {
            "duration": 604800,
            "availability_perc": "100.000000"
        },
        {
            "duration": 2592000,
            "availability_perc": "100.000000"
        },
        {
            "duration": 31536000,
            "availability_perc": "99.968000"
        }
    ]
}
```

**Duration Values:**

- `86400` = 24 hours
- `604800` = 7 days
- `2592000` = 30 days
- `31536000` = 365 days

**cURL:**

```bash
curl -X GET "https://nms.arafuracloud.com.au/api/v0/devices/101/availability" \
  -H "X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840"
```

---

### 6. Get Device Groups for Device

**Endpoint:** `GET /devices/{device_id}/groups`  
**URL:** `https://nms.arafuracloud.com.au/api/v0/devices/{device_id}/groups`

**Headers:**

```
X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840
```

**Response:**

```json
{
    "status": "ok",
    "groups": [
        {
            "id": 5,
            "name": "Arafura Core Infrastructure",
            "desc": "Arafura Core Infrastructure",
            "type": "dynamic",
            "rules": {
                "condition": "OR",
                "rules": [
                    {
                        "id": "devices.hostname",
                        "field": "devices.hostname",
                        "type": "string",
                        "input": "text",
                        "operator": "begins_with",
                        "value": "192.168.225"
                    },
                    {
                        "id": "devices.hostname",
                        "field": "devices.hostname",
                        "type": "string",
                        "input": "text",
                        "operator": "begins_with",
                        "value": "192.168.228"
                    }
                ],
                "valid": true,
                "joins": []
            },
            "pattern": null
        }
    ],
    "message": "Found 1 device groups",
    "count": 1
}
```

**cURL:**

```bash
curl -X GET "https://nms.arafuracloud.com.au/api/v0/devices/101/groups" \
  -H "X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840"
```

---

## Postman Collection

### Import This JSON

```json
{
    "info": {
        "name": "LibreNMS API - Arafura Cloud NMS",
        "description": "API testing for LibreNMS NMS instance",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "variable": [
        {
            "key": "base_url",
            "value": "https://nms.arafuracloud.com.au/api/v0",
            "type": "string"
        },
        {
            "key": "api_token",
            "value": "96c9989e1c4a0a9ce532c91d27d05840",
            "type": "string"
        }
    ],
    "item": [
        {
            "name": "1. Get All Device Groups",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "pm.test(\"Status code is 200\", function () {",
                            "    pm.response.to.have.status(200);",
                            "});",
                            "",
                            "pm.test(\"Response status is ok\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.status).to.eql(\"ok\");",
                            "});",
                            "",
                            "pm.test(\"Groups array exists\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.groups).to.be.an('array');",
                            "});",
                            "",
                            "if (pm.response.json().groups && pm.response.json().groups.length > 0) {",
                            "    pm.environment.set(\"group_id\", pm.response.json().groups[0].id);",
                            "    console.log(\"Saved group_id: \" + pm.response.json().groups[0].id);",
                            "}"
                        ]
                    }
                }
            ],
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "X-Auth-Token",
                        "value": "{{api_token}}",
                        "type": "text"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/devicegroups",
                    "host": ["{{base_url}}"],
                    "path": ["devicegroups"]
                }
            }
        },
        {
            "name": "2. Get Devices in Group",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "pm.test(\"Status code is 200\", function () {",
                            "    pm.response.to.have.status(200);",
                            "});",
                            "",
                            "pm.test(\"Response status is ok\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.status).to.eql(\"ok\");",
                            "});",
                            "",
                            "pm.test(\"Devices array exists\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.devices).to.be.an('array');",
                            "});",
                            "",
                            "if (pm.response.json().devices && pm.response.json().devices.length > 0) {",
                            "    var deviceId = pm.response.json().devices[0].device_id;",
                            "    pm.environment.set(\"device_id\", deviceId);",
                            "    console.log(\"Saved device_id: \" + deviceId);",
                            "}"
                        ]
                    }
                }
            ],
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "X-Auth-Token",
                        "value": "{{api_token}}",
                        "type": "text"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/devicegroups/{{group_id}}",
                    "host": ["{{base_url}}"],
                    "path": ["devicegroups", "{{group_id}}"]
                }
            }
        },
        {
            "name": "3. Get All Devices",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "pm.test(\"Status code is 200\", function () {",
                            "    pm.response.to.have.status(200);",
                            "});",
                            "",
                            "pm.test(\"Response status is ok\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.status).to.eql(\"ok\");",
                            "});",
                            "",
                            "pm.test(\"Devices array exists\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.devices).to.be.an('array');",
                            "});",
                            "",
                            "pm.test(\"Devices have required fields\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.devices.length > 0) {",
                            "        var device = jsonData.devices[0];",
                            "        pm.expect(device).to.have.property('device_id');",
                            "        pm.expect(device).to.have.property('hostname');",
                            "        pm.expect(device).to.have.property('status');",
                            "    }",
                            "});",
                            "",
                            "if (pm.response.json().devices && pm.response.json().devices.length > 0) {",
                            "    pm.environment.set(\"device_id\", pm.response.json().devices[0].device_id);",
                            "    console.log(\"Saved device_id: \" + pm.response.json().devices[0].device_id);",
                            "}"
                        ]
                    }
                }
            ],
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "X-Auth-Token",
                        "value": "{{api_token}}",
                        "type": "text"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/devices",
                    "host": ["{{base_url}}"],
                    "path": ["devices"]
                }
            }
        },
        {
            "name": "4. Get Single Device",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "pm.test(\"Status code is 200\", function () {",
                            "    pm.response.to.have.status(200);",
                            "});",
                            "",
                            "pm.test(\"Response status is ok\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.status).to.eql(\"ok\");",
                            "});",
                            "",
                            "pm.test(\"Devices array has one device\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.devices).to.be.an('array');",
                            "    pm.expect(jsonData.devices.length).to.eql(1);",
                            "});",
                            "",
                            "pm.test(\"Device has required fields\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    var device = jsonData.devices[0];",
                            "    pm.expect(device).to.have.property('device_id');",
                            "    pm.expect(device).to.have.property('hostname');",
                            "    pm.expect(device).to.have.property('status');",
                            "});"
                        ]
                    }
                }
            ],
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "X-Auth-Token",
                        "value": "{{api_token}}",
                        "type": "text"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/devices/{{device_id}}",
                    "host": ["{{base_url}}"],
                    "path": ["devices", "{{device_id}}"]
                }
            }
        },
        {
            "name": "5. Get Device Availability",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "pm.test(\"Status code is 200\", function () {",
                            "    pm.response.to.have.status(200);",
                            "});",
                            "",
                            "pm.test(\"Response status is ok\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.status).to.eql(\"ok\");",
                            "});",
                            "",
                            "pm.test(\"Availability array exists\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.availability).to.be.an('array');",
                            "});",
                            "",
                            "pm.test(\"Availability entries have required fields\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.availability.length > 0) {",
                            "        jsonData.availability.forEach(function(entry) {",
                            "            pm.expect(entry).to.have.property('duration');",
                            "            pm.expect(entry).to.have.property('availability_perc');",
                            "            pm.expect(entry.availability_perc).to.be.within(0, 100);",
                            "        });",
                            "    }",
                            "});"
                        ]
                    }
                }
            ],
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "X-Auth-Token",
                        "value": "{{api_token}}",
                        "type": "text"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/devices/{{device_id}}/availability",
                    "host": ["{{base_url}}"],
                    "path": ["devices", "{{device_id}}", "availability"]
                }
            }
        },
        {
            "name": "6. Get Device Groups for Device",
            "event": [
                {
                    "listen": "test",
                    "script": {
                        "exec": [
                            "pm.test(\"Status code is 200\", function () {",
                            "    pm.response.to.have.status(200);",
                            "});",
                            "",
                            "pm.test(\"Response status is ok\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.status).to.eql(\"ok\");",
                            "});",
                            "",
                            "pm.test(\"Groups array exists\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    pm.expect(jsonData.groups).to.be.an('array');",
                            "});",
                            "",
                            "pm.test(\"Groups have required fields\", function () {",
                            "    var jsonData = pm.response.json();",
                            "    if (jsonData.groups.length > 0) {",
                            "        jsonData.groups.forEach(function(group) {",
                            "            pm.expect(group).to.have.property('id');",
                            "            pm.expect(group).to.have.property('name');",
                            "        });",
                            "    }",
                            "});"
                        ]
                    }
                }
            ],
            "request": {
                "method": "GET",
                "header": [
                    {
                        "key": "X-Auth-Token",
                        "value": "{{api_token}}",
                        "type": "text"
                    }
                ],
                "url": {
                    "raw": "{{base_url}}/devices/{{device_id}}/groups",
                    "host": ["{{base_url}}"],
                    "path": ["devices", "{{device_id}}", "groups"]
                }
            }
        }
    ]
}
```

---

## Testing Workflow

### Step 1: Import to Postman

1. Copy the JSON collection above
2. Open Postman → **Import** → **Raw Text**
3. Paste and click **Import**

### Step 2: Run Tests in Order

1. **Get All Device Groups** - Saves `group_id`
2. **Get All Devices** - Saves `device_id`
3. **Get Single Device** - Uses saved `device_id`
4. **Get Device Availability** - Uses saved `device_id`
5. **Get Device Groups for Device** - Uses saved `device_id`
6. **Get Devices in Group** - Uses saved `group_id`

### Step 3: View Results

Each test automatically validates:

- Status code is 200
- Response has `status: "ok"`
- Required fields exist
- Data types are correct

---

## Quick Test Commands

### Test Connection

```bash
curl -X GET "https://nms.arafuracloud.com.au/api/v0/devicegroups" \
  -H "X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840"
```

### Get All Devices

```bash
curl -X GET "https://nms.arafuracloud.com.au/api/v0/devices" \
  -H "X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840"
```

### Get Specific Device (replace 101 with actual device_id)

```bash
curl -X GET "https://nms.arafuracloud.com.au/api/v0/devices/101" \
  -H "X-Auth-Token: 96c9989e1c4a0a9ce532c91d27d05840"
```

---

## Response Codes

| Code | Status       | Meaning                    |
| ---- | ------------ | -------------------------- |
| 200  | OK           | Success                    |
| 401  | Unauthorized | Invalid API token          |
| 404  | Not Found    | Device/Group doesn't exist |
| 500  | Server Error | LibreNMS error             |

---

## Notes

- All endpoints return JSON with `status: "ok"` on success
- Device status: `1` = up, `0` = down
- Disabled devices (`disabled: 1`) are excluded
- SSL verification disabled for self-signed certificates
- Timeout: 30 seconds, 3 retries
