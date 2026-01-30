# Tributary Database Schema (PostgreSQL)

## Overview

Tributary uses PostgreSQL as the primary datastore with the following design principles:
- **JSONB** for flexible, schema-less event data
- **Row-level security (RLS)** for multi-tenant isolation
- **Partitioning** for time-series event data
- **Full-text search** for event discovery
- **TimescaleDB** extension for time-series optimization (optional)

---

## Schema Diagram

```
┌─────────────────┐       ┌─────────────────┐       ┌─────────────────┐
│     users       │       │     streams     │       │     sources     │
├─────────────────┤       ├─────────────────┤       ├─────────────────┤
│ id (PK)         │──┐    │ id (PK)         │──┐    │ id (PK)         │
│ email           │  │    │ user_id (FK)────│──┼───>│ user_id (FK)    │
│ name            │  │    │ type            │  │    │ stream_id (FK)──│───┐
│ preferences     │  │    │ name            │  │    │ provider        │   │
│ plan            │  │    │ settings        │  │    │ credentials     │   │
└─────────────────┘  │    │ enabled         │  │    │ sync_config     │   │
                     │    └─────────────────┘  │    │ status          │   │
                     │                         │    └─────────────────┘   │
                     │                         │                          │
                     │    ┌─────────────────┐  │    ┌─────────────────┐   │
                     │    │     events      │  │    │   sync_logs     │   │
                     │    ├─────────────────┤  │    ├─────────────────┤   │
                     │    │ id (PK)         │  │    │ id (PK)         │   │
                     └───>│ user_id (FK)    │  │    │ source_id (FK)──│───┘
                          │ stream_id (FK)──│──┘    │ status          │
                          │ source_id (FK)  │       │ events_created  │
                          │ type            │       │ started_at      │
                          │ occurred_at     │       └─────────────────┘
                          │ title           │
                          │ data (JSONB)    │       ┌─────────────────┐
                          │ tags            │       │    entities     │
                          └─────────────────┘       ├─────────────────┤
                                   │                │ id (PK)         │
                                   │                │ user_id (FK)    │
                          ┌────────┴────────┐       │ type            │
                          │                 │       │ name            │
                   ┌──────┴──────┐   ┌──────┴──────┐│ metadata        │
                   │ event_links │   │event_entities│└─────────────────┘
                   └─────────────┘   └─────────────┘        │
                                             │              │
                                             └──────────────┘
```

---

## Core Tables

### users

```sql
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    avatar_url TEXT,
    timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',
    locale VARCHAR(10) NOT NULL DEFAULT 'en-US',
    
    -- Preferences (JSONB for flexibility)
    preferences JSONB NOT NULL DEFAULT '{
        "theme": "system",
        "weekStartsOn": 0,
        "timeFormat": "12h",
        "defaultStreamView": "timeline",
        "emailDigest": "daily",
        "pushEnabled": true,
        "quietHours": null,
        "shareInsights": false,
        "aiEnabled": true
    }'::jsonb,
    
    -- Plan
    plan VARCHAR(20) NOT NULL DEFAULT 'free' CHECK (plan IN ('free', 'pro', 'team')),
    plan_expires_at TIMESTAMPTZ,
    
    -- Auth (for internal auth, not OAuth)
    password_hash VARCHAR(255),
    email_verified BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- System
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_active_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- Indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_plan ON users(plan) WHERE deleted_at IS NULL;
CREATE INDEX idx_users_last_active ON users(last_active_at);

-- Auto-update updated_at
CREATE TRIGGER update_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();
```

### streams

```sql
CREATE TYPE stream_type AS ENUM (
    'health', 'calendar', 'tasks', 'relationships', 
    'finance', 'work', 'ai'
);

CREATE TABLE streams (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    type stream_type NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(10) NOT NULL DEFAULT '📊',
    color VARCHAR(7) NOT NULL DEFAULT '#6366f1',  -- Hex color
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    
    -- Settings
    settings JSONB NOT NULL DEFAULT '{
        "visibility": "private",
        "retentionDays": null,
        "syncFrequency": 60,
        "notifications": {
            "onNewEvent": false,
            "dailyDigest": true
        }
    }'::jsonb,
    
    -- Cached stats (updated periodically)
    cached_event_count INTEGER NOT NULL DEFAULT 0,
    cached_last_event_at TIMESTAMPTZ,
    
    -- System
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,
    
    -- Constraints
    CONSTRAINT unique_stream_per_type_per_user 
        UNIQUE (user_id, type) WHERE deleted_at IS NULL
);

-- Indexes
CREATE INDEX idx_streams_user_id ON streams(user_id);
CREATE INDEX idx_streams_type ON streams(type);
CREATE INDEX idx_streams_enabled ON streams(user_id, enabled) WHERE deleted_at IS NULL;

-- RLS
ALTER TABLE streams ENABLE ROW LEVEL SECURITY;

CREATE POLICY streams_user_isolation ON streams
    USING (user_id = current_setting('app.current_user_id')::uuid);
```

