@echo off
rem Publica el sitio en Hostinger por FTP. Opciones: ver deploy\subir_ftp.ps1
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0subir_ftp.ps1" %*
echo.
pause
