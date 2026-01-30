# Tributary Integration Architecture

## Overview

Tributary connects to external services through three primary mechanisms:
1. **OAuth 2.0** - For user-authorized data access
2. **Webhooks** - For real-time push notifications
3. **Polling** - For services without webhook support

The integration layer normalizes diverse data formats into Tributary's unified event schema.

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           EXTERNAL SERVICES                                  │
├────────────┬────────────┬────────────┬────────────┬────────────┬───────────┤
│ Eight Sleep│   Whoop    │  Google    │  Todoist   │   GitHub   │   Plaid   │
│            │            │  Calendar  │            │            │           │
└─────┬──────┴─────┬──────┴─────┬──────┴─────┬──────┴─────┬──────┴─────┬─────┘
      │ OAuth      │ OAuth      │ OAuth      │ OAuth      │ OAuth      │ OAuth
      │            │            │            │            │            │
      ▼            ▼            ▼            ▼            ▼            ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        CREDENTIALS VAULT                                     │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │  AES-256 Encrypted Storage                                           │    │
│  │  - OAuth tokens (access + refresh)                                   │    │
│  │  - API keys                                                          │    │
│  │  - Webhook secrets                                                   │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        INTEGRATION ENGINE                                    │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐    │
│  │   OAuth     │  │   Webhook   │  │   Polling   │  │   Transform     │    │
│  │   Manager   │  │   Receiver  │  │   Scheduler │  │   Pipeline      │    │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └────────┬────────┘    │
│         │                │                │                   │             │
│         ▼                ▼                ▼                   ▼             │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                     ADAPTER REGISTRY                                 │    │
│  │  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐       │    │
│  │  │Eight    │ │ Whoop   │ │ Google  │ │Todoist  │ │ GitHub  │  ...  │    │
│  │  │Sleep    │ │ Adapter │ │Calendar │ │ Adapter │ │ Adapter │       │    │
│  │  │Adapter  │ │         │ │ Adapter │ │         │ │         │       │    │
│  │  └─────────┘ └─────────┘ └─────────┘ └─────────┘ └─────────┘       │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└──────────────────────────────────┬──────────────────────────────────────────┘
                                   │
                                   ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                          EVENT STORE                                         │
│                    (Normalized Tributary Events)                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## OAuth 2.0 Flow

### Supported Grant Types

| Service | Grant Type | Scopes |
|---------|-----------|--------|
| Eight Sleep | Authorization Code | `user.read`, `sleep.read` |
| Whoop | Authorization Code + PKCE | `read:recovery`, `read:sleep`, `read:workout` |
| Google Calendar | Authorization Code | `calendar.readonly`, `calendar.events` |
| Todoist | Authorization Code | `data:read_write` |
| GitHub | Authorization Code | `repo`, `read:user` |
| Plaid | Link Token | `transactions`, `accounts` |

### OAuth Manager

```typescript
interface OAuthManager {
  // Initiate OAuth flow
  initiateAuth(provider: Provider, userId: string, streamId: string): Promise<{
    authUrl: string;
    state: string;
    codeChallenge?: string;  // PKCE
  }>;
  
  // Complete OAuth flow
  handleCallback(
    provider: Provider,
    code: string,
    state: string,
    codeVerifier?: string
  ): Promise<OAuthTokens>;
  
  // Token refresh
  refreshTokens(sourceId: string): Promise<OAuthTokens>;
  
  // Revoke access
  revokeAccess(sourceId: string): Promise<void>;
}

interface OAuthTokens {
  accessToken: string;
  refreshToken: string;
  expiresAt: DateTime;
  scope: string[];
}
```

### Token Lifecycle

```
┌─────────────┐      ┌─────────────┐      ┌─────────────┐
│   Fresh     │──────│  Near       │──────│   Expired   │
│   Token     │      │  Expiry     │      │   Token     │
│  (active)   │      │ (<10 min)   │      │  (refresh)  │
└─────────────┘      └──────┬──────┘      └──────┬──────┘
                            │                     │
                            ▼                     ▼
                     ┌─────────────┐      ┌─────────────┐
                     │  Proactive  │      │  On-Demand  │
                     │   Refresh   │      │   Refresh   │
                     └─────────────┘      └─────────────┘
```