### sources

```sql
CREATE TYPE source_provider AS ENUM (
    -- Health
    'eight_sleep', 'apple_health', 'whoop', 'oura', 'fitbit', 'garmin',
    -- Calendar
    'google_calendar', 'outlook_calendar', 'apple_calendar',
    -- Tasks
    'todoist', 'notion', 'asana', 'linear',
    -- Relationships
    'google_contacts', 'apple_contacts',
    -- Finance
    'plaid',
    -- Work
    'github', 'gitlab', 'jira',
    -- AI
    'openai', 'anthropic',
    -- Generic
    'webhook', 'csv_import', 'manual'
);

CREATE TYPE source_status AS ENUM (
    'connected', 'disconnected', 'expired', 'error', 'syncing'
);

CREATE TABLE sources (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    stream_id UUID NOT NULL REFERENCES streams(id) ON DELETE CASCADE,
    
    provider source_provider NOT NULL,
    name VARCHAR(100) NOT NULL,
    icon VARCHAR(10),
    
    -- Connection status
    status source_status NOT NULL DEFAULT 'disconnected',
    status_message TEXT,  -- Error message if status is 'error'
    
    -- Encrypted credentials (stored in separate secure table or vault)
    credential_id UUID,  -- Reference to credentials vault
    
    -- Sync configuration
    sync_config JSONB NOT NULL DEFAULT '{
        "mode": "polling",
        "intervalMinutes": 60,
        "webhookUrl": null,
        "filters": [],
        "transforms": []
    }'::jsonb,
    
    -- Sync state
    last_sync_at TIMESTAMPTZ,
    last_sync_status VARCHAR(20) CHECK (last_sync_status IN ('success', 'partial', 'failed')),
    last_sync_error TEXT,
    next_sync_at TIMESTAMPTZ,
    
    -- Sync cursor (for incremental sync)
    sync_state JSONB NOT NULL DEFAULT '{}'::jsonb,
    
    -- Stats
    total_events_synced INTEGER NOT NULL DEFAULT 0,
    
    -- System
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- Indexes
CREATE INDEX idx_sources_user_id ON sources(user_id);
CREATE INDEX idx_sources_stream_id ON sources(stream_id);
CREATE INDEX idx_sources_provider ON sources(provider);
CREATE INDEX idx_sources_status ON sources(status) WHERE deleted_at IS NULL;
CREATE INDEX idx_sources_next_sync ON sources(next_sync_at) 
    WHERE status = 'connected' AND deleted_at IS NULL;

-- RLS
ALTER TABLE sources ENABLE ROW LEVEL SECURITY;

CREATE POLICY sources_user_isolation ON sources
    USING (user_id = current_setting('app.current_user_id')::uuid);
```

### events

