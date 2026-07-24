# Edu AI Architecture
# Edu AI Architecture

This project now includes a split Edu AI foundation:

## Main system

- PHP
- MySQL
- XAMPP

This remains the source of truth for:

- students
- exams
- attendance
- finance
- discipline
- timetable
- permissions

## Separate AI engine

Location:

- `edu-ai/`

Core pieces:

- FastAPI service
- ChromaDB vector store
- document ingestion
- Gemini / OpenAI / Ollama routing
- role-aware query execution

## Secure flow

1. User asks from PHP portal.
2. PHP resolves role and safe scoped data.
3. PHP sends only filtered context to the AI service.
4. AI service retrieves relevant school knowledge chunks.
5. AI service combines retrieval + scoped live data + provider reasoning.
6. Response returns to PHP widget.

## Why this matters

- heavy AI work stays outside page requests
- XAMPP remains faster and more stable
- school knowledge becomes searchable through vector retrieval
- permissions stay enforced in PHP

## Current MVP endpoints

- `GET /health`
- `POST /query`
- `POST /ingest`

## Current admin settings added

- `ai_service_enabled`
- `ai_service_url`
- `ai_service_timeout_seconds`
- `ai_rag_enabled`

## Recommended next phase

1. Build an admin upload page for knowledge ingestion.
2. Add OCR pipeline for images and scanned PDFs.
3. Add result-risk analytics and teacher comment generation pipelines.
4. Add background queue workers for ingestion jobs.
5. Add citations in the widget response UI.
