-- Optional indexes for ticket lists, Performance aggregates, and mailbox lookups.
-- Safe to re-run: each statement skips when the index already exists.

-- Status filters (active queues, performance aggregates, retention)
SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'tickets' AND index_name = 'idx_tickets_status'
);
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_tickets_status ON tickets (status)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Category rollups on resolved tickets
SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'tickets' AND index_name = 'idx_tickets_status_category'
);
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_tickets_status_category ON tickets (status, category)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- User / technician ticket lists
SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'tickets' AND index_name = 'idx_tickets_user_id'
);
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_tickets_user_id ON tickets (user_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'tickets' AND index_name = 'idx_tickets_assigned_to'
);
SET @sql := IF(@idx = 0, 'CREATE INDEX idx_tickets_assigned_to ON tickets (assigned_to)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Soft-archive / retention scans (only if column exists)
SET @has_archived := (
  SELECT COUNT(1) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'tickets' AND column_name = 'archived_at'
);
SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'tickets' AND index_name = 'idx_tickets_archived_at'
);
SET @sql := IF(@has_archived > 0 AND @idx = 0, 'CREATE INDEX idx_tickets_archived_at ON tickets (archived_at)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Mailbox messages by ticket
SET @has_msg := (
  SELECT COUNT(1) FROM information_schema.tables
  WHERE table_schema = DATABASE() AND table_name = 'messages'
);
SET @idx := (
  SELECT COUNT(1) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'messages' AND index_name = 'idx_messages_ticket_id'
);
SET @sql := IF(@has_msg > 0 AND @idx = 0, 'CREATE INDEX idx_messages_ticket_id ON messages (ticket_id)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