**Proactive Refresh:** Background job refreshes tokens 10 minutes before expiry.
**On-Demand Refresh:** If a sync fails with 401, attempt refresh and retry.

### Provider Configuration

```typescript
interface ProviderConfig {
  id: Provider;
  name: string;
  
  // OAuth settings
  oauth: {
    authorizationUrl: string;
    tokenUrl: string;
    revokeUrl?: string;
    scopes: string[];
    pkceRequired: boolean;
    clientId: string;
    clientSecret: string;  // Stored in secrets manager
  };
  
  // Data access
  api: {
    baseUrl: string;
    rateLimit: {
      requests: number;
      windowSeconds: number;
    };
    pagination: "cursor" | "offset" | "page";
  };
  
  // Sync capabilities
  sync: {
    supportsWebhooks: boolean;
    webhookEvents?: string[];
    pollingIntervalMin: number;
    historicalDataDays: number;  // How far back we can fetch
  };
}
```

**Example: Eight Sleep Config**
```typescript
const eightSleepConfig: ProviderConfig = {
  id: Provider.EIGHT_SLEEP,
  name: "Eight Sleep",
  
  oauth: {
    authorizationUrl: "https://client-api.8slp.net/v1/oauth/authorize",
    tokenUrl: "https://client-api.8slp.net/v1/oauth/token",
    scopes: ["user.read", "sleep.read"],
    pkceRequired: false,
    clientId: process.env.EIGHT_SLEEP_CLIENT_ID,
    clientSecret: process.env.EIGHT_SLEEP_CLIENT_SECRET,
  },
  
  api: {
    baseUrl: "https://client-api.8slp.net/v1",
    rateLimit: { requests: 100, windowSeconds: 60 },
    pagination: "cursor",
  },
  
  sync: {
    supportsWebhooks: false,
    pollingIntervalMin: 60,
    historicalDataDays: 365,
  },
};
```

---

## Webhook Integration

### Webhook Receiver

```typescript
interface WebhookReceiver {
  // Register webhook with provider
  register(sourceId: string, events: string[]): Promise<WebhookRegistration>;
  
  // Handle incoming webhook
  handleWebhook(
    sourceId: string,
    payload: unknown,
    signature: string,
    headers: Record<string, string>
  ): Promise<WebhookResult>;
  
  // Verify webhook signature
  verifySignature(
    payload: string,
    signature: string,
    secret: string
  ): boolean;
}

interface WebhookRegistration {
  webhookId: string;
  url: string;
  secret: string;
  events: string[];
}

interface WebhookResult {
  accepted: boolean;
  eventsCreated: number;
  errors: WebhookError[];
}
```

### Webhook URL Pattern

```
https://api.tributary.app/v1/webhooks/{sourceId}
```

### Signature Verification by Provider

| Provider | Signature Header | Algorithm |
|----------|------------------|-----------|
| GitHub | `X-Hub-Signature-256` | HMAC-SHA256 |
| Todoist | `X-Todoist-Hmac-SHA256` | HMAC-SHA256 |
| Google Calendar | Push notification + `X-Goog-Resource-State` | Token validation |
| Whoop | `X-Whoop-Signature` | HMAC-SHA256 |

### Webhook Processing Pipeline

```
Webhook Request
       │
       ▼
┌─────────────────────┐
│  Rate Limiter       │  (prevent abuse)
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Signature          │  (verify authenticity)
│  Verification       │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Idempotency        │  (deduplicate)
│  Check              │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Adapter            │  (transform to Event)
│  Transform          │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  Event Store        │  (persist)
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│  WebSocket          │  (notify clients)
│  Broadcast          │
└─────────────────────┘
```

### Idempotency

Webhooks include a unique event ID to prevent duplicate processing:

```typescript
interface WebhookIdempotency {
  // Check if webhook already processed
  async isProcessed(webhookId: string): Promise<boolean>;
  
  // Mark webhook as processed
  async markProcessed(webhookId: string, ttlHours: number): Promise<void>;
}
```

Storage: Redis with 72-hour TTL.

