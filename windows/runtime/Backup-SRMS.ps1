param(
  [Parameter(Mandatory = $true)][string]$InstallRoot
)

$ErrorActionPreference = 'Stop'
$stateDir = Join-Path $InstallRoot 'data'
$backupDir = Join-Path $stateDir 'backups'
$mysqldumpCandidates = @(
  (Join-Path $InstallRoot 'runtime\mysql\bin\mysqldump.exe'),
  (Join-Path $InstallRoot 'runtime\mysql\mysqldump.exe')
)

if (!(Test-Path $backupDir)) {
  New-Item -ItemType Directory -Path $backupDir | Out-Null
}

$mysqldumpExe = $mysqldumpCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $mysqldumpExe) {
  throw 'mysqldump.exe not found in the bundled MySQL runtime.'
}

$dbName = $env:SRMS_DB_NAME
if ([string]::IsNullOrWhiteSpace($dbName)) { $dbName = 'srms' }
$dbHost = $env:SRMS_DB_HOST
if ([string]::IsNullOrWhiteSpace($dbHost)) { $dbHost = '127.0.0.1' }
$dbPort = $env:SRMS_DB_PORT
if ([string]::IsNullOrWhiteSpace($dbPort)) { $dbPort = '3306' }
$dbUser = $env:SRMS_DB_USER
if ([string]::IsNullOrWhiteSpace($dbUser)) { $dbUser = 'root' }
$dbPass = $env:SRMS_DB_PASS

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$sqlFile = Join-Path $backupDir ("srms-backup-{0}.sql" -f $stamp)
$zipFile = Join-Path $backupDir ("srms-backup-{0}.zip" -f $stamp)

$args = @('-h', $dbHost, '-P', $dbPort, '-u', $dbUser, '--single-transaction', '--routines', '--triggers', '--events')
if (-not [string]::IsNullOrWhiteSpace($dbPass)) {
  $args += "-p$dbPass"
}
$args += $dbName

& $mysqldumpExe @args | Set-Content -Path $sqlFile -Encoding UTF8

Compress-Archive -Path $sqlFile -DestinationPath $zipFile -Force
Remove-Item $sqlFile -Force

Write-Host "Backup created: $zipFile"