```sql
-- Main events table (partitioned by month)
CREATE TABLE events (
    id UUID NOT NULL DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    stream_id UUID NOT NULL REFERENCES streams(id) ON DELETE CASCADE,
    source_id UUID REFERENCES sources(id) ON DELETE SET NULL,
    
    -- External reference
    external_id VARCHAR(255),  -- ID in source system
    
    -- Classification
    type VARCHAR(50) NOT NULL,  -- sleep, workout, meeting, task, etc.
    subtype VARCHAR(50),        -- More specific classification
    
    -- Temporal
    occurred_at TIMESTAMPTZ NOT NULL,
    duration INTEGER,           -- Duration in seconds
    timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',
    is_all_day BOOLEAN NOT NULL DEFAULT FALSE,
    
    -- Content
    title VARCHAR(500) NOT NULL,
    description TEXT,
    data JSONB NOT NULL DEFAULT '{}'::jsonb,  -- Type-specific structured data
    
    -- Metadata
    tags TEXT[] NOT NULL DEFAULT '{}',
    sentiment NUMERIC(3,2) CHECK (sentiment >= -1 AND sentiment <= 1),
    importance INTEGER NOT NULL DEFAULT 50 CHECK (importance >= 0 AND importance <= 100),
    
    -- Full-text search
    search_vector TSVECTOR,
    
    -- System
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    synced_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ,
    
    PRIMARY KEY (id, occurred_at)
) PARTITION BY RANGE (occurred_at);

-- Create partitions for recent months and future
CREATE TABLE events_y2025m01 PARTITION OF events
    FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');
CREATE TABLE events_y2025m02 PARTITION OF events
    FOR VALUES FROM ('2025-02-01') TO ('2025-03-01');
-- ... create partitions as needed

-- Default partition for out-of-range data
CREATE TABLE events_default PARTITION OF events DEFAULT;

-- Indexes (created on parent, automatically applied to partitions)
CREATE INDEX idx_events_user_stream ON events(user_id, stream_id, occurred_at DESC);
CREATE INDEX idx_events_user_type ON events(user_id, type, occurred_at DESC);
CREATE INDEX idx_events_source ON events(source_id, occurred_at DESC);
CREATE INDEX idx_events_external ON events(source_id, external_id) WHERE external_id IS NOT NULL;
CREATE INDEX idx_events_tags ON events USING GIN(tags);
CREATE INDEX idx_events_data ON events USING GIN(data jsonb_path_ops);
CREATE INDEX idx_events_search ON events USING GIN(search_vector);

-- Auto-update search vector
CREATE FUNCTION update_event_search_vector() RETURNS TRIGGER AS $$
BEGIN
    NEW.search_vector := 
        setweight(to_tsvector('english', COALESCE(NEW.title, '')), 'A') ||
        setweight(to_tsvector('english', COALESCE(NEW.description, '')), 'B') ||
        setweight(to_tsvector('english', COALESCE(array_to_string(NEW.tags, ' '), '')), 'C');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER event_search_vector_update
    BEFORE INSERT OR UPDATE OF title, description, tags ON events
    FOR EACH ROW
    EXECUTE FUNCTION update_event_search_vector();

-- RLS
ALTER TABLE events ENABLE ROW LEVEL SECURITY;

CREATE POLICY events_user_isolation ON events
    USING (user_id = current_setting('app.current_user_id')::uuid);
```

### entities

```sql
CREATE TYPE entity_type AS ENUM ('person', 'place', 'project', 'company');

CREATE TABLE entities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    type entity_type NOT NULL,
    name VARCHAR(255) NOT NULL,
    aliases TEXT[] NOT NULL DEFAULT '{}',
    
    -- Type-specific fields
    email VARCHAR(255),
    phone VARCHAR(50),
    birthday VARCHAR(5),  -- MM-DD format
    address TEXT,
    coordinates POINT,    -- For places
    
    -- Flexible metadata
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    
    -- Cached stats
    event_count INTEGER NOT NULL DEFAULT 0,
    last_event_at TIMESTAMPTZ,
    
    -- Full-text search
    search_vector TSVECTOR,
    
    -- System
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    deleted_at TIMESTAMPTZ
);

-- Indexes
CREATE INDEX idx_entities_user_type ON entities(user_id, type);
CREATE INDEX idx_entities_name ON entities(user_id, name);
CREATE INDEX idx_entities_email ON entities(email) WHERE email IS NOT NULL;
CREATE INDEX idx_entities_search ON entities USING GIN(search_vector);
CREATE INDEX idx_entities_aliases ON entities USING GIN(aliases);

-- RLS
ALTER TABLE entities ENABLE ROW LEVEL SECURITY;

CREATE POLICY entities_user_isolation ON entities
    USING (user_id = current_setting('app.current_user_id')::uuid);
```

### event_entities (Junction Table)

```sql
CREATE TABLE event_entities (
    event_id UUID NOT NULL,
    event_occurred_at TIMESTAMPTZ NOT NULL,  -- For partition routing
    entity_id UUID NOT NULL REFERENCES entities(id) ON DELETE CASCADE,
    
    -- How this entity relates to the event
    role VARCHAR(50),  -- 'attendee', 'organizer', 'subject', etc.
    
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    PRIMARY KEY (event_id, entity_id),
    FOREIGN KEY (event_id, event_occurred_at) REFERENCES events(id, occurred_at) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX idx_event_entities_entity ON event_entities(entity_id);
```

### event_links

