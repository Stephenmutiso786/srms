@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Backup-SRMS.ps1" -InstallRoot "%~dp0.."
endlocal

