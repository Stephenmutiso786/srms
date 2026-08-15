@echo off
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0runtime\bootstrap.ps1" -InstallRoot "%~dp0"
start "" "http://127.0.0.1:8000"
endlocal
