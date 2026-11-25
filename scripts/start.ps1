$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot)
$root = (Get-Location).Path.Replace('\','/')
$mysql = 'C:/Program Files/MySQL/MySQL Server 8.0/bin/mysqld.exe'
$apache = 'C:/xampp/apache/bin/httpd.exe'
New-Item -ItemType Directory -Force var | Out-Null
if (!(Test-Path vendor/autoload.php)) { throw 'Run composer install first.' }
$env:PATH = "C:\xampp\php;$env:PATH"
function Listening($port) { [bool](Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue) }
function Wait-Port($port) {
    foreach ($attempt in 1..30) { if (Listening $port) { return }; Start-Sleep -Milliseconds 500 }
    throw "Port $port did not open. See var for server logs."
}
if (!(Test-Path var/mysql)) { & $mysql --no-defaults --initialize-insecure "--datadir=$root/var/mysql"; if ($LASTEXITCODE) {throw 'Database initialization failed'} }
if (!(Test-Path var/database.json)) {
    $config = @{password=[Guid]::NewGuid().ToString('N'); rootPassword=[Guid]::NewGuid().ToString('N')}
    $config | ConvertTo-Json | Set-Content var/database.json -Encoding ascii
}
$config = Get-Content var/database.json -Raw | ConvertFrom-Json
if (!(Listening 3318)) {
    @"
ALTER USER 'root'@'localhost' IDENTIFIED BY '$($config.rootPassword)';
CREATE DATABASE IF NOT EXISTS ticketdesk CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS 'ticketdesk'@'localhost' IDENTIFIED BY '$($config.password)';
GRANT ALL ON ticketdesk.* TO 'ticketdesk'@'localhost';
"@ | Set-Content var/init.sql -Encoding ascii
    Start-Process $mysql -ArgumentList "--no-defaults --datadir=`"$root/var/mysql`" --port=3318 --bind-address=127.0.0.1 --mysqlx=0 --init-file=`"$root/var/init.sql`"" -WindowStyle Hidden
    Wait-Port 3318
    Remove-Item -LiteralPath var/init.sql
}
php scripts/install.php
if ($LASTEXITCODE) { throw 'Application setup failed' }
if (!(Listening 8186)) {
@"
ServerRoot "C:/xampp/apache"
Listen 127.0.0.1:8186
ServerName localhost
PidFile "$root/var/httpd.pid"
ErrorLog "$root/var/apache.log"
LoadModule mpm_winnt_module modules/mod_mpm_winnt.so
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "C:/xampp/php"
TypesConfig conf/mime.types
AddHandler application/x-httpd-php .php
DirectoryIndex index.php
DocumentRoot "$root/public"
<Directory "$root/public">
    Require all granted
    Options -Indexes
</Directory>
"@.Replace("LoadModule mpm_winnt_module modules/mod_mpm_winnt.so","") | Set-Content var/apache.conf -Encoding ascii
    $env:PATH = "C:\xampp\php;C:\xampp\apache\bin;$env:PATH"
    Start-Process $apache -ArgumentList "-f `"$root/var/apache.conf`"" -WindowStyle Hidden
    Wait-Port 8186
}
Write-Host 'Open http://127.0.0.1:8186/'
