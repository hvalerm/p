param(
    [Parameter(Mandatory = $true)]
    [string] $Direccion
)

$ErrorActionPreference = 'Stop'
$ip = $null
if (-not [System.Net.IPAddress]::TryParse($Direccion, [ref] $ip) -or
    $ip.AddressFamily -ne [System.Net.Sockets.AddressFamily]::InterNetwork -or
    $ip.ToString() -eq '127.0.0.1') {
    throw 'Indica la dirección IPv4 de la computadora en la red local.'
}
if (-not (Get-NetIPAddress -AddressFamily IPv4 -ErrorAction Stop | Where-Object { $_.IPAddress -eq $Direccion -and $_.AddressState -eq 'Preferred' })) {
    throw 'La dirección indicada no está activa en esta computadora.'
}
if (Get-NetTCPConnection -LocalPort 8443 -State Listen -ErrorAction SilentlyContinue) {
    throw 'El puerto 8443 ya está en uso. Detén el servidor anterior antes de iniciar otro.'
}

$apache = 'C:\xampp\apache\bin\httpd.exe'
$openssl = 'C:\xampp\apache\bin\openssl.exe'
$opensslConfig = 'C:\xampp\apache\conf\openssl.cnf'
$phpModule = 'C:\xampp\php\php8apache2_4.dll'
foreach ($archivo in @($apache, $openssl, $opensslConfig, $phpModule, (Join-Path $PSScriptRoot 'ca.pem'))) {
    if (-not (Test-Path -LiteralPath $archivo -PathType Leaf)) {
        throw "No se encuentra el archivo necesario: $archivo"
    }
}

$privado = Join-Path $env:LOCALAPPDATA 'OpenDoorsVista'
if (-not (Test-Path -LiteralPath $privado)) {
    New-Item -ItemType Directory -Path $privado | Out-Null
}
$usuario = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls.exe $privado /inheritance:r /grant:r "${usuario}:(OI)(CI)F" /grant:r '*S-1-5-18:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'No se pudieron proteger los archivos de certificados.' }

$caKey = Join-Path $privado 'autoridad.key'
$caCert = Join-Path $privado 'autoridad.crt'
$key = Join-Path $privado 'servidor.key'
$csr = Join-Path $privado 'servidor.csr'
$cert = Join-Path $privado 'servidor.crt'
$config = Join-Path $privado 'httpd.conf'
if ((Test-Path -LiteralPath $caKey) -xor (Test-Path -LiteralPath $caCert)) {
    throw 'La autoridad local está incompleta. Recupera su certificado y clave antes de continuar.'
}
if (-not (Test-Path -LiteralPath $caKey)) {
    & $openssl req -config $opensslConfig -quiet -x509 -newkey rsa:3072 -sha256 -nodes -days 365 -subj '/CN=OpenDoors Vista Local' -keyout $caKey -out $caCert
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la autoridad local.' }
}
& $openssl req -config $opensslConfig -quiet -new -newkey rsa:2048 -sha256 -nodes -subj '/CN=OpenDoors Vista' -addext "subjectAltName=IP:$Direccion,IP:127.0.0.1,DNS:localhost" -keyout $key -out $csr
if ($LASTEXITCODE -ne 0) { throw 'No se pudo crear la solicitud del certificado HTTPS.' }
& $openssl x509 -req -in $csr -CA $caCert -CAkey $caKey -CAcreateserial -out $cert -days 30 -sha256 -copy_extensions copy
if ($LASTEXITCODE -ne 0) { throw 'No se pudo firmar el certificado HTTPS.' }
Remove-Item -LiteralPath $csr

function ApachePath([string] $path) {
    return $path.Replace('\', '/')
}
$root = ApachePath $PSScriptRoot
$privatePath = ApachePath $privado
$certPath = ApachePath $cert
$keyPath = ApachePath $key
$configuration = @"
ServerRoot "C:/xampp/apache"
Listen 127.0.0.1:8443
Listen ${Direccion}:8443
ServerName localhost:8443
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule ssl_module modules/mod_ssl.so
LoadModule socache_shmcb_module modules/mod_socache_shmcb.so
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "C:/xampp/php"
TypesConfig conf/mime.types
AddHandler application/x-httpd-php .php
DirectoryIndex index.php
ErrorLog "$privatePath/error.log"
LogLevel warn
SSLSessionCache "shmcb:$privatePath/sslcache(512000)"
SSLProtocol -all +TLSv1.2 +TLSv1.3
DocumentRoot "$root"
<Directory "$root">
    Options -Indexes -Includes -ExecCGI
    AllowOverride None
    Require all denied
</Directory>
<LocationMatch "^/(?:$|index\.php|login\.php|logout\.php|ModoAnfitrion/vistaAnfitrion\.php|ElementosVistaBasica/img/OpenDoors\.png)$">
    Require all granted
</LocationMatch>
<LocationMatch "^/Modulos/[^/]+/(?:Nucleo|Configuracion)/(?!(?i:util)(?:/|$)|.*/(?i:util)(?:/|$))(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.(?:php|css|js|png|jpg|svg)$">
    Require all granted
</LocationMatch>
SSLEngine on
SSLCertificateFile "$certPath"
SSLCertificateKeyFile "$keyPath"
"@
[System.IO.File]::WriteAllText($config, $configuration, [System.Text.UTF8Encoding]::new($false))
& $apache -t -f $config
if ($LASTEXITCODE -ne 0) { throw 'La configuración HTTPS no es válida.' }
Write-Host "Instala el certificado de confianza $caCert en tu computadora y celular."
Write-Host "Abre https://${Direccion}:8443/ y detén el servidor con Ctrl+C."
& $apache -X -f $config
if ($LASTEXITCODE -ne 0) { throw 'Apache no pudo iniciar. Revisa el puerto y el archivo error.log en la carpeta privada.' }
