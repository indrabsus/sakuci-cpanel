-- Migration 006: GitHub Integration (Webhook & Push)

ALTER TABLE projects
  ADD COLUMN webhook_secret VARCHAR(64) NULL AFTER git_branch,
  ADD COLUMN github_token VARCHAR(255) NULL AFTER webhook_secret;

ALTER TABLE job_queue
  MODIFY COLUMN action ENUM(\x27clone\x27, \x27pull\x27, \x27push\x27) NOT NULL,
  ADD COLUMN commit_message VARCHAR(255) NULL AFTER action;

UPDATE projects
   SET webhook_secret = MD5(CONCAT(id, RAND(), NOW()))
 WHERE webhook_secret IS NULL OR webhook_secret = \x27\x27;
