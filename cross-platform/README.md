# SRMS Cross-Platform Deployment

This folder documents the supported ways to run SRMS on Kali Linux and Windows.

## Supported paths

- Portable Linux folder bundle
- Windows `Setup.exe`
- Docker container deployment
- VirtualBox VM deployment

## Common idea

Keep one SRMS codebase and vary only the runtime wrapper:

- PHP web app files are shared
- Database schema is shared
- Backup format is shared
- Launch scripts are OS-specific

## Recommended choice

Use the Docker or portable Linux path for development on Kali, and use the Windows installer for staff laptops.

