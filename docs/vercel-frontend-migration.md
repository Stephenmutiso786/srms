# Vercel frontend migration

## What changes

The current application is a PHP-rendered monolith under `srms/script`. That means the UI, authentication, session handling, and data rendering are tightly coupled to the PHP backend.

Because of that, we **cannot directly deploy the existing frontend to Vercel** as-is.

The safe professional path is:

1. Keep the PHP backend on Render
2. Create a separate Next.js frontend for Vercel
3. Expose backend APIs for each portal
4. Migrate portal by portal

## Scaffold added

A Vercel-ready frontend shell now exists in:

- `frontend-vercel/`

It includes:

- Next.js app structure
- shared Elimu Hub theme
- starter student, teacher, and parent portal shells
- environment-based backend URL configuration

## Required environment variables on Vercel

- `NEXT_PUBLIC_APP_NAME=Elimu Hub`
- `NEXT_PUBLIC_BACKEND_URL=https://srms-n7g2.onrender.com`
- `NEXT_PUBLIC_API_BASE_URL=https://srms-n7g2.onrender.com/api`

## Required environment variable on Render

Add the Vercel frontend origin to the backend so cross-site cookies and API CORS work:

- `FRONTEND_ORIGINS=https://your-vercel-domain.vercel.app`

If you later add a custom frontend domain, append it as a comma-separated value:

- `FRONTEND_ORIGINS=https://your-vercel-domain.vercel.app,https://app.yourschooldomain.com`

## Recommended migration order

### Phase 1
- Authentication handoff ✅
- Student dashboard ✅
- Parent dashboard ✅
- Published report cards ✅

### Phase 2
- Teacher dashboard ✅
- Class analytics ✅
- Exam results views links ✅

### Phase 3
- Marks entry through backend portal links
- E-learning migration still pending
- Communication migration still pending

## Important note

Before the new Vercel frontend can fully replace the current PHP pages, we still need more backend APIs for:

- admin dashboard and settings
- marks entry workflows
- e-learning content management
- communication workflows
- timetable management

## Run locally

From `frontend-vercel/`:

```bash
npm install
npm run dev
```

## Deploy to Vercel

1. Import the repo into Vercel
2. Set project root to `frontend-vercel`
3. Add the environment variables above
4. Deploy
