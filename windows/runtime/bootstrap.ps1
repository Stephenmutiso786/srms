param(
  [Parameter(Mandatory=$true)][string]$InstallRoot
)

$ErrorActionPreference = 'Stop'
$php = Join-Path $InstallRoot 'runtime\php\php.exe'
$mysqlCandidates = @(
  (Join-Path $InstallRoot 'runtime\mysql\bin\mysql.exe'),
  (Join-Path $InstallRoot 'runtime\mysql\mysql.exe')
)
$schemaFile = Join-Path $InstallRoot 'installer\srms_mysql_schema_clean.sql'
$stateDir = Join-Path $InstallRoot 'data'
$markerFile = Join-Path $stateDir 'db_initialized.flag'
$app = Join-Path $InstallRoot 'script'
if (!(Test-Path $php)) { throw "PHP runtime missing at $php" }

if (!(Test-Path $stateDir)) {
  New-Item -ItemType Directory -Path $stateDir | Out-Null
}

if (!(Test-Path $markerFile)) {
  if (!(Test-Path $schemaFile)) {
    throw "Schema file missing at $schemaFile"
  }

  $mysqlExe = $mysqlCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1
  if (-not $mysqlExe) {
    throw "MySQL client not found. Put mysql.exe in runtime\\mysql\\bin or runtime\\mysql."
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

  $createDbArgs = @('-h', $dbHost, '-P', $dbPort, '-u', $dbUser)
  if (-not [string]::IsNullOrWhiteSpace($dbPass)) {
    $createDbArgs += "-p$dbPass"
  }
  $createDbArgs += @('-e', ('CREATE DATABASE IF NOT EXISTS `' + $dbName + '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;'))
  & $mysqlExe @createDbArgs | Out-Null

  $importArgs = @('-h', $dbHost, '-P', $dbPort, '-u', $dbUser)
  if (-not [string]::IsNullOrWhiteSpace($dbPass)) {
    $importArgs += "-p$dbPass"
  }
  $importArgs += $dbName
  Get-Content $schemaFile -Raw | & $mysqlExe @importArgs
  Set-Content -Path $markerFile -Value ("initialized=" + (Get-Date).ToString("o"))
}

Push-Location $app
try {
  Start-Process -WindowStyle Hidden -FilePath $php -ArgumentList @('-S', '127.0.0.1:8000', 'router.php')
  Start-Sleep -Seconds 2
} finally {
  Pop-Location
}
