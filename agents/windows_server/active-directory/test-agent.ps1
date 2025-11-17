<#
.SYNOPSIS
    Test script for AD Sync Agent

.DESCRIPTION
    Tests various components of the AD Sync Agent without pushing to API

.PARAMETER TestADConnection
    Test Active Directory connectivity

.PARAMETER TestAPIConnection
    Test API connectivity

.PARAMETER TestDataCollection
    Test data collection (users, groups)

.PARAMETER ShowSample
    Show sample of collected data
#>

[CmdletBinding()]
param(
    [Parameter(Mandatory=$false)]
    [switch]$TestADConnection,
    
    [Parameter(Mandatory=$false)]
    [switch]$TestAPIConnection,
    
    [Parameter(Mandatory=$false)]
    [switch]$TestDataCollection,
    
    [Parameter(Mandatory=$false)]
    [switch]$ShowSample,
    
    [Parameter(Mandatory=$false)]
    [string]$ConfigPath = ".\config.json"
)

$ErrorActionPreference = "Continue"

# Get script directory
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "=== MSP Reports AD Sync Agent - Test Script ===" -ForegroundColor Cyan
Write-Host ""

# Load configuration
if (Test-Path $ConfigPath) {
    $Config = Get-Content $ConfigPath -Raw | ConvertFrom-Json
    Write-Host "✓ Configuration loaded from: $ConfigPath" -ForegroundColor Green
} else {
    Write-Host "✗ Configuration file not found: $ConfigPath" -ForegroundColor Red
    exit 1
}

# Test AD Connection
if ($TestADConnection) {
    Write-Host ""
    Write-Host "=== Testing Active Directory Connection ===" -ForegroundColor Cyan
    
    try {
        Import-Module ActiveDirectory -ErrorAction Stop
        Write-Host "✓ Active Directory module loaded" -ForegroundColor Green
        
        # Get domain info
        $Domain = Get-ADDomain -ErrorAction Stop
        Write-Host "✓ Connected to domain: $($Domain.DNSRoot)" -ForegroundColor Green
        Write-Host "  Domain functional level: $($Domain.DomainMode)" -ForegroundColor Gray
        Write-Host "  Forest: $($Domain.Forest)" -ForegroundColor Gray
        
        # Test user query
        $TestUser = Get-ADUser -Filter * -ResultSetSize 1 -ErrorAction Stop
        Write-Host "✓ Successfully queried users (sample: $($TestUser.Name))" -ForegroundColor Green
        
        # Test group query
        $TestGroup = Get-ADGroup -Filter "GroupCategory -eq 'Security'" -ResultSetSize 1 -ErrorAction Stop
        Write-Host "✓ Successfully queried groups (sample: $($TestGroup.Name))" -ForegroundColor Green
        
    } catch {
        Write-Host "✗ Active Directory test failed: $_" -ForegroundColor Red
    }
}

# Test API Connection
if ($TestAPIConnection) {
    Write-Host ""
    Write-Host "=== Testing API Connection ===" -ForegroundColor Cyan
    
    try {
        Import-Module "$ScriptDir\modules\APIClient.psm1" -Force
        
        # Get API key
        $ApiKey = $null
        if ($Config.advanced.useSecureCredentialStore) {
            $ApiKey = Get-StoredCredential -Target "MSPReports-AD-Agent"
        }
        if (-not $ApiKey) {
            $ApiKey = $Config.platform.apiKey
        }
        
        if (-not $ApiKey) {
            Write-Host "✗ No API key found in config or credential store" -ForegroundColor Red
        } else {
            Write-Host "  Testing connection to: $($Config.platform.apiUrl)" -ForegroundColor Gray
            
            $Result = Test-APIConnection -ApiUrl $Config.platform.apiUrl -ApiKey $ApiKey
            
            if ($Result.Success) {
                Write-Host "✓ API connection successful" -ForegroundColor Green
                Write-Host "  Response: $($Result.Message)" -ForegroundColor Gray
            } else {
                Write-Host "✗ API connection failed: $($Result.Error)" -ForegroundColor Red
            }
        }
        
    } catch {
        Write-Host "✗ API test failed: $_" -ForegroundColor Red
    }
}

# Test Data Collection
if ($TestDataCollection) {
    Write-Host ""
    Write-Host "=== Testing Data Collection ===" -ForegroundColor Cyan
    
    try {
        Import-Module "$ScriptDir\modules\ADDataCollector.psm1" -Force
        Import-Module ActiveDirectory -ErrorAction Stop
        
        # Collect users
        Write-Host "  Collecting users..." -ForegroundColor Gray
        $Users = Get-ADUsersData -Config $Config.activeDirectory -Verbose:$false
        Write-Host "✓ Collected $($Users.Count) users" -ForegroundColor Green
        
        # Collect groups
        Write-Host "  Collecting security groups..." -ForegroundColor Gray
        $Groups = Get-ADSecurityGroups -Config $Config.activeDirectory -Verbose:$false
        Write-Host "✓ Collected $($Groups.Count) security groups" -ForegroundColor Green
        
        # Collect memberships
        Write-Host "  Collecting group memberships..." -ForegroundColor Gray
        $Memberships = Get-ADGroupMemberships -Groups $Groups -Config $Config.activeDirectory -Verbose:$false
        Write-Host "✓ Collected $($Memberships.Count) membership records" -ForegroundColor Green
        
        # Calculate payload size
        $Payload = @{
            data = @{
                users = $Users
                securityGroups = $Groups
                groupMemberships = $Memberships
            }
        }
        $PayloadJson = $Payload | ConvertTo-Json -Depth 10 -Compress
        $PayloadSize = [System.Text.Encoding]::UTF8.GetByteCount($PayloadJson)
        
        Write-Host ""
        Write-Host "Data Summary:" -ForegroundColor Cyan
        Write-Host "  Users: $($Users.Count)" -ForegroundColor Gray
        Write-Host "  Security Groups: $($Groups.Count)" -ForegroundColor Gray
        Write-Host "  Group Memberships: $($Memberships.Count)" -ForegroundColor Gray
        Write-Host "  Payload Size: $([Math]::Round($PayloadSize / 1KB, 2)) KB" -ForegroundColor Gray
        
        # Show sample if requested
        if ($ShowSample) {
            Write-Host ""
            Write-Host "Sample User:" -ForegroundColor Cyan
            $Users | Select-Object -First 1 | ConvertTo-Json -Depth 2 | Write-Host -ForegroundColor Gray
            
            Write-Host ""
            Write-Host "Sample Group:" -ForegroundColor Cyan
            $Groups | Select-Object -First 1 | ConvertTo-Json -Depth 2 | Write-Host -ForegroundColor Gray
            
            Write-Host ""
            Write-Host "Sample Membership:" -ForegroundColor Cyan
            $Memberships | Select-Object -First 1 | ConvertTo-Json -Depth 2 | Write-Host -ForegroundColor Gray
        }
        
    } catch {
        Write-Host "✗ Data collection test failed: $_" -ForegroundColor Red
    }
}

# If no specific test requested, run all tests
if (-not $TestADConnection -and -not $TestAPIConnection -and -not $TestDataCollection) {
    Write-Host "No specific test requested. Running all tests..." -ForegroundColor Yellow
    Write-Host ""
    
    & $MyInvocation.MyCommand.Path -TestADConnection -TestAPIConnection -TestDataCollection -ConfigPath $ConfigPath
}

Write-Host ""
Write-Host "=== Test Complete ===" -ForegroundColor Green
Write-Host ""
