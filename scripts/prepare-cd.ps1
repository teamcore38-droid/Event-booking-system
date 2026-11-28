$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot)
$root = (Get-Location).Path
$out = Join-Path $root 'CD_Package'
$appOut = Join-Path $out 'Ticketdesk'
$sqlOut = Join-Path $out 'SQL'
$videoOut = Join-Path $out 'Video'

if (!(Test-Path (Join-Path $root 'vendor/autoload.php'))) {
    throw 'Missing vendor/autoload.php. Run composer install first so the CD copy works offline.'
}

if (Test-Path $out) {
    Remove-Item -LiteralPath $out -Recurse -Force
}
New-Item -ItemType Directory -Force $appOut, $sqlOut, $videoOut | Out-Null

$folders = @('app', 'public', 'scripts', 'tests', 'vendor')
foreach ($folder in $folders) {
    $src = Join-Path $root $folder
    if (Test-Path $src) {
        Copy-Item -LiteralPath $src -Destination (Join-Path $appOut $folder) -Recurse -Force
    }
}

$files = @(
    'composer.json',
    'composer.lock',
    'package.json',
    'package-lock.json',
    'README.md',
    'Start Ticketdesk.cmd',
    'Change-Proposal_Smart Service Booking System18.pdf'
)
foreach ($file in $files) {
    $src = Join-Path $root $file
    if (Test-Path $src) {
        Copy-Item -LiteralPath $src -Destination (Join-Path $appOut $file) -Force
    }
}

# Export live MySQL database on port 3318 if running; otherwise include schema.sql
$mysqldump = 'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqldump.exe'
$dbJson = Join-Path $root 'var/database.json'
$listening3318 = [bool](Get-NetTCPConnection -LocalPort 3318 -State Listen -ErrorAction SilentlyContinue)
$exportedSql = $false
if ($listening3318 -and (Test-Path $mysqldump) -and (Test-Path $dbJson)) {
    $cfg = Get-Content $dbJson -Raw | ConvertFrom-Json
    $sqlFile = Join-Path $sqlOut 'ticketdesk.sql'
    $env:MYSQL_PWD = $cfg.password
    try {
        & $mysqldump --host=127.0.0.1 --port=3318 --user=ticketdesk --no-tablespaces --routines --events --single-transaction ticketdesk --result-file="$sqlFile"
        if ($LASTEXITCODE -eq 0 -and (Test-Path $sqlFile)) {
            $exportedSql = $true
        }
    } finally {
        Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue
    }
}
if (!$exportedSql) {
    Copy-Item -LiteralPath (Join-Path $root 'app/schema.sql') -Destination (Join-Path $sqlOut 'schema.sql') -Force
    Write-Warning 'MySQL on port 3318 was not running; copied app/schema.sql instead of a full ticketdesk.sql dump. Start Ticketdesk first and re-run prepare-cd.ps1 if you want full data exported.'
}

# Build login-details.txt matching submission requirements
$localAccountsPath = Join-Path $root 'var/local-accounts.txt'
$adminStaffText = if (Test-Path $localAccountsPath) {
    (Get-Content $localAccountsPath -Raw).Trim()
} else {
    "Administrator: admin@ticketdesk.local (password generated in var/local-accounts.txt on first run)`r`nDoor staff:    staff@ticketdesk.local (password generated in var/local-accounts.txt on first run)"
}

$customerLines = @()
$php = 'C:/xampp/php/php.exe'
if ($listening3318 -and (Test-Path $php) -and (Test-Path $dbJson)) {
    $phpCode = @'
<?php
require 'app/db.php';
foreach (query("SELECT name, email FROM users WHERE role='participant' ORDER BY id") as $r) {
    echo $r['name'] . '|' . $r['email'] . PHP_EOL;
}
'@
    $rows = $phpCode | & $php
    foreach ($line in $rows) {
        if ($line -match '^(.*)\|(.*)$') {
            $customerLines += "- $($Matches[1]) | Email: $($Matches[2]) | Password: <enter password you registered>"
        }
    }
}
if ($customerLines.Count -eq 0) {
    $customerLines += @(
        '- Customer 1 | Email: <register on website> | Password: <password>',
        '- Customer 2 | Email: <register on website> | Password: <password>',
        '- Customer 3 | Email: <register on website> | Password: <password>',
        '- Customer 4 | Email: <register on website> | Password: <password>',
        '- Customer 5 | Email: <register on website> | Password: <password>'
    )
}