```sql
CREATE TYPE link_type AS ENUM (
    'caused_by', 'blocked_by', 'related_to', 'followed_by', 'part_of'
);

CREATE TABLE event_links (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    
    from_event_id UUID NOT NULL,
    from_event_occurred_at TIMESTAMPTZ NOT NULL,
    to_event_id UUID NOT NULL,
    to_event_occurred_at TIMESTAMPTZ NOT NULL,
    
    link_type link_type NOT NULL,
    strength NUMERIC(3,2) NOT NULL DEFAULT 1.0 CHECK (strength >= 0 AND strength <= 1),
    
    created_by VARCHAR(20) NOT NULL DEFAULT 'user' CHECK (created_by IN ('user', 'system')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    FOREIGN KEY (from_event_id, from_event_occurred_at) REFERENCES events(id, occurred_at) ON DELETE CASCADE,
    FOREIGN KEY (to_event_id, to_event_occurred_at) REFERENCES events(id, occurred_at) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX idx_event_links_from ON event_links(from_event_id);
CREATE INDEX idx_event_links_to ON event_links(to_event_id);
CREATE INDEX idx_event_links_type ON event_links(link_type);
```

---

## Supporting Tables

### sync_logs

```sql
CREATE TABLE sync_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_id UUID NOT NULL REFERENCES sources(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    status VARCHAR(20) NOT NULL CHECK (status IN ('queued', 'running', 'success', 'partial', 'failed')),
    
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ,
    
    -- Results
    events_created INTEGER NOT NULL DEFAULT 0,
    events_updated INTEGER NOT NULL DEFAULT 0,
    events_deleted INTEGER NOT NULL DEFAULT 0,
    
    -- Errors
    errors JSONB NOT NULL DEFAULT '[]'::jsonb,
    
    -- Sync range
    sync_from TIMESTAMPTZ,
    sync_to TIMESTAMPTZ,
    
    -- Performance
    duration_ms INTEGER,
    api_calls INTEGER NOT NULL DEFAULT 0
);

-- Indexes
CREATE INDEX idx_sync_logs_source ON sync_logs(source_id, started_at DESC);
CREATE INDEX idx_sync_logs_status ON sync_logs(status) WHERE status IN ('queued', 'running');

-- Auto-cleanup old logs (keep 30 days)
CREATE INDEX idx_sync_logs_cleanup ON sync_logs(started_at) WHERE started_at < NOW() - INTERVAL '30 days';
```

### daily_summaries

```sql
CREATE TABLE daily_summaries (
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    date DATE NOT NULL,
    
    -- Overall score
    score INTEGER CHECK (score >= 0 AND score <= 100),
    
    -- Per-stream summaries
    health JSONB NOT NULL DEFAULT '{}'::jsonb,
    calendar JSONB NOT NULL DEFAULT '{}'::jsonb,
    tasks JSONB NOT NULL DEFAULT '{}'::jsonb,
    relationships JSONB NOT NULL DEFAULT '{}'::jsonb,
    finance JSONB NOT NULL DEFAULT '{}'::jsonb,
    work JSONB NOT NULL DEFAULT '{}'::jsonb,
    
    -- AI-generated
    highlights TEXT[] NOT NULL DEFAULT '{}',
    
    -- System
    generated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    PRIMARY KEY (user_id, date)
);

-- Indexes
CREATE INDEX idx_daily_summaries_date ON daily_summaries(date DESC);
```

### insights

```sql
CREATE TYPE insight_type AS ENUM (
    'correlation', 'anomaly', 'trend', 'reminder', 'suggestion'
);

CREATE TABLE insights (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    type insight_type NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    
    -- Evidence
    related_streams stream_type[] NOT NULL DEFAULT '{}',
    related_event_ids UUID[] NOT NULL DEFAULT '{}',
    confidence NUMERIC(3,2) NOT NULL CHECK (confidence >= 0 AND confidence <= 1),
    
    -- Action
    actionable BOOLEAN NOT NULL DEFAULT FALSE,
    suggested_action TEXT,
    action_taken BOOLEAN NOT NULL DEFAULT FALSE,
    action_taken_at TIMESTAMPTZ,
    
    -- Display
    priority INTEGER NOT NULL DEFAULT 50 CHECK (priority >= 0 AND priority <= 100),
    expires_at TIMESTAMPTZ,
    dismissed_at TIMESTAMPTZ,
    
    -- System
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Indexes
CREATE INDEX idx_insights_user ON insights(user_id, created_at DESC);
CREATE INDEX idx_insights_active ON insights(user_id, priority DESC) 
    WHERE dismissed_at IS NULL AND (expires_at IS NULL OR expires_at > NOW());
CREATE INDEX idx_insights_type ON insights(type);

-- RLS
ALTER TABLE insights ENABLE ROW LEVEL SECURITY;

CREATE POLICY insights_user_isolation ON insights
    USING (user_id = current_setting('app.current_user_id')::uuid);
```

