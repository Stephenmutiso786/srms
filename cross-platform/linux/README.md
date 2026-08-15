# Linux Portable SRMS

This is the simplest way to carry SRMS between Linux machines.

## Folder layout

```text
srms-portable/
  app/
  runtime/
  data/
  backups/
  start-srms.sh
  backup-srms.sh
```

## How it works

- `start-srms.sh` starts PHP's built-in server
- `backup-srms.sh` creates a backup archive
- `app/` contains the SRMS code
- `runtime/` contains portable PHP and MySQL binaries if you want fully local mode

## Best for

- Kali Linux laptops
- Offline school demonstrations
- Quick folder transfer without installation

