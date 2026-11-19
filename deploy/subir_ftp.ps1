<#
  Publica el sitio en Hostinger por FTP. Guía paso a paso: docs\DESPLIEGUE_HOSTINGER.md (o .docx)

  Uso (desde la carpeta del proyecto):
    deploy\subir.bat -Iniciar       Crea la carpeta privado\ con las plantillas a llenar
    deploy\subir.bat -PrepararSql   Genera en privado\sql\ los .sql para phpMyAdmin
    deploy\subir.bat -SubirConfig   Sube la configuración de producción, SOLO si el
                                    servidor todavía no tiene bd/config.local.php
    deploy\subir.bat -Simular       Solo muestra qué se subiría; no toca el servidor
    deploy\subir.bat -Todo          Sube todos los archivos del sitio
    deploy\subir.bat                Sube solo lo que cambió desde la última subida
    deploy\subir.bat -Borrar        Además borra del servidor los archivos que este
                                    script subió antes y que ya no existen en local

  Todo lo privado vive en privado\ (dentro del proyecto, ignorado por Git y que
  nunca se sube al servidor):
    privado\ftp.env                 FTP_HOST, FTP_USER, FTP_PASS (CLAVE=valor)
                                    opcionales: FTP_PORT=21, FTP_DIR=, FTP_TLS=0
    privado\config_produccion.php   datos de la base de Hostinger
    privado\estado_ftp.json         qué se subió y cuándo cambió (lo maneja el script)
    privado\sql\                    los .sql generados

  Nunca sube: .git, Pantallas, docs, pruebas, sql, deploy, privado, documentación .md/.docx,
  verificar.php ni bd/config.*.php. Antes de subir siempre pide escribir SI.
#>
param(
    [switch]$Iniciar,
    [switch]$Todo,
    [switch]$Simular,
    [switch]$SubirConfig,
    [switch]$Borrar,
    [switch]$PrepararSql
)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

$Raiz = Split-Path -Parent $PSScriptRoot
$Privado = Join-Path $Raiz 'privado'
$Credenciales = Join-Path $Privado 'ftp.env'
$Utf8SinBom = New-Object System.Text.UTF8Encoding($false)

# Rutas (relativas, con /) que nunca se publican.
$Excluir = @(
    '^\.git/', '^\.git(ignore|attributes)$', '^\.vscode/', '^\.idea/',
    '^Pantallas/', '^docs/', '^pruebas/', '^pruebas\.php$', '^sql/', '^deploy/', '^privado/', '^uploads/',
    '^bd/config\.[^/]*\.php$', '^verificar\.php$',
    '\.(md|docx)$', '(^|/)\.env$', '\.log$', '(^|/)(Thumbs\.db|desktop\.ini|\.DS_Store)$'
)
$ConfigRemota = 'bd/config.local.php'

function Excluido([string]$rel) {
    foreach ($p in $Excluir) { if ($rel -match $p) { return $true } }
    return $false
}

function Leer([string]$ruta) { return [System.IO.File]::ReadAllText($ruta, [System.Text.Encoding]::UTF8) }
function Escribir([string]$ruta, [string]$texto) { [System.IO.File]::WriteAllText($ruta, $texto, $Utf8SinBom) }

# ---------------------------------------------------------------------
# -Iniciar: carpeta privado\ con plantillas (no sobrescribe nada)
# ---------------------------------------------------------------------
if ($Iniciar) {
    New-Item -ItemType Directory -Force -Path (Join-Path $Privado 'sql') | Out-Null
    $plantillas = [ordered]@{
        '.htaccess' = "Require all denied`r`n"
        'ftp.env' = "# Datos del FTP que te dio el profesor o hPanel (Archivos > Cuentas FTP)`r`nFTP_HOST=`r`nFTP_USER=`r`nFTP_PASS=`r`nFTP_PORT=21`r`n"
        'config_produccion.php' = @"
<?php
// Configuración de la base en Hostinger (hPanel > Bases de datos > MySQL).
// Se sube UNA vez como bd/config.local.php con: deploy\subir.bat -SubirConfig
return [
    'db_host'     => 'localhost',          // si no conecta, el host de base de datos que te dieron
    'db_name'     => '',                    // nombre completo de la base (con su prefijo u..._)
    'db_user'     => '',                    // usuario de la base (con su prefijo u..._)
    'db_pass'     => '',
    'db_timezone' => '-06:00',
    'debug'       => false,                 // en el servidor siempre false
];
"@
    }
    foreach ($nombre in $plantillas.Keys) {
        $ruta = Join-Path $Privado $nombre
        if (Test-Path $ruta) { Write-Host "Ya existe (no se toca): privado\$nombre"; continue }
        Escribir $ruta ($plantillas[$nombre] -replace "(?<!`r)`n", "`r`n")
        Write-Host "Creado: privado\$nombre"
    }
    Write-Host ''
    Write-Host 'Ahora llena los datos del FTP en privado\ftp.env y los de la base en privado\config_produccion.php.'
    exit 0
}