### credentials_vault

```sql
-- Separate table with enhanced security for OAuth credentials
CREATE TABLE credentials_vault (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_id UUID NOT NULL UNIQUE REFERENCES sources(id) ON DELETE CASCADE,
    
    -- Encrypted credentials (AES-256-GCM)
    encrypted_data BYTEA NOT NULL,
    encryption_key_id VARCHAR(50) NOT NULL,  -- Key rotation support
    
    -- Token metadata (not sensitive)
    token_type VARCHAR(50),
    scopes TEXT[],
    expires_at TIMESTAMPTZ,
    
    -- System
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Minimal indexes (security)
CREATE INDEX idx_credentials_expires ON credentials_vault(expires_at) 
    WHERE expires_at IS NOT NULL;

-- This table should have additional security:
-- 1. Separate database role with minimal permissions
-- 2. Audit logging on all access
-- 3. Encryption at rest
```

### webhook_deliveries

```sql
CREATE TABLE webhook_deliveries (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    source_id UUID NOT NULL REFERENCES sources(id) ON DELETE CASCADE,
    
    -- Request
    webhook_id VARCHAR(255) NOT NULL,  -- Provider's event ID
    event_type VARCHAR(100) NOT NULL,
    payload JSONB NOT NULL,
    headers JSONB NOT NULL DEFAULT '{}'::jsonb,
    
    -- Processing
    status VARCHAR(20) NOT NULL CHECK (status IN ('received', 'processed', 'failed', 'duplicate')),
    processed_at TIMESTAMPTZ,
    error_message TEXT,
    
    -- Results
    events_created INTEGER NOT NULL DEFAULT 0,
    
    -- System
    received_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    
    -- Idempotency
    CONSTRAINT unique_webhook_delivery UNIQUE (source_id, webhook_id)
);

-- Indexes
CREATE INDEX idx_webhook_deliveries_source ON webhook_deliveries(source_id, received_at DESC);
CREATE INDEX idx_webhook_deliveries_status ON webhook_deliveries(status) 
    WHERE status IN ('received', 'failed');

-- Auto-cleanup (keep 7 days)
CREATE INDEX idx_webhook_cleanup ON webhook_deliveries(received_at) 
    WHERE received_at < NOW() - INTERVAL '7 days';
```

---

## Functions & Triggers

### update_updated_at_column

```sql
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply to all tables with updated_at
CREATE TRIGGER update_streams_updated_at BEFORE UPDATE ON streams
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_sources_updated_at BEFORE UPDATE ON sources
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_events_updated_at BEFORE UPDATE ON events
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_entities_updated_at BEFORE UPDATE ON entities
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### update_stream_stats

```sql
CREATE OR REPLACE FUNCTION update_stream_stats()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE streams SET 
            cached_event_count = cached_event_count + 1,
            cached_last_event_at = GREATEST(cached_last_event_at, NEW.occurred_at)
        WHERE id = NEW.stream_id;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE streams SET 
            cached_event_count = cached_event_count - 1
        WHERE id = OLD.stream_id;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_stream_stats
    AFTER INSERT OR DELETE ON events
    FOR EACH ROW
    EXECUTE FUNCTION update_stream_stats();
```

### update_entity_stats

```sql
CREATE OR REPLACE FUNCTION update_entity_stats()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE entities SET
            event_count = event_count + 1,
            last_event_at = GREATEST(last_event_at, (
                SELECT occurred_at FROM events WHERE id = NEW.event_id
            ))
        WHERE id = NEW.entity_id;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE entities SET
            event_count = event_count - 1
        WHERE id = OLD.entity_id;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trigger_update_entity_stats
    AFTER INSERT OR DELETE ON event_entities
    FOR EACH ROW
    EXECUTE FUNCTION update_entity_stats();
```

### create_monthly_partition

```sql
CREATE OR REPLACE FUNCTION create_monthly_partition(
    table_name TEXT,
    partition_date DATE
)
RETURNS VOID AS $$
DECLARE
    partition_name TEXT;
    start_date DATE;
    end_date DATE;
