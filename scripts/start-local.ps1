<#
.SYNOPSIS
    Start the local MAXXfam development stack (MySQL on 3307, PHP on 8080).
.NOTES
    MySQL data lives in .runtime/mysql-data (gitignored). PHP lives in .runtime/php.
    Logs: .runtime/logs/
#>
[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$runtime = Join-Path $root '.runtime'
$logs = Join-Path $runtime 'logs'
New-Item -ItemType Directory -Force -Path $logs | Out-Null

function Test-Port([int]$Port) {
    $result = Test-NetConnection -ComputerName 127.0.0.1 -Port $Port -InformationLevel Quiet
    return [bool]$result
}

function Start-Hidden($FilePath, $Arguments, $LogName) {
    $log = Join-Path $logs "$LogName.log"
    $err = Join-Path $logs "$LogName.err.log"
    return Start-Process -FilePath $FilePath -ArgumentList $Arguments `
        -WorkingDirectory $root -WindowStyle Hidden `
        -RedirectStandardOutput $log -RedirectStandardError $err -PassThru
}

if (Test-Port 3307) {
    Write-Output 'MySQL already listening on 127.0.0.1:3307.'
} else {
    $base = Join-Path $runtime 'mysql'
    $data = Join-Path $runtime 'mysql-data'
    if (-not (Test-Path (Join-Path $base 'bin\mysqld.exe'))) { throw "MySQL not found at $base" }
    if (-not (Test-Path (Join-Path $data 'mysql'))) { throw "MySQL data directory missing at $data" }
    $p = Start-Hidden (Join-Path $base 'bin\mysqld.exe') @(
        "--basedir=$base",
        "--datadir=$data",
        '--port=3307',
        '--bind-address=127.0.0.1',
        '--mysqlx=0',
        '--console') 'mysql'
    Write-Output "Starting MySQL (pid $($p.Id))..."
    $deadline = (Get-Date).AddSeconds(60)
    while (-not (Test-Port 3307) -and (Get-Date) -lt $deadline) { Start-Sleep -Seconds 2 }
    if (-not (Test-Port 3307)) { throw "MySQL did not open port 3307. See $logs\mysql.err.log" }
    Write-Output 'MySQL listening on 127.0.0.1:3307.'
}

if (Test-Port 8080) {
    Write-Output 'PHP dev server already listening on 127.0.0.1:8080.'
} else {
    $php = Join-Path $runtime 'php\php.exe'
    if (-not (Test-Path $php)) { throw "PHP not found at $php" }
    $p = Start-Hidden $php @('-S', '127.0.0.1:8080', '-t', 'public', 'public/router.php') 'php-server'
    Write-Output "Starting PHP dev server (pid $($p.Id))..."
    Start-Sleep -Seconds 3
    if (-not (Test-Port 8080)) { throw "PHP dev server did not open port 8080. See $logs\php-server.err.log" }
    Write-Output 'PHP dev server listening on http://127.0.0.1:8080.'
}

Write-Output ''
Write-Output 'MAXXfam local:  http://127.0.0.1:8080'
Write-Output 'Admin login:    http://127.0.0.1:8080/admin/login.php'
Write-Output 'Stop MySQL:     & .\.runtime\mysql\bin\mysqladmin.exe --protocol=TCP -h 127.0.0.1 -P 3307 -u root shutdown'
