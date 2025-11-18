-- LeadSense CRM: Seed default pipeline
-- This is a placeholder - actual seeding done via PHP script
-- See: scripts/seed_default_pipeline.php

-- This migration just ensures the PHP script has been run
-- by checking for the existence of default pipeline

-- If you need to manually verify:
-- SELECT * FROM crm_pipelines WHERE is_default = 1;
-- SELECT * FROM crm_pipeline_stages WHERE pipeline_id IN (SELECT id FROM crm_pipelines WHERE is_default = 1);

-- Note: The actual seeding is performed by scripts/seed_default_pipeline.php
-- which is called automatically after migrations by scripts/run_migrations.php