@"
TICKETDESK - LOGIN DETAILS
==========================

1. Admin & Door Staff Accounts (from current local database)
------------------------------------------------------------
$adminStaffText

Note: When Ticketdesk runs on a new computer for the first time without restoring
SQL/ticketdesk.sql, it creates fresh random passwords in:
- var\local-accounts.txt (if copied to a writable disk folder)
- %LOCALAPPDATA%\Ticketdesk\var\local-accounts.txt (if launched directly from CD)

2. Customer / Participant Accounts
----------------------------------
(Fill in the passwords for the customer accounts you registered and demonstrated.)
$($customerLines -join "`r`n")
"@ | Set-Content (Join-Path $out 'login-details.txt') -Encoding utf8

@"
Place your recorded demo video (.mp4) in this folder before burning CD_Package to a CD/DVD.
You can delete this reminder file once the video is added.
"@ | Set-Content (Join-Path $videoOut 'PUT_YOUR_DEMO_VIDEO_HERE.txt') -Encoding utf8

@"
TICKETDESK - CD PACKAGE GUIDE
=============================

Contents of this CD
-------------------
1. Ticketdesk\        Complete application source code + PHP vendor packages (offline-ready).
2. SQL\               Database export (ticketdesk.sql) or schema (schema.sql).
3. login-details.txt  Admin, Door Staff, and Customer login credentials.
4. Video\             Screen recording demonstrating the running system.

Prerequisites on Windows 10/11
------------------------------
- XAMPP with PHP 8.2 installed at C:\xampp
- MySQL Community Server 8.0 installed at C:\Program Files\MySQL\MySQL Server 8.0
- Ports 8186 (Apache) and 3318 (MySQL) must be free.

How to Run
----------
Option A - Copy to Hard Drive (Recommended):
  1. Copy the "Ticketdesk" folder from the CD to your Desktop.
  2. Double-click "Start Ticketdesk.cmd".
  3. Open http://127.0.0.1:8186/ in your browser.
  4. Check "var\local-accounts.txt" inside the Desktop\Ticketdesk folder for the
     generated Administrator and Door Staff passwords on that PC.

Option B - Run Directly from the CD:
  1. Double-click "Ticketdesk\Start Ticketdesk.cmd" on the CD.
  2. Because a CD is read-only, Ticketdesk automatically stores its runtime MySQL
     database and logs in: %LOCALAPPDATA%\Ticketdesk\var
  3. Open http://127.0.0.1:8186/ in your browser.
  4. Check "%LOCALAPPDATA%\Ticketdesk\var\local-accounts.txt" for the generated
     Administrator and Door Staff passwords.

Optional: Restoring SQL\ticketdesk.sql
--------------------------------------
First startup automatically creates the database tables, Admin/Staff accounts, and
6 sample events. If you want to restore the exact snapshot from SQL\ticketdesk.sql:
  1. Start Ticketdesk once so MySQL initializes on 127.0.0.1 port 3318.
  2. Connect with MySQL Workbench (host 127.0.0.1, port 3318, user "ticketdesk",
     password from var\database.json) and use Server -> Data Import to import
     SQL\ticketdesk.sql into the "ticketdesk" schema.
"@ | Set-Content (Join-Path $out 'README-CD.txt') -Encoding utf8

$sizeMB = [math]::Round(((Get-ChildItem $out -Recurse -Force | Measure-Object Length -Sum).Sum / 1MB), 2)
Write-Host "CD package created at: $out ($sizeMB MB)"
Write-Host "SQL exported: $exportedSql"