BEGIN
    start_date := DATE_TRUNC('month', partition_date);
    end_date := start_date + INTERVAL '1 month';
    partition_name := table_name || '_y' || 
        TO_CHAR(start_date, 'YYYY') || 'm' || 
        TO_CHAR(start_date, 'MM');
    
    EXECUTE format(
        'CREATE TABLE IF NOT EXISTS %I PARTITION OF %I 
         FOR VALUES FROM (%L) TO (%L)',
        partition_name, table_name, start_date, end_date
    );
END;
$$ LANGUAGE plpgsql;

-- Create partitions for next 12 months
DO $$
DECLARE
    i INTEGER;
BEGIN
    FOR i IN 0..12 LOOP
        PERFORM create_monthly_partition(
            'events', 
            CURRENT_DATE + (i || ' months')::INTERVAL
        );
    END LOOP;
END;
$$;
```

---

## Performance Considerations

### Partitioning Strategy

Events table is partitioned by `occurred_at` (monthly):
- Improves query performance for time-range queries
- Enables efficient data retention (drop old partitions)
- Reduces index size per partition

**Maintenance job (monthly):**
```sql
-- Create next month's partition
SELECT create_monthly_partition('events', CURRENT_DATE + INTERVAL '2 months');

-- Archive old partitions (optional)
-- ALTER TABLE events DETACH PARTITION events_y2024m01;
```

### Recommended Indexes Summary

| Table | Index | Purpose |
|-------|-------|---------|
| events | (user_id, stream_id, occurred_at DESC) | Timeline queries |
| events | (user_id, type, occurred_at DESC) | Type-filtered queries |
| events | GIN(tags) | Tag filtering |
| events | GIN(data) | JSONB queries |
| events | GIN(search_vector) | Full-text search |
| sources | (next_sync_at) | Polling scheduler |
| insights | (user_id, priority DESC) | Active insights |
| entities | (user_id, type) | Entity listing |

### Query Patterns

**Timeline query (optimized):**
```sql
SELECT * FROM events
WHERE user_id = $1
  AND occurred_at >= $2
  AND occurred_at < $3
ORDER BY occurred_at DESC
LIMIT 50;
```

**Search events:**
```sql
SELECT * FROM events
WHERE user_id = $1
  AND search_vector @@ plainto_tsquery('english', $2)
ORDER BY ts_rank(search_vector, plainto_tsquery('english', $2)) DESC
LIMIT 20;
```

**Filter by JSON data:**
```sql
SELECT * FROM events
WHERE user_id = $1
  AND type = 'sleep'
  AND (data->>'sleepScore')::int > 80
ORDER BY occurred_at DESC;
```

---

## Security

### Row-Level Security

All user-facing tables have RLS enabled:

```sql
-- Set user context before queries
SET app.current_user_id = 'user-uuid-here';

-- Queries automatically filtered
SELECT * FROM events;  -- Only returns user's events
```

### Credential Isolation

- Credentials stored in separate `credentials_vault` table
- Encrypted with AES-256-GCM
- Accessed via dedicated service with audit logging
- Key rotation supported via `encryption_key_id`

### Audit Logging

```sql
CREATE TABLE audit_log (
    id BIGSERIAL PRIMARY KEY,
    user_id UUID,
    action VARCHAR(50) NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    record_id UUID,
    old_data JSONB,
    new_data JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Create partitions by month for audit log
-- Retain for 90 days per compliance
```

---

## Migrations

Use a migration tool like **golang-migrate**, **Flyway**, or **Prisma**.

Migration naming convention:
```
V001__create_users_table.sql
V002__create_streams_table.sql
V003__create_sources_table.sql
V004__create_events_table.sql
V005__create_entities_table.sql
V006__add_full_text_search.sql
V007__create_insights_table.sql
```

---

## Scaling Considerations

### Read Replicas
- Use read replicas for dashboard/reporting queries
- Write to primary, read from replicas with acceptable lag

### Connection Pooling
- Use PgBouncer in transaction mode
- Pool size: 20-50 connections per application instance

### Archival Strategy
- Events older than retention period → archive to cold storage
- Use `pg_partman` for automated partition management
- Consider TimescaleDB for automatic compression

### Estimated Sizing

| Table | Rows/User/Year | Avg Row Size | Storage/User/Year |
|-------|----------------|--------------|-------------------|
| events | ~10,000 | 2 KB | 20 MB |
| entities | ~500 | 1 KB | 0.5 MB |
| daily_summaries | 365 | 5 KB | 1.8 MB |
| insights | ~1,000 | 1 KB | 1 MB |
| **Total** | | | **~25 MB/user/year** |
