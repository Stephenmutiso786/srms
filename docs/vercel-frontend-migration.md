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

## Recommended migration order

### Phase 1
- Authentication handoff
- Student dashboard
- Parent dashboard
- Published report cards

### Phase 2
- Teacher dashboard
- Class analytics
- Exam results views

### Phase 3
- Marks entry
- E-learning
- Communication

## Important note

Before the new Vercel frontend can fully replace the current PHP pages, we still need real backend APIs for:

- login/session validation
- current user profile
- student dashboard data
- parent child-scoped dashboard data
- teacher class/subject analytics
- published report cards

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