---

## Polling Integration

### Polling Scheduler

For services without webhook support:

```typescript
interface PollingScheduler {
  // Schedule recurring sync
  scheduleSync(sourceId: string, intervalMinutes: number): Promise<void>;
  
  // Execute immediate sync
  syncNow(sourceId: string): Promise<SyncResult>;
  
  // Get sync status
  getSyncStatus(sourceId: string): Promise<SyncStatus>;
  
  // Pause/resume syncing
  pauseSync(sourceId: string): Promise<void>;
  resumeSync(sourceId: string): Promise<void>;
}

interface SyncResult {
  syncId: string;
  sourceId: string;
  startedAt: DateTime;
  completedAt: DateTime;
  status: "success" | "partial" | "failed";
  
  eventsCreated: number;
  eventsUpdated: number;
  eventsDeleted: number;
  
  errors: SyncError[];
  nextSyncAt: DateTime;
}
```

### Polling Strategy

```
┌─────────────────────────────────────────────────────────────────┐
│                    POLLING SCHEDULER (Bull Queue)                │
└──────────────────────────────┬──────────────────────────────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
        ▼                      ▼                      ▼
┌───────────────┐      ┌───────────────┐      ┌───────────────┐
│  Eight Sleep  │      │    Oura       │      │   Fitbit      │
│  Every 60min  │      │  Every 30min  │      │  Every 15min  │
└───────┬───────┘      └───────┬───────┘      └───────┬───────┘
        │                      │                      │
        │    ┌─────────────────┴──────────────────┐   │
        │    │                                     │   │
        ▼    ▼                                     ▼   ▼
┌─────────────────────────────────────────────────────────────────┐
│                     SYNC WORKER POOL                             │
│  (Concurrent workers with per-provider rate limiting)           │
└─────────────────────────────────────────────────────────────────┘
```

### Incremental Sync

Each adapter tracks sync state to fetch only new/changed data:

```typescript
interface SyncState {
  sourceId: string;
  lastSyncAt: DateTime;
  lastCursor: string | null;     // For cursor-based APIs
  lastSyncedId: string | null;   // For ID-based APIs
  metadata: Record<string, any>; // Provider-specific state
}
```

**Example: Eight Sleep Incremental Sync**
```typescript
async function syncEightSleep(source: Source, state: SyncState): Promise<Event[]> {
  const client = new EightSleepClient(source.credentials);
  
  // Fetch intervals since last sync
  const intervals = await client.getSleepIntervals({
    from: state.lastSyncAt ?? subDays(new Date(), 30),
    to: new Date(),
  });
  
  // Transform to Tributary events
  return intervals.map(interval => ({
    type: "sleep",
    occurredAt: interval.startTime,
    duration: interval.duration,
    title: "Sleep Session",
    data: {
      sleepScore: interval.score,
      timeAsleep: interval.timeAsleep,
      deepSleep: interval.stages.deep,
      remSleep: interval.stages.rem,
      lightSleep: interval.stages.light,
      hrvAvg: interval.hrv.average,
    },
    externalId: interval.id,
  }));
}
```

### Backfill Strategy

On initial connection, fetch historical data in chunks:

```typescript
interface BackfillConfig {
  provider: Provider;
  daysToBackfill: number;
  chunkSizeDays: number;
  delayBetweenChunksMs: number;  // Respect rate limits
}

// Default backfill configs
const backfillConfigs: Record<Provider, BackfillConfig> = {
  [Provider.EIGHT_SLEEP]: {
    daysToBackfill: 365,
    chunkSizeDays: 30,
    delayBetweenChunksMs: 1000,
  },
  [Provider.WHOOP]: {
    daysToBackfill: 90,
    chunkSizeDays: 7,
    delayBetweenChunksMs: 500,
  },
  [Provider.GOOGLE_CALENDAR]: {
    daysToBackfill: 180,
    chunkSizeDays: 30,
    delayBetweenChunksMs: 100,
  },
};
```

---

## Adapter System

### Base Adapter Interface

