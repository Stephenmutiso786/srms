-- Migration: 999 - General Ledger scaffold (minimal)
-- Creates basic chart of accounts and GL entries table
CREATE TABLE IF NOT EXISTS tbl_chart_of_accounts (
  id SERIAL PRIMARY KEY,
  code VARCHAR(32) NOT NULL UNIQUE,
  name VARCHAR(255) NOT NULL,
  type VARCHAR(32) NOT NULL,
  parent_id INTEGER DEFAULT NULL,
  created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT now()
);

CREATE TABLE IF NOT EXISTS tbl_gl_entries (
  id SERIAL PRIMARY KEY,
  account_id INTEGER NOT NULL REFERENCES tbl_chart_of_accounts(id) ON DELETE RESTRICT,
  date DATE NOT NULL DEFAULT CURRENT_DATE,
  description TEXT,
  debit NUMERIC(14,2) DEFAULT 0,
  credit NUMERIC(14,2) DEFAULT 0,
  created_by INTEGER DEFAULT NULL,
  created_at TIMESTAMP WITHOUT TIME ZONE DEFAULT now()
);

-- Basic index to speed up reports
CREATE INDEX IF NOT EXISTS idx_gl_entries_account_date ON tbl_gl_entries(account_id, date);
