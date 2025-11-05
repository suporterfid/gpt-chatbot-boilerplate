-- Migration: Add response_format support to agents table
-- Description: Adds response_format_json column to support guardrails and structured outputs

ALTER TABLE agents ADD COLUMN response_format_json TEXT NULL;