```typescript
interface SourceAdapter {
  provider: Provider;
  
  // Authentication
  getAuthUrl(state: string): string;
  exchangeCode(code: string): Promise<OAuthTokens>;
  refreshToken(refreshToken: string): Promise<OAuthTokens>;
  
  // Data fetching
  fetchEvents(
    credentials: Credentials,
    since: DateTime,
    until: DateTime
  ): Promise<RawEvent[]>;
  
  // Transformation
  transformEvent(raw: RawEvent): Event;
  
  // Webhooks (optional)
  registerWebhook?(credentials: Credentials): Promise<WebhookRegistration>;
  handleWebhook?(payload: unknown): Event[];
  
  // Health check
  testConnection(credentials: Credentials): Promise<boolean>;
}
```

### Transform Pipeline

```typescript
interface TransformPipeline {
  // Main transform flow
  async transform(
    raw: unknown,
    adapter: SourceAdapter,
    config: TransformConfig
  ): Promise<Event> {
    // 1. Validate raw data
    const validated = await this.validate(raw, adapter.schema);
    
    // 2. Apply adapter-specific transform
    let event = adapter.transformEvent(validated);
    
    // 3. Apply user-defined transforms
    event = await this.applyUserTransforms(event, config.transforms);
    
    // 4. Enrich with AI (optional)
    if (config.aiEnrichment) {
      event = await this.enrichWithAI(event);
    }
    
    // 5. Link to entities
    event = await this.linkEntities(event);
    
    return event;
  }
}
```

### User-Defined Transforms

Users can configure transforms on their sources:

```typescript
interface Transform {
  type: "map" | "filter" | "tag" | "enrich";
  config: TransformConfig;
}

// Example: Auto-tag workouts by type
const workoutTagTransform: Transform = {
  type: "tag",
  config: {
    conditions: [
      { field: "data.activityType", equals: "running", tag: "cardio" },
      { field: "data.activityType", equals: "weights", tag: "strength" },
      { field: "data.duration", greaterThan: 3600, tag: "long_workout" },
    ],
  },
};

// Example: Filter out short sleep sessions
const sleepFilterTransform: Transform = {
  type: "filter",
  config: {
    field: "duration",
    greaterThan: 3600,  // Only keep sleep > 1 hour
  },
};
```

---

## Rate Limiting

### Per-Provider Rate Limits

```typescript
interface RateLimiter {
  // Check if request is allowed
  async checkLimit(provider: Provider, userId: string): Promise<{
    allowed: boolean;
    retryAfter?: number;
  }>;
  
  // Record a request
  async recordRequest(provider: Provider, userId: string): Promise<void>;
}
```

**Implementation:** Redis sliding window with provider-specific limits.

### Rate Limit Configuration

| Provider | Requests/Min | Burst | Strategy |
|----------|-------------|-------|----------|
| Eight Sleep | 100 | 20 | Token bucket |
| Whoop | 120 | 30 | Token bucket |
| Google Calendar | 600 | 100 | Fixed window |
| Todoist | 450 | 50 | Sliding window |
| GitHub | 5000/hr | 100 | Fixed window |

### Backoff Strategy

```typescript
interface BackoffConfig {
  initialDelayMs: number;
  maxDelayMs: number;
  multiplier: number;
  jitterPercent: number;
}

const defaultBackoff: BackoffConfig = {
  initialDelayMs: 1000,
  maxDelayMs: 60000,
  multiplier: 2,
  jitterPercent: 10,
};

function calculateBackoff(attempt: number, config: BackoffConfig): number {
  const delay = Math.min(
    config.initialDelayMs * Math.pow(config.multiplier, attempt),
    config.maxDelayMs
  );
  const jitter = delay * (config.jitterPercent / 100) * (Math.random() - 0.5);
  return delay + jitter;
}
```

---

## Error Handling

### Error Categories

```typescript
enum IntegrationErrorType {
  AUTH_EXPIRED = "auth_expired",           // Token needs refresh
  AUTH_REVOKED = "auth_revoked",           // User revoked access
  RATE_LIMITED = "rate_limited",           // Hit rate limit
  PROVIDER_ERROR = "provider_error",       // Provider API error
  NETWORK_ERROR = "network_error",         // Connection failed
  TRANSFORM_ERROR = "transform_error",     // Data transform failed
  VALIDATION_ERROR = "validation_error",   // Invalid data
}

interface IntegrationError {
  type: IntegrationErrorType;
  provider: Provider;
  sourceId: string;
  message: string;
  retryable: boolean;
  retryAfter?: number;
  context?: Record<string, any>;
}
```

