# SRMS Windows Packaging

This folder contains the Windows packaging flow for SRMS.

## Goal

Create a real `Setup.exe` that:

- installs SRMS files
- starts a local PHP server
- opens the browser to the app
- lets a super admin manage schools
- imports the MySQL schema on first launch

## Important

This package still needs a Windows build machine with:

- Inno Setup
- a portable PHP runtime
- a database runtime if you want the installer to auto-start MySQL/MariaDB
- `mysql.exe` on the bundled runtime path so first-launch schema import can run

## Next step

Compile `windows/SRMS.iss` after staging your runtime bundles.
