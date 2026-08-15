# SRMS Docker Deployment

This is the most consistent cross-platform runtime.

## What it gives you

- Same runtime on Kali and Windows
- No manual PHP install on user machines
- Easy local testing
- Easy deployment to servers

## Build

```bash
docker build -t srms .
```

## Run

```bash
docker run --rm -p 8080:80 srms
```

Open `http://localhost:8080`.

