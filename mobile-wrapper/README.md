# SRMS Mobile Wrapper

This folder contains an Android wrapper for the single locally hosted SRMS app using Capacitor.

## Current server target

The mobile app opens the same SRMS application tree used by the web login.

The app is configured to open:

`http://10.80.175.66/srms/script/`

If your XAMPP machine gets a different LAN IP, update `server.url` in `capacitor.config.json` before rebuilding, or run:

```bash
bash set-server-url.sh http://NEW-LAN-IP/srms/script/
```

## Generate Android project

From this folder:

```bash
npm install
npx cap add android
npx cap sync
```

## Build APK

1. Open the Android project in Android Studio:

```bash
npx cap open android
```

2. In Android Studio use:

`Build -> Build Bundle(s) / APK(s) -> Build APK(s)`

3. Share the generated APK with other phones.

## Important

- Phones must be on the same Wi‑Fi or network as the XAMPP machine.
- This APK uses plain HTTP on the local network, so it does not depend on browser PWA install prompts or local HTTPS certificate trust.
- If you later move the backend online, change `server.url` to the public HTTPS URL and rebuild.
