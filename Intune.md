# Intune

## List Applications

> Retrieves a list of applications

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListApps":{"get":{"summary":"List Applications","description":"Retrieves a list of applications","tags":["Applications"],"parameters":[{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Applications retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"id":{"type":"string","description":"The application ID"},"displayName":{"type":"string","description":"The display name of the application"},"publishingState":{"type":"string","description":"The publishing state of the application"},"installCommandLine":{"type":"string","description":"The install command line"},"uninstallCommandLine":{"type":"string","description":"The uninstall command line"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## Assign Application

> Assigns an application to users or devices

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecAssignApp":{"post":{"summary":"Assign Application","description":"Assigns an application to users or devices","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["AssignTo","ID"],"properties":{"AssignTo":{"type":"string","description":"Who to assign the application to","enum":["AllUsers","AllDevices","Both","customGroup"]},"ID":{"type":"string","description":"The application ID"},"customGroup":{"type":"string","description":"Custom group names (if AssignTo is customGroup)"}}}}}},"responses":{"200":{"description":"Application assigned successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Remove Application

> Removes an application

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/RemoveApp":{"post":{"summary":"Remove Application","description":"Removes an application","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["ID"],"properties":{"ID":{"type":"string","description":"The application ID"}}}}}},"responses":{"200":{"description":"Application removed successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## List Application Queue

> Retrieves a list of applications in the queue

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListApplicationQueue":{"get":{"summary":"List Application Queue","description":"Retrieves a list of applications in the queue","tags":["Applications"],"parameters":[{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Application queue retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"id":{"type":"string","description":"The queue item ID"},"applicationName":{"type":"string","description":"The application name"},"status":{"type":"string","description":"The status of the queue item"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## Remove Queued Application

> Removes an application from the queue

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/RemoveQueuedApp":{"post":{"summary":"Remove Queued Application","description":"Removes an application from the queue","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["ID"],"properties":{"ID":{"type":"string","description":"The queue item ID"}}}}}},"responses":{"200":{"description":"Queued application removed successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Execute Application Upload

> Executes the application upload process

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecAppUpload":{"post":{"summary":"Execute Application Upload","description":"Executes the application upload process","tags":["Applications"],"responses":{"200":{"description":"Application upload executed successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Add MSP Application

> Adds an MSP vendor application

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/AddMSPApp":{"post":{"summary":"Add MSP Application","description":"Adds an MSP vendor application","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["selectedTenants","rmmname","displayName"],"properties":{"selectedTenants":{"type":"array","description":"The tenants to add the application to","items":{"type":"object","properties":{"defaultDomainName":{"type":"string","description":"The tenant domain name"},"customerId":{"type":"string","description":"The tenant ID"}}}},"rmmname":{"type":"object","description":"The RMM tool","properties":{"value":{"type":"string","enum":["datto","syncro","immy","huntress","automate","cwcommand"]}}},"displayName":{"type":"string","description":"The display name of the application"},"params":{"type":"object","description":"Parameters specific to the RMM tool"},"AssignTo":{"type":"string","description":"Who to assign the application to","enum":["On","allLicensedUsers","AllDevices","AllDevicesAndUsers","customGroup"]},"customGroup":{"type":"string","description":"Custom group names (if AssignTo is customGroup)"}}}}}},"responses":{"200":{"description":"MSP application added successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Add Store Application

> Adds a store application

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/AddStoreApp":{"post":{"summary":"Add Store Application","description":"Adds a store application","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["selectedTenants","packagename","applicationName"],"properties":{"selectedTenants":{"type":"array","description":"The tenants to add the application to","items":{"type":"object","properties":{"defaultDomainName":{"type":"string","description":"The tenant domain name"},"customerId":{"type":"string","description":"The tenant ID"}}}},"packagename":{"type":"string","description":"The package identifier"},"applicationName":{"type":"string","description":"The application name"},"description":{"type":"string","description":"The application description"},"InstallationIntent":{"type":"boolean","description":"Whether to mark for uninstallation"},"AssignTo":{"type":"string","description":"Who to assign the application to","enum":["On","allLicensedUsers","AllDevices","AllDevicesAndUsers","customGroup"]},"customGroup":{"type":"string","description":"Custom group names (if AssignTo is customGroup)"}}}}}},"responses":{"200":{"description":"Store application added successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Add WinGet Application

> Adds a WinGet application

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/AddwinGetApp":{"post":{"summary":"Add WinGet Application","description":"Adds a WinGet application","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["selectedTenants","packagename","applicationName"],"properties":{"selectedTenants":{"type":"array","description":"The tenants to add the application to","items":{"type":"object","properties":{"defaultDomainName":{"type":"string","description":"The tenant domain name"},"customerId":{"type":"string","description":"The tenant ID"}}}},"packagename":{"type":"string","description":"The package identifier"},"applicationName":{"type":"string","description":"The application name"},"description":{"type":"string","description":"The application description"},"InstallationIntent":{"type":"boolean","description":"Whether to mark for uninstallation"},"AssignTo":{"type":"string","description":"Who to assign the application to","enum":["On","allLicensedUsers","AllDevices","AllDevicesAndUsers","customGroup"]},"customGroup":{"type":"string","description":"Custom group names (if AssignTo is customGroup)"}}}}}},"responses":{"200":{"description":"WinGet application added successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Add Chocolatey Application

> Adds a Chocolatey application

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/AddChocoApp":{"post":{"summary":"Add Chocolatey Application","description":"Adds a Chocolatey application","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["selectedTenants","packagename","applicationName"],"properties":{"selectedTenants":{"type":"array","description":"The tenants to add the application to","items":{"type":"object","properties":{"defaultDomainName":{"type":"string","description":"The tenant domain name"},"customerId":{"type":"string","description":"The tenant ID"}}}},"packagename":{"type":"string","description":"The package name"},"applicationName":{"type":"string","description":"The application name"},"description":{"type":"string","description":"The application description"},"customRepo":{"type":"string","description":"Custom repository URL"},"InstallAsSystem":{"type":"boolean","description":"Whether to install as system"},"DisableRestart":{"type":"boolean","description":"Whether to disable restart"},"InstallationIntent":{"type":"boolean","description":"Whether to mark for uninstallation"},"AssignTo":{"type":"string","description":"Who to assign the application to","enum":["On","allLicensedUsers","AllDevices","AllDevicesAndUsers","customGroup"]},"customGroup":{"type":"string","description":"Custom group names (if AssignTo is customGroup)"}}}}}},"responses":{"200":{"description":"Chocolatey application added successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Add Office Application

> Adds a Microsoft Office application

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/AddOfficeApp":{"post":{"summary":"Add Office Application","description":"Adds a Microsoft Office application","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["selectedTenants","updateChannel","languages"],"properties":{"selectedTenants":{"type":"array","description":"The tenants to add the application to","items":{"type":"object","properties":{"defaultDomainName":{"type":"string","description":"The tenant domain name"},"customerId":{"type":"string","description":"The tenant ID"}}}},"excludedApps":{"type":"array","description":"Apps to exclude from the installation","items":{"type":"object","properties":{"value":{"type":"string","enum":["access","excel","oneNote","outlook","powerPoint","teams","word","lync","bing"]}}}},"updateChannel":{"type":"object","description":"The update channel","properties":{"value":{"type":"string","enum":["current","firstReleaseCurrent","monthlyEnterprise","deferred","firstReleaseDeferred"]}}},"languages":{"type":"array","description":"Languages to install","items":{"type":"object","properties":{"value":{"type":"string","description":"Language tag"}}}},"SharedComputerActivation":{"type":"boolean","description":"Whether to use shared computer activation"},"arch":{"type":"boolean","description":"Whether to use 64-bit"},"RemoveVersions":{"type":"boolean","description":"Whether to remove other versions"},"AcceptLicense":{"type":"boolean","description":"Whether to accept the license"},"AssignTo":{"type":"string","description":"Who to assign the application to","enum":["On","allLicensedUsers","AllDevices","AllDevicesAndUsers"]}}}}}},"responses":{"200":{"description":"Office application added successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## List Autopilot Devices

> Retrieves a list of Autopilot devices

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListAPDevices":{"get":{"summary":"List Autopilot Devices","description":"Retrieves a list of Autopilot devices","tags":["Autopilot"],"parameters":[{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Autopilot devices retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"id":{"type":"string","description":"The device ID"},"displayName":{"type":"string","description":"The display name of the device"},"serialNumber":{"type":"string","description":"The serial number of the device"},"model":{"type":"string","description":"The model of the device"},"manufacturer":{"type":"string","description":"The manufacturer of the device"},"groupTag":{"type":"string","description":"The group tag of the device"},"enrollmentState":{"type":"string","description":"The enrollment state of the device"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## Assign Autopilot Device

> Assigns an Autopilot device to a user

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecAssignAPDevice":{"post":{"summary":"Assign Autopilot Device","description":"Assigns an Autopilot device to a user","tags":["Autopilot"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["device","serialNumber","user"],"properties":{"device":{"type":"string","description":"The device ID"},"serialNumber":{"type":"string","description":"The serial number of the device"},"user":{"type":"string","description":"The user principal name"}}}}}},"responses":{"200":{"description":"Autopilot device assigned successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Remove Autopilot Device

> Removes an Autopilot device

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/RemoveAPDevice":{"post":{"summary":"Remove Autopilot Device","description":"Removes an Autopilot device","tags":["Autopilot"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["ID"],"properties":{"ID":{"type":"string","description":"The device ID"}}}}}},"responses":{"200":{"description":"Autopilot device removed successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Sync Autopilot Devices

> Syncs Autopilot devices

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecSyncAPDevices":{"post":{"summary":"Sync Autopilot Devices","description":"Syncs Autopilot devices","tags":["Autopilot"],"responses":{"200":{"description":"Autopilot devices synced successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## List Autopilot Configurations

> Retrieves a list of Autopilot configurations

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListAutopilotConfig":{"get":{"summary":"List Autopilot Configurations","description":"Retrieves a list of Autopilot configurations","tags":["Autopilot"],"parameters":[{"name":"type","in":"query","description":"The type of configuration to retrieve","required":false,"schema":{"type":"string","enum":["ESP","ApProfile"]}},{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Autopilot configurations retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"id":{"type":"string","description":"The configuration ID"},"displayName":{"type":"string","description":"The display name of the configuration"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## List Devices

> Retrieves a list of devices

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListDevices":{"get":{"summary":"List Devices","description":"Retrieves a list of devices","tags":["Devices"],"parameters":[{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Devices retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"id":{"type":"string","description":"The device ID"},"displayName":{"type":"string","description":"The display name of the device"},"manufacturer":{"type":"string","description":"The manufacturer of the device"},"model":{"type":"string","description":"The model of the device"},"osVersion":{"type":"string","description":"The OS version of the device"},"lastSyncDateTime":{"type":"string","description":"The last sync date and time"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## Execute Device Action

> Executes an action on a device

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecDeviceAction":{"post":{"summary":"Execute Device Action","description":"Executes an action on a device","tags":["Devices"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["Action","DeviceID"],"properties":{"Action":{"type":"string","description":"The action to perform","enum":["Reboot","Rename","Autopilot","Wipe","RemoveFromAutopilot","RemoveFromIntune","RemoveFromDefender","RemoveFromAzure","RemoveFromEverywhere","SyncDevice","FreshStart","QuickScan","FullScan","WindowsUpdateScan","WindowsUpdateReboot","WindowsUpdateDrivers","WindowsUpdateFeatures","WindowsUpdateOther","WindowsUpdateAll"]},"DeviceID":{"type":"string","description":"The device ID"},"NewDeviceName":{"type":"string","description":"The new device name (for Rename action)"}}}}}},"responses":{"200":{"description":"Device action executed successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Get Local Admin Password

> Retrieves the local admin password for a device

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecGetLocalAdminPassword":{"post":{"summary":"Get Local Admin Password","description":"Retrieves the local admin password for a device","tags":["Devices"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["DeviceID"],"properties":{"DeviceID":{"type":"string","description":"The device ID"}}}}}},"responses":{"200":{"description":"Local admin password retrieved successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Get Recovery Key

> Retrieves the recovery key for a device

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecGetRecoveryKey":{"post":{"summary":"Get Recovery Key","description":"Retrieves the recovery key for a device","tags":["Devices"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["DeviceID"],"properties":{"DeviceID":{"type":"string","description":"The device ID"}}}}}},"responses":{"200":{"description":"Recovery key retrieved successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## List Intune Policies

> Retrieves a list of Intune policies

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListIntunePolicy":{"get":{"summary":"List Intune Policies","description":"Retrieves a list of Intune policies","tags":["Intune"],"parameters":[{"name":"type","in":"query","description":"The type of policy to retrieve","required":false,"schema":{"type":"string","enum":["ESP"]}},{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Intune policies retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"id":{"type":"string","description":"The policy ID"},"displayName":{"type":"string","description":"The display name of the policy"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## Add Policy

> Adds a policy

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/AddPolicy":{"post":{"summary":"Add Policy","description":"Adds a policy","tags":["Intune"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","properties":{"selectedTenants":{"type":"array","description":"The tenants to add the policy to","items":{"type":"object","properties":{"customerId":{"type":"string","description":"The tenant ID"}}}}}}}}},"responses":{"200":{"description":"Policy added successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Add Intune Template

> Adds an Intune template

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/AddIntuneTemplate":{"post":{"summary":"Add Intune Template","description":"Adds an Intune template","tags":["Intune"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","properties":{"Type":{"type":"string","description":"The type of template"}}}}}},"responses":{"200":{"description":"Intune template added successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Assign Policy

> Assigns a policy

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecAssignPolicy":{"post":{"summary":"Assign Policy","description":"Assigns a policy","tags":["Intune"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["AssignTo","ID"],"properties":{"AssignTo":{"type":"string","description":"Who to assign the policy to","enum":["AllUsers","AllDevices","Both"]},"ID":{"type":"string","description":"The policy ID"}}}}}},"responses":{"200":{"description":"Policy assigned successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Remove Policy

> Removes a policy

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/RemovePolicy":{"post":{"summary":"Remove Policy","description":"Removes a policy","tags":["Intune"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["ID"],"properties":{"ID":{"type":"string","description":"The policy ID"}}}}}},"responses":{"200":{"description":"Policy removed successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## List Intune Templates

> Retrieves a list of Intune templates

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListIntuneTemplates":{"get":{"summary":"List Intune Templates","description":"Retrieves a list of Intune templates","tags":["Intune"],"parameters":[{"name":"View","in":"query","description":"Whether to view the templates","required":false,"schema":{"type":"boolean"}},{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Intune templates retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"GUID":{"type":"string","description":"The template ID"},"displayName":{"type":"string","description":"The display name of the template"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## Edit Template

> Edits a template

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecEditTemplate":{"post":{"summary":"Edit Template","description":"Edits a template","tags":["Intune"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","properties":{"GUID":{"type":"string","description":"The template ID"}}}}}},"responses":{"200":{"description":"Template edited successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Execute Community Repository

> Executes a community repository

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ExecCommunityRepo":{"post":{"summary":"Execute Community Repository","description":"Executes a community repository","tags":["Intune"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","properties":{"RepoURL":{"type":"string","description":"The repository URL"}}}}}},"responses":{"200":{"description":"Community repository executed successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## Remove Intune Template

> Removes an Intune template

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/RemoveIntuneTemplate":{"post":{"summary":"Remove Intune Template","description":"Removes an Intune template","tags":["Intune"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["ID"],"properties":{"ID":{"type":"string","description":"The template ID"}}}}}},"responses":{"200":{"description":"Intune template removed successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## List Intune Scripts

> Retrieves a list of Intune scripts

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListIntuneScript":{"get":{"summary":"List Intune Scripts","description":"Retrieves a list of Intune scripts","tags":["Intune"],"parameters":[{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Intune scripts retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"id":{"type":"string","description":"The script ID"},"displayName":{"type":"string","description":"The display name of the script"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## Remove Intune Script

> Removes an Intune script

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/RemoveIntuneScript":{"post":{"summary":"Remove Intune Script","description":"Removes an Intune script","tags":["Intune"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["ID"],"properties":{"ID":{"type":"string","description":"The script ID"}}}}}},"responses":{"200":{"description":"Intune script removed successfully","content":{"application/json":{"schema":{"$ref":"#/components/schemas/StandardResponse"}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```

## List Graph Request

> Retrieves data from a Graph API request

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListGraphRequest":{"get":{"summary":"List Graph Request","description":"Retrieves data from a Graph API request","tags":["Graph"],"parameters":[{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}},{"name":"endpoint","in":"query","description":"The Graph API endpoint","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Graph request data retrieved successfully","content":{"application/json":{"schema":{"type":"object","properties":{"Results":{"type":"array","items":{"type":"object"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## List Users

> Retrieves a list of users

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/listUsers":{"get":{"summary":"List Users","description":"Retrieves a list of users","tags":["Users"],"parameters":[{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Users retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"userPrincipalName":{"type":"string","description":"The user principal name"},"displayName":{"type":"string","description":"The display name of the user"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## List Apps Repository

> Searches the apps repository

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListAppsRepository":{"post":{"summary":"List Apps Repository","description":"Searches the apps repository","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["search"],"properties":{"search":{"type":"string","description":"The search query"}}}}}},"responses":{"200":{"description":"Apps repository search results retrieved successfully","content":{"application/json":{"schema":{"type":"object","properties":{"Results":{"type":"array","items":{"type":"object","properties":{"packagename":{"type":"string","description":"The package name"},"applicationName":{"type":"string","description":"The application name"},"description":{"type":"string","description":"The application description"}}}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## List Potential Apps

> Searches for potential apps

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListPotentialApps":{"post":{"summary":"List Potential Apps","description":"Searches for potential apps","tags":["Applications"],"requestBody":{"required":true,"content":{"application/json":{"schema":{"type":"object","required":["searchString","type"],"properties":{"searchString":{"type":"string","description":"The search query"},"type":{"type":"string","description":"The type of apps to search for","enum":["WinGet"]}}}}}},"responses":{"200":{"description":"Potential apps search results retrieved successfully","content":{"application/json":{"schema":{"type":"object","properties":{"data":{"type":"array","items":{"type":"object","properties":{"packagename":{"type":"string","description":"The package name"},"applicationName":{"type":"string","description":"The application name"},"description":{"type":"string","description":"The application description"}}}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## List Extensions Configuration

> Retrieves the configuration for extensions

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListExtensionsConfig":{"get":{"summary":"List Extensions Configuration","description":"Retrieves the configuration for extensions","tags":["Extensions"],"responses":{"200":{"description":"Extensions configuration retrieved successfully","content":{"application/json":{"schema":{"type":"object","additionalProperties":{"type":"object","properties":{"Enabled":{"type":"boolean","description":"Whether the extension is enabled"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## List Community Repositories

> Retrieves a list of community repositories

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"servers":[{"url":"/api","description":"CIPP API Server"}],"paths":{"/ListCommunityRepos":{"get":{"summary":"List Community Repositories","description":"Retrieves a list of community repositories","tags":["Intune"],"parameters":[{"name":"tenantFilter","in":"query","description":"The tenant to filter by","required":false,"schema":{"type":"string"}}],"responses":{"200":{"description":"Community repositories retrieved successfully","content":{"application/json":{"schema":{"type":"array","items":{"type":"object","properties":{"RepoURL":{"type":"string","description":"The repository URL"},"RepoName":{"type":"string","description":"The repository name"}}}}}}},"400":{"description":"Bad request"},"401":{"description":"Unauthorized"},"500":{"description":"Internal server error"}}}}}}
```

## The StandardResponse object

```json
{"openapi":"3.0.0","info":{"title":"CIPP Endpoint API","version":"1.0.0"},"components":{"schemas":{"StandardResponse":{"type":"object","properties":{"Results":{"type":"object","description":"The results of the operation"}}}}}}
```