### Error Recovery

```typescript
const errorHandlers: Record<IntegrationErrorType, ErrorHandler> = {
  [IntegrationErrorType.AUTH_EXPIRED]: async (error, source) => {
    // Attempt token refresh
    try {
      await oauthManager.refreshTokens(source.id);
      return { action: "retry", delay: 0 };
    } catch {
      return { action: "disconnect", notify: true };
    }
  },
  
  [IntegrationErrorType.AUTH_REVOKED]: async (error, source) => {
    // Mark as disconnected, notify user
    return { action: "disconnect", notify: true };
  },
  
  [IntegrationErrorType.RATE_LIMITED]: async (error) => {
    // Wait and retry
    return { action: "retry", delay: error.retryAfter ?? 60000 };
  },
  
  [IntegrationErrorType.NETWORK_ERROR]: async (error, source, attempt) => {
    // Exponential backoff
    if (attempt < 5) {
      return { action: "retry", delay: calculateBackoff(attempt) };
    }
    return { action: "skip", scheduleRetry: true };
  },
};
```

---

## Security

### Credential Storage

```typescript
interface CredentialVault {
  // Store encrypted credentials
  store(sourceId: string, credentials: Credentials): Promise<void>;
  
  // Retrieve and decrypt
  retrieve(sourceId: string): Promise<Credentials>;
  
  // Delete credentials
  delete(sourceId: string): Promise<void>;
  
  // Rotate encryption key
  rotateKey(): Promise<void>;
}
```

**Encryption:** AES-256-GCM with per-user derived keys.
**Storage:** Separate database/table with restricted access.

### Webhook Security

1. **Signature verification** - Required for all webhooks
2. **IP allowlisting** - Optional, provider-specific
3. **Replay protection** - Timestamp validation (5-minute window)
4. **HTTPS only** - No HTTP webhooks accepted

### Data Isolation

- Each user's credentials stored with their user_id
- Database row-level security on source/event tables
- API tokens scoped to specific sources
- No cross-user data access possible

---

## Monitoring

### Metrics

```typescript
interface IntegrationMetrics {
  // Sync metrics
  syncDuration: Histogram;
  syncSuccess: Counter;
  syncFailure: Counter;
  eventsProcessed: Counter;
  
  // API metrics  
  apiLatency: Histogram;
  apiErrors: Counter;
  rateLimitHits: Counter;
  
  // Webhook metrics
  webhooksReceived: Counter;
  webhooksProcessed: Counter;
  webhooksRejected: Counter;
  
  // Health metrics
  sourceHealthy: Gauge;
  tokensExpiringSoon: Gauge;
}
```

### Alerting

| Condition | Severity | Action |
|-----------|----------|--------|
| Sync failing > 3 times | Warning | Notify user |
| Token expires in < 1 day | Warning | Attempt refresh, notify if fails |
| Provider API down | Critical | Pause syncs, monitor |
| High error rate (>10%) | Warning | Investigate |
| Webhook signature invalid | Warning | Log, investigate pattern |

---

## Provider-Specific Notes

### Eight Sleep
- No webhook support; polling required
- API rate limit: 100/min
- Sleep data available ~30 min after waking
- Temperature data real-time

### Whoop
- Webhooks for recovery/sleep/workout completion
- Requires PKCE for OAuth
- Strain scores calculated end-of-day

### Google Calendar
- Push notifications via Pub/Sub
- Watch channels expire; need renewal
- Supports incremental sync tokens

### Todoist
- Webhooks for item events
- Sync API for full sync
- Activity log for historical changes

### GitHub
- Comprehensive webhook support
- App installation flow vs OAuth
- GraphQL API for efficient queries

### Plaid
- Link token flow (not standard OAuth)
- Transactions update asynchronously
- Requires handling of item errors