# ---------------------------------------------------------------------
# -PrepararSql: .sql para importar en phpMyAdmin
# ---------------------------------------------------------------------
if ($PrepararSql) {
    $dirSql = Join-Path $Privado 'sql'
    New-Item -ItemType Directory -Force -Path $dirSql | Out-Null
    Get-ChildItem -LiteralPath $dirSql -Filter '*.sql' | Remove-Item

    # 0) Borrar todo: deja la base vacía para empezar de nuevo.
    Escribir (Join-Path $dirSql '0_borrar_todo.sql') @"
-- BORRA todas las tablas, vistas y el procedimiento del Banco de Proyectos
-- (los triggers se van con sus tablas). Úsalo solo para empezar de cero;
-- después importa 1_estructura.sql y el archivo 2_ que corresponda.
SET FOREIGN_KEY_CHECKS = 0;
DROP VIEW IF EXISTS v_proyecto_resumen, v_carga_profesor, v_carga_alumno, v_candidatura_vigente;
DROP PROCEDURE IF EXISTS sp_siguiente_folio;
DROP TABLE IF EXISTS historial_auditoria, notificacion, asignacion_profesor, asignacion_alumno,
  autorizacion, evaluacion_criterio, evaluacion_candidato, solicitud_direccion,
  solicitud_candidatura, requisito_proyecto, perfil_habilidad, proyecto, criterio, habilidad,
  administrador, profesor, empresa, alumno, consecutivo, usuario;
SET FOREIGN_KEY_CHECKS = 1;
"@

    # 1) Estructura sin CREATE DATABASE / USE (en Hostinger la base ya existe).
    $texto = Leer (Join-Path $Raiz 'sql\banco_proyectos.sql')
    $patron = '(?ms)^CREATE DATABASE IF NOT EXISTS banco_proyectos.*?^USE banco_proyectos;[ \t]*\r?$'
    if ($texto -notmatch $patron) { throw 'No encontré las líneas CREATE DATABASE / USE en banco_proyectos.sql.' }
    $texto = [regex]::Replace($texto, $patron, '-- (CREATE DATABASE / USE quitados: en Hostinger la base ya existe y se elige en phpMyAdmin)')
    Escribir (Join-Path $dirSql '1_estructura.sql') $texto

    # 2) Datos: de prueba (para revisar) o un administrador real.
    Write-Host ''
    $r = Read-Host '¿Cargar los usuarios de prueba (contraseña Prueba2026!) para revisar el sitio? (s/n)'
    if ($r -match '^[sS]') {
        Copy-Item -LiteralPath (Join-Path $Raiz 'sql\datos_prueba.sql') -Destination (Join-Path $dirSql '2_datos_prueba.sql')
        $segundo = '2_datos_prueba.sql'
    } else {
        $php = 'C:\xampp\php\php.exe'
        if (-not (Test-Path $php)) { throw "No encontré $php (se usa para calcular el hash de la contraseña)." }
        Write-Host 'Datos del primer administrador del sitio:'
        $nombre = Read-Host '  Nombre completo'
        $correo = Read-Host '  Correo (con este inicias sesión)'
        $numEmp = Read-Host '  Número de empleado (máx. 10 caracteres)'
        $cargo  = Read-Host '  Cargo (ej. Coordinación de Vinculación)'
        $seg1 = Read-Host '  Contraseña' -AsSecureString
        $seg2 = Read-Host '  Repite la contraseña' -AsSecureString
        $pw1 = [System.Net.NetworkCredential]::new('', $seg1).Password
        $pw2 = [System.Net.NetworkCredential]::new('', $seg2).Password
        if ($pw1 -ne $pw2) { throw 'Las contraseñas no coinciden.' }
        if ($pw1.Length -lt 8) { throw 'Usa una contraseña de al menos 8 caracteres.' }
        if (-not $nombre -or -not $correo -or -not $numEmp -or -not $cargo) { throw 'Todos los datos son obligatorios.' }
        if ($numEmp.Length -gt 10) { throw 'El número de empleado tiene más de 10 caracteres.' }

        # La contraseña va por la entrada estándar, no en la línea de comandos.
        $OutputEncoding = $Utf8SinBom
        $hash = ($pw1 | & $php -r 'echo password_hash(rtrim(fgets(STDIN), chr(13).chr(10)), PASSWORD_BCRYPT);')
        $pw1 = $null; $pw2 = $null
        if ($hash -notmatch '^\$2y\$') { throw 'No se pudo calcular el hash con PHP.' }

        function Sql([string]$s) { return $s.Replace('\', '\\').Replace("'", "''") }
        # .Replace es literal: el hash lleva $ y no debe interpretarse.
        $datos = (Leer (Join-Path $Raiz 'sql\datos_iniciales.sql')).Replace('{{NOMBRE}}', (Sql $nombre))
        $datos = $datos.Replace('{{CORREO}}', (Sql $correo))
        $datos = $datos.Replace('{{NUM_EMPLEADO}}', (Sql $numEmp))
        $datos = $datos.Replace('{{CARGO}}', (Sql $cargo))
        $datos = $datos.Replace('{{HASH}}', $hash)
        $segundo = '2_datos_iniciales.sql'
        Escribir (Join-Path $dirSql $segundo) $datos
    }

    Write-Host ''
    Write-Host "Listo, en privado\sql\. En phpMyAdmin, con la base de Hostinger seleccionada, importa:"
    Write-Host '  1_estructura.sql'
    Write-Host "  $segundo"
    Write-Host 'Si algo sale mal: importa 0_borrar_todo.sql y vuelve a importar los dos anteriores.'
    exit 0
}

# ---------------------------------------------------------------------
# Credenciales FTP
# ---------------------------------------------------------------------
if (-not (Test-Path $Credenciales)) {
    throw "No encuentro privado\ftp.env. Corre primero: deploy\subir.bat -Iniciar"
}
$cfg = @{}
foreach ($linea in Get-Content -LiteralPath $Credenciales -Encoding UTF8) {
    $l = $linea.Trim()
    if (-not $l -or $l.StartsWith('#') -or -not $l.Contains('=')) { continue }
    $i = $l.IndexOf('=')
    $cfg[$l.Substring(0, $i).Trim()] = $l.Substring($i + 1).Trim().Trim('"')
}
foreach ($k in 'FTP_HOST', 'FTP_USER', 'FTP_PASS') {
    if (-not $cfg.ContainsKey($k) -or -not $cfg[$k]) { throw "Falta $k en $Credenciales." }
}
$Puerto = if ($cfg.ContainsKey('FTP_PORT') -and $cfg['FTP_PORT']) { [int]$cfg['FTP_PORT'] } else { 21 }
$DirRemoto = if ($cfg.ContainsKey('FTP_DIR')) { $cfg['FTP_DIR'].Trim('/') } else { '' }
$Tls = $cfg.ContainsKey('FTP_TLS') -and $cfg['FTP_TLS'] -eq '1'
$ConfigProd = Join-Path $Privado 'config_produccion.php'
$Estado = Join-Path $Privado 'estado_ftp.json'

# ---------------------------------------------------------------------
# FTP
# ---------------------------------------------------------------------
function Uri-Remota([string]$rel) {
    $partes = @()
    if ($DirRemoto) { $partes += $DirRemoto.Split('/') }
    if ($rel) { $partes += $rel.Split('/') }
    $ruta = ($partes | Where-Object { $_ } | ForEach-Object { [Uri]::EscapeDataString($_) }) -join '/'
    return "ftp://$($cfg['FTP_HOST']):$Puerto/$ruta"
}

function Peticion([string]$rel, [string]$metodo) {
    $req = [System.Net.FtpWebRequest]::Create((Uri-Remota $rel))
    $req.Method = $metodo
    $req.Credentials = New-Object System.Net.NetworkCredential($cfg['FTP_USER'], $cfg['FTP_PASS'])
    $req.UseBinary = $true
    $req.UsePassive = $true
    $req.KeepAlive = $false   # una conexión por operación: evita que el servidor arrastre la carpeta actual
    $req.EnableSsl = $Tls
    $req.Timeout = 60000
    return $req
}

function Error-Web($ex) {
    $e = $ex
    while ($e -and -not ($e -is [System.Net.WebException])) { $e = $e.InnerException }
    return $e
}

function Codigo-Ftp($ex) {
    $e = Error-Web $ex
    if ($e -and $e.Response) { return [int]$e.Response.StatusCode }
    return 0
}

function Mensaje-Ftp($ex) {
    $e = Error-Web $ex
    if ($e -and $e.Response) { return ([string]$e.Response.StatusDescription).Trim() }
    return $ex.Message
}

function Existe-Remoto([string]$rel) {
    try {
        $r = (Peticion $rel ([System.Net.WebRequestMethods+Ftp]::GetFileSize)).GetResponse()
        $r.Close()
        return $true
    } catch {
        if ((Codigo-Ftp $_.Exception) -eq 550) { return $false }
        throw
    }
}

$CarpetasListas = @{}
function Asegurar-Carpeta([string]$relDir, [switch]$Detallado) {
    if (-not $relDir -or $CarpetasListas.ContainsKey($relDir)) { return }
    $padre = ($relDir -replace '/[^/]+$', '')
    if ($padre -ne $relDir) { Asegurar-Carpeta $padre -Detallado:$Detallado }
    try {
        $r = (Peticion $relDir ([System.Net.WebRequestMethods+Ftp]::MakeDirectory)).GetResponse()
        if ($Detallado) { Write-Host "   carpeta creada: $relDir" }
        $r.Close()
    } catch {
        if ((Codigo-Ftp $_.Exception) -notin 550, 521) { throw }
        # 550/521: normalmente "ya existe"; si no, la subida lo dirá.
        if ($Detallado) { Write-Host "   carpeta ${relDir}: $(Mensaje-Ftp $_.Exception)" -ForegroundColor Yellow }
    }
    $CarpetasListas[$relDir] = $true
}

function Enviar([byte[]]$bytes, [string]$rel) {
    $req = Peticion $rel ([System.Net.WebRequestMethods+Ftp]::UploadFile)
    $req.ContentLength = $bytes.Length
    $s = $req.GetRequestStream()
    $s.Write($bytes, 0, $bytes.Length)
    $s.Close()
    $req.GetResponse().Close()
}

function Subir([string]$local, [string]$rel) {
    $dir = ($rel -replace '/?[^/]+$', '')
    Asegurar-Carpeta $dir
    $bytes = [System.IO.File]::ReadAllBytes($local)
    try {
        Enviar $bytes $rel
    } catch {
        if ((Codigo-Ftp $_.Exception) -ne 550) { throw }
        # La carpeta no estaba: se crea nivel por nivel, mostrando qué responde el servidor, y se reintenta una vez.
        Write-Host "   la carpeta $dir no existe en el servidor; creándola..." -ForegroundColor Yellow
        foreach ($k in @($CarpetasListas.Keys)) { if ($dir -eq $k -or $dir.StartsWith("$k/")) { $CarpetasListas.Remove($k) } }
        Asegurar-Carpeta $dir -Detallado
        try {
            Enviar $bytes $rel
        } catch {
            throw "No se pudo subir ${rel}: $(Mensaje-Ftp $_.Exception)"
        }
    }
}

function Borrar-Remoto([string]$rel) {
    try {
        (Peticion $rel ([System.Net.WebRequestMethods+Ftp]::DeleteFile)).GetResponse().Close()
    } catch {
        if ((Codigo-Ftp $_.Exception) -ne 550) { throw }
    }
}

function Confirmar([string]$pregunta) {
    $r = Read-Host "$pregunta Escribe SI para continuar"
    return $r -ceq 'SI'
}

Write-Host "Servidor: $($cfg['FTP_HOST']):$Puerto  usuario: $($cfg['FTP_USER'])  carpeta: /$DirRemoto  TLS: $Tls"

# ---------------------------------------------------------------------
# -SubirConfig: configuración de producción, solo si no existe
# ---------------------------------------------------------------------
if ($SubirConfig) {
    if (-not (Test-Path $ConfigProd)) { throw "No encuentro $ConfigProd." }
    Write-Host "Revisando si el servidor ya tiene $ConfigRemota ..."
    if (Existe-Remoto $ConfigRemota) {
        Write-Host "El servidor YA tiene $ConfigRemota. No se toca. Si necesitas cambiarla, edítala en el Administrador de archivos de Hostinger."
        exit 0
    }
    if ($Simular) { Write-Host "(Simulación) Se subiría $ConfigProd como $ConfigRemota."; exit 0 }
    if (-not (Confirmar "Se subirá $ConfigProd como $ConfigRemota.")) { Write-Host 'Cancelado.'; exit 1 }
    Subir $ConfigProd $ConfigRemota
    Write-Host "Configuración subida."
    exit 0
}

# ---------------------------------------------------------------------
# Archivos del sitio y cambios desde la última subida
# ---------------------------------------------------------------------
$anterior = @{}
if ((Test-Path $Estado) -and -not $Todo) {
    $json = Get-Content -LiteralPath $Estado -Raw -Encoding UTF8 | ConvertFrom-Json
    foreach ($p in $json.PSObject.Properties) { $anterior[$p.Name] = [string]$p.Value }
}
$primeraVez = -not (Test-Path $Estado)

$actual = @{}
$prefijo = $Raiz.TrimEnd('\').Length + 1
foreach ($f in Get-ChildItem -LiteralPath $Raiz -Recurse -File -Force) {
    $rel = $f.FullName.Substring($prefijo).Replace('\', '/')
    if (Excluido $rel) { continue }
    $actual[$rel] = (Get-FileHash -LiteralPath $f.FullName -Algorithm SHA256).Hash
}

$porSubir = @($actual.Keys | Where-Object { $Todo -or -not $anterior.ContainsKey($_) -or $anterior[$_] -ne $actual[$_] } | Sort-Object)
$sobran   = @($anterior.Keys | Where-Object { -not $actual.ContainsKey($_) } | Sort-Object)

# Defensa extra: la configuración del servidor nunca entra por aquí.
$porSubir = @($porSubir | Where-Object { $_ -ne $ConfigRemota })

# Aviso si hay trabajo sin commit (se sube lo que está en disco).
try {
    $pendientes = @(& git -C $Raiz status --porcelain 2>$null | Where-Object { $_ -and -not (Excluido ($_.Substring(3).Trim('"'))) })
    if ($pendientes.Count -gt 0) {
        Write-Host ''
        Write-Host "AVISO: hay $($pendientes.Count) archivo(s) del sitio con cambios sin commit. Se sube lo que está en disco:" -ForegroundColor Yellow
        $pendientes | Select-Object -First 15 | ForEach-Object { Write-Host "   $_" -ForegroundColor Yellow }
    }
} catch { }

Write-Host ''
if ($primeraVez -and -not $Todo) { Write-Host 'No hay registro de subidas anteriores: se considera todo como nuevo.' }
Write-Host "Archivos por subir: $($porSubir.Count)"
$porSubir | ForEach-Object { Write-Host "   + $_" }
if ($sobran.Count -gt 0) {
    $accion = if ($Borrar) { 'se borrarán del servidor' } else { 'siguen en el servidor (usa -Borrar para quitarlos)' }
    Write-Host "Ya no existen en local ($accion): $($sobran.Count)"
    $sobran | ForEach-Object { Write-Host "   - $_" }
}

if ($porSubir.Count -eq 0 -and (-not $Borrar -or $sobran.Count -eq 0)) { Write-Host 'Nada que subir.'; exit 0 }
if ($Simular) { Write-Host ''; Write-Host '(Simulación: no se subió nada.)'; exit 0 }
Write-Host ''
if (-not (Confirmar 'Se subirán estos archivos al servidor.')) { Write-Host 'Cancelado.'; exit 1 }

# El registro se guarda aunque falle a la mitad: la próxima vez sigue donde se quedó.
$registro = @{}
foreach ($k in $anterior.Keys) { $registro[$k] = $anterior[$k] }
function Guardar-Estado {
    $o = [ordered]@{}
    foreach ($k in ($registro.Keys | Sort-Object)) { $o[$k] = $registro[$k] }
    [System.IO.File]::WriteAllText($Estado, ($o | ConvertTo-Json), $Utf8SinBom)
}

$n = 0
try {
    foreach ($rel in $porSubir) {
        $n++
        Write-Host ("[{0}/{1}] {2}" -f $n, $porSubir.Count, $rel)
        Subir (Join-Path $Raiz $rel.Replace('/', '\')) $rel
        $registro[$rel] = $actual[$rel]
    }
    if ($Borrar) {
        foreach ($rel in $sobran) {
            Write-Host "Borrando $rel"
            Borrar-Remoto $rel
            $registro.Remove($rel)
        }
    }
} finally {
    Guardar-Estado
}
Write-Host ''
Write-Host "Listo: $n archivo(s) subidos."
