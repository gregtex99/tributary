# Tributary API Design

## Overview

Tributary exposes a RESTful API for CRUD operations and a WebSocket API for real-time updates. All APIs are versioned and require authentication.

**Base URL:** `https://api.tributary.app/v1`
**WebSocket:** `wss://api.tributary.app/v1/ws`

---

## Authentication

### Auth Flow (OAuth 2.0 + PKCE)

```
┌─────────┐                              ┌─────────────┐                    ┌──────────┐
│  Client │                              │  Tributary  │                    │  Google  │
│   App   │                              │    Auth     │                    │  (IdP)   │
└────┬────┘                              └──────┬──────┘                    └────┬─────┘
     │                                          │                                │
     │  1. GET /auth/providers                  │                                │
     │─────────────────────────────────────────>│                                │
     │  { providers: ["google", "apple", ...]}  │                                │
     │<─────────────────────────────────────────│                                │
     │                                          │                                │
     │  2. POST /auth/login                     │                                │
     │     { provider: "google",                │                                │
     │       codeVerifier, codeChallenge }      │                                │
     │─────────────────────────────────────────>│                                │
     │  { authUrl: "..." }                      │                                │
     │<─────────────────────────────────────────│                                │
     │                                          │                                │
     │  3. Redirect to Google                   │                                │
     │─────────────────────────────────────────────────────────────────────────>│
     │                                          │                                │
     │  4. User authenticates                   │                                │
     │<─────────────────────────────────────────────────────────────────────────│
     │     (redirect with code)                 │                                │
     │                                          │                                │
     │  5. POST /auth/callback                  │                                │
     │     { code, codeVerifier, provider }     │                                │
     │─────────────────────────────────────────>│  6. Exchange code             │
     │                                          │─────────────────────────────>│
     │                                          │  { access_token, id_token }   │
     │                                          │<─────────────────────────────│
     │  7. { accessToken, refreshToken, user }  │                                │
     │<─────────────────────────────────────────│                                │
```

### Token Format

**Access Token (JWT)**
```json
{
  "sub": "user_abc123",
  "email": "user@example.com",
  "iat": 1706644800,
  "exp": 1706648400,
  "scope": "read write",
  "type": "access"
}
```

**Refresh Token:** Opaque, stored server-side, 30-day expiry.

### API Authentication

```http
GET /v1/streams
Authorization: Bearer eyJhbGciOiJSUzI1NiIs...
```

### Rate Limits

| Plan | Requests/min | WebSocket connections |
|------|-------------|----------------------|
| Free | 60 | 1 |
| Pro | 600 | 5 |
| Team | 6000 | 50 |

Rate limit headers:
```http
X-RateLimit-Limit: 600
X-RateLimit-Remaining: 547
X-RateLimit-Reset: 1706644860
```

---

## REST API Endpoints

### Streams

#### List Streams
```http
GET /streams

Response 200:
{
  "streams": [
    {
      "id": "stream_abc123",
      "type": "health",
      "name": "My Health",
      "icon": "❤️",
      "color": "#ef4444",
      "enabled": true,
      "sourceCount": 3,
      "eventCount": 1234,
      "lastEventAt": "2026-01-30T15:00:00Z",
      "createdAt": "2025-06-01T00:00:00Z"
    }
  ]
}
```

#### Get Stream
```http
GET /streams/:streamId

Response 200:
{
  "id": "stream_abc123",
  "type": "health",
  "name": "My Health",
  "icon": "❤️",
  "color": "#ef4444",
  "enabled": true,
  "settings": {
    "visibility": "private",
    "retentionDays": null,
    "syncFrequency": 15,
    "notifications": {
      "onNewEvent": false,
      "dailyDigest": true
    }
  },
  "sources": [
    {
      "id": "source_xyz789",
      "provider": "eight_sleep",
      "name": "Eight Sleep",
      "status": "connected",
      "lastSyncAt": "2026-01-30T14:45:00Z"
    }
  ],
  "stats": {
    "totalEvents": 1234,
    "eventsToday": 5,
    "eventsThisWeek": 42
  },
  "createdAt": "2025-06-01T00:00:00Z",
  "updatedAt": "2026-01-30T14:45:00Z"
}
```

#### Create Stream
```http
POST /streams

Request:
{
  "type": "health",
  "name": "My Health",
  "icon": "❤️",
  "color": "#ef4444",
  "settings": {
    "visibility": "private",
    "syncFrequency": 15
  }
}

Response 201:
{
  "id": "stream_abc123",
  "type": "health",
  ...
}
```

#### Update Stream
```http
PATCH /streams/:streamId

Request:
{
  "name": "Health Tracker",
  "settings": {
    "syncFrequency": 30
  }
}

Response 200:
{ ... updated stream ... }
```

#### Delete Stream
```http
DELETE /streams/:streamId

Response 204: (no content)
```

---

### Events

#### Query Events
```http
GET /events
  ?streamId=stream_abc123
  &type=sleep
  &from=2026-01-01T00:00:00Z
  &to=2026-01-31T23:59:59Z
  &tags=important
  &limit=50
  &cursor=cursor_xyz

Response 200:
{
  "events": [
    {
      "id": "event_def456",
      "streamId": "stream_abc123",
      "sourceId": "source_xyz789",
      "type": "sleep",
      "occurredAt": "2026-01-30T06:30:00Z",
      "duration": 28800,
      "title": "Sleep Session",
      "data": {
        "sleepScore": 85,
        "timeAsleep": 27000,
        "deepSleep": 5400,
        "remSleep": 6300,
        "hrvAvg": 45
      },
      "tags": ["good_sleep"],
      "importance": 75,
      "createdAt": "2026-01-30T06:30:00Z"
    }
  ],
  "cursor": "cursor_abc123",
  "hasMore": true
}
```

#### Get Event
```http
GET /events/:eventId

Response 200:
{
  "id": "event_def456",
  "streamId": "stream_abc123",
  ... full event data ...
  "linkedEvents": [
    {
      "id": "event_ghi789",
      "type": "workout",
      "title": "Evening Run",
      "linkType": "caused_by"
    }
  ],
  "linkedEntities": [
    {
      "type": "person",
      "id": "entity_jkl012",
      "name": "Dr. Smith"
    }
  ]
}
```

#### Create Event
```http
POST /events

Request:
{
  "streamId": "stream_abc123",
  "type": "measurement",
  "occurredAt": "2026-01-30T08:00:00Z",
  "title": "Morning Weight",
  "data": {
    "value": 175.5,
    "unit": "lbs",
    "measurementType": "weight"
  },
  "tags": ["morning_routine"]
}

Response 201:
{ ... created event ... }
```

#### Update Event
```http
PATCH /events/:eventId

Request:
{
  "tags": ["morning_routine", "important"],
  "data": {
    "notes": "Feeling good today"
  }
}

Response 200:
{ ... updated event ... }
```

#### Delete Event
```http
DELETE /events/:eventId
  ?hard=false  // soft delete by default

Response 204: (no content)
```

#### Bulk Operations
```http
POST /events/bulk

Request:
{
  "operations": [
    {
      "action": "create",
      "event": { ... }
    },
    {
      "action": "update",
      "eventId": "event_abc123",
      "changes": { ... }
    },
    {
      "action": "delete",
      "eventId": "event_xyz789"
    }
  ]
}

Response 200:
{
  "results": [
    { "success": true, "eventId": "event_new123" },
    { "success": true, "eventId": "event_abc123" },
    { "success": false, "error": "Event not found" }
  ]
}
```

---

### Sources (Integrations)

#### List Available Providers
```http
GET /providers

Response 200:
{
  "providers": [
    {
      "id": "eight_sleep",
      "name": "Eight Sleep",
      "icon": "https://...",
      "streams": ["health"],
      "authType": "oauth2",
      "features": ["sleep", "temperature"],
      "status": "available"
    },
    {
      "id": "whoop",
      "name": "Whoop",
      "icon": "https://...",
      "streams": ["health"],
      "authType": "oauth2",
      "features": ["sleep", "workout", "recovery", "strain"],
      "status": "available"
    }
  ]
}
```

#### Connect Source
```http
POST /sources/connect

Request:
{
  "provider": "eight_sleep",
  "streamId": "stream_abc123"
}

Response 200:
{
  "authUrl": "https://accounts.eightsleep.com/oauth/authorize?...",
  "state": "state_xyz789"
}
```

#### Complete OAuth Callback
```http
POST /sources/callback

Request:
{
  "provider": "eight_sleep",
  "code": "auth_code_from_redirect",
  "state": "state_xyz789"
}

Response 201:
{
  "source": {
    "id": "source_new123",
    "provider": "eight_sleep",
    "status": "connected",
    "syncConfig": {
      "mode": "polling",
      "intervalMinutes": 60
    }
  },
  "initialSyncStarted": true
}
```

#### List Connected Sources
```http
GET /sources
  ?streamId=stream_abc123

Response 200:
{
  "sources": [
    {
      "id": "source_xyz789",
      "streamId": "stream_abc123",
      "provider": "eight_sleep",
      "name": "Eight Sleep",
      "status": "connected",
      "lastSyncAt": "2026-01-30T14:45:00Z",
      "lastSyncStatus": "success",
      "eventCount": 365
    }
  ]
}
```

#### Trigger Manual Sync
```http
POST /sources/:sourceId/sync

Response 202:
{
  "syncId": "sync_abc123",
  "status": "queued",
  "estimatedDuration": 30
}
```

#### Get Sync Status
```http
GET /sources/:sourceId/sync/:syncId

Response 200:
{
  "syncId": "sync_abc123",
  "status": "completed",
  "startedAt": "2026-01-30T14:45:00Z",
  "completedAt": "2026-01-30T14:45:25Z",
  "eventsCreated": 5,
  "eventsUpdated": 2,
  "errors": []
}
```

#### Update Source Settings
```http
PATCH /sources/:sourceId

Request:
{
  "name": "Bedroom Eight Sleep",
  "syncConfig": {
    "intervalMinutes": 30
  }
}

Response 200:
{ ... updated source ... }
```

#### Disconnect Source
```http
DELETE /sources/:sourceId
  ?deleteEvents=false  // Keep historical data by default

Response 204: (no content)
```

---

### Entities

#### List Entities
```http
GET /entities
  ?type=person
  &search=smith
  &limit=20

Response 200:
{
  "entities": [
    {
      "id": "entity_abc123",
      "type": "person",
      "name": "John Smith",
      "email": "john@example.com",
      "metadata": {
        "company": "Acme Corp",
        "role": "Engineer"
      },
      "eventCount": 42,
      "lastEventAt": "2026-01-28T12:00:00Z"
    }
  ]
}
```

#### Create Entity
```http
POST /entities

Request:
{
  "type": "person",
  "name": "Jane Doe",
  "email": "jane@example.com",
  "metadata": {
    "company": "Tech Inc"
  }
}

Response 201:
{ ... created entity ... }
```

#### Link Event to Entity
```http
POST /events/:eventId/links

Request:
{
  "entityId": "entity_abc123"
}

Response 201:
{
  "linked": true
}
```

---

### Timeline & Summaries

#### Get Timeline
```http
GET /timeline
  ?date=2026-01-30
  &streams=health,calendar,tasks
  &granularity=hour

Response 200:
{
  "date": "2026-01-30",
  "timezone": "America/Chicago",
  "hours": [
    {
      "hour": 6,
      "events": [
        {
          "id": "event_abc123",
          "stream": "health",
          "type": "sleep",
          "title": "Woke up",
          "occurredAt": "2026-01-30T06:30:00Z"
        }
      ]
    },
    {
      "hour": 9,
      "events": [
        {
          "id": "event_def456",
          "stream": "calendar",
          "type": "meeting",
          "title": "Standup",
          "occurredAt": "2026-01-30T09:00:00Z",
          "duration": 900
        }
      ]
    }
  ]
}
```

#### Get Daily Summary
```http
GET /summaries/:date
  // date format: YYYY-MM-DD

Response 200:
{
  "date": "2026-01-30",
  "score": 78,
  "health": {
    "sleepScore": 85,
    "activeMinutes": 45,
    "steps": 8234
  },
  "calendar": {
    "eventCount": 6,
    "meetingHours": 3.5,
    "focusHours": 2
  },
  "tasks": {
    "completed": 8,
    "added": 3,
    "overdue": 1
  },
  "highlights": [
    "Great sleep last night (85 score)",
    "Completed 8 tasks",
    "Met with Sarah about project kickoff"
  ],
  "insights": [
    {
      "id": "insight_abc123",
      "type": "correlation",
      "title": "Sleep affects productivity",
      "description": "You completed 40% more tasks on days after good sleep"
    }
  ]
}
```

#### Get Insights
```http
GET /insights
  ?type=suggestion
  &dismissed=false
  &limit=10

Response 200:
{
  "insights": [
    {
      "id": "insight_abc123",
      "type": "suggestion",
      "title": "Schedule focus time",
      "description": "You have 6 hours of meetings tomorrow. Consider blocking focus time in the morning.",
      "priority": 80,
      "actionable": true,
      "suggestedAction": "Block 9-11am for deep work",
      "relatedStreams": ["calendar", "tasks"],
      "createdAt": "2026-01-30T15:00:00Z"
    }
  ]
}
```

#### Dismiss Insight
```http
POST /insights/:insightId/dismiss

Response 200:
{
  "dismissed": true
}
```

---

## WebSocket API

### Connection
```javascript
const ws = new WebSocket('wss://api.tributary.app/v1/ws', {
  headers: {
    'Authorization': 'Bearer eyJhbGc...'
  }
});
```

### Subscribe to Updates
```json
// Client → Server
{
  "type": "subscribe",
  "channels": [
    "stream:stream_abc123",
    "events:health",
    "insights",
    "sync:source_xyz789"
  ]
}

// Server → Client
{
  "type": "subscribed",
  "channels": ["stream:stream_abc123", "events:health", "insights", "sync:source_xyz789"]
}
```

### Event Types

#### New Event
```json
{
  "type": "event.created",
  "data": {
    "id": "event_abc123",
    "streamId": "stream_xyz789",
    "type": "sleep",
    "title": "Sleep Session",
    "occurredAt": "2026-01-30T06:30:00Z"
  },
  "timestamp": "2026-01-30T06:30:05Z"
}
```

#### Event Updated
```json
{
  "type": "event.updated",
  "data": {
    "id": "event_abc123",
    "changes": {
      "tags": ["good_sleep", "recovery"]
    }
  },
  "timestamp": "2026-01-30T06:35:00Z"
}
```

#### Sync Progress
```json
{
  "type": "sync.progress",
  "data": {
    "sourceId": "source_xyz789",
    "syncId": "sync_abc123",
    "progress": 45,
    "eventsProcessed": 23,
    "status": "running"
  },
  "timestamp": "2026-01-30T14:45:10Z"
}
```

#### New Insight
```json
{
  "type": "insight.created",
  "data": {
    "id": "insight_abc123",
    "type": "correlation",
    "title": "Sleep pattern detected",
    "priority": 70
  },
  "timestamp": "2026-01-30T08:00:00Z"
}
```

### Heartbeat
```json
// Server → Client (every 30s)
{ "type": "ping" }

// Client → Server
{ "type": "pong" }
```

### Unsubscribe
```json
{
  "type": "unsubscribe",
  "channels": ["events:health"]
}
```

---

## Integration Webhooks

External services can push data to Tributary via webhooks.

### Webhook Endpoint
```http
POST /webhooks/:sourceId
X-Webhook-Signature: sha256=abc123...
Content-Type: application/json

{
  "event": "sleep.completed",
  "timestamp": "2026-01-30T06:30:00Z",
  "data": {
    // Provider-specific payload
  }
}
```

### Signature Verification
```javascript
const crypto = require('crypto');

function verifyWebhook(payload, signature, secret) {
  const expected = 'sha256=' + crypto
    .createHmac('sha256', secret)
    .update(payload)
    .digest('hex');
  
  return crypto.timingSafeEqual(
    Buffer.from(signature),
    Buffer.from(expected)
  );
}
```

### Webhook Registration
```http
POST /sources/:sourceId/webhook

Request:
{
  "events": ["sleep.completed", "workout.completed"],
  "secret": "webhook_secret_xyz"  // Optional, we can generate
}

Response 201:
{
  "webhookUrl": "https://api.tributary.app/v1/webhooks/source_xyz789",
  "secret": "whsec_abc123xyz",
  "events": ["sleep.completed", "workout.completed"]
}
```

### Supported Webhook Events by Provider

| Provider | Events |
|----------|--------|
| Eight Sleep | `sleep.completed`, `temperature.changed` |
| Whoop | `sleep.completed`, `workout.completed`, `recovery.updated` |
| Google Calendar | `event.created`, `event.updated`, `event.deleted` |
| Todoist | `item.completed`, `item.added`, `item.updated` |
| GitHub | `push`, `pull_request.*`, `issues.*` |

---

## Error Responses

### Standard Error Format
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Invalid request parameters",
    "details": [
      {
        "field": "occurredAt",
        "message": "Must be a valid ISO 8601 datetime"
      }
    ],
    "requestId": "req_abc123xyz"
  }
}
```

### Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `UNAUTHORIZED` | 401 | Missing or invalid token |
| `FORBIDDEN` | 403 | Insufficient permissions |
| `NOT_FOUND` | 404 | Resource doesn't exist |
| `VALIDATION_ERROR` | 400 | Invalid request data |
| `CONFLICT` | 409 | Resource already exists |
| `RATE_LIMITED` | 429 | Too many requests |
| `INTERNAL_ERROR` | 500 | Server error |
| `SERVICE_UNAVAILABLE` | 503 | Temporarily unavailable |

---

## Pagination

All list endpoints use cursor-based pagination:

```http
GET /events?limit=50&cursor=eyJpZCI6ImV2ZW50XzEyMyJ9

Response:
{
  "events": [...],
  "cursor": "eyJpZCI6ImV2ZW50XzE3MyJ9",
  "hasMore": true
}
```

Cursors are opaque, base64-encoded strings. Don't parse them.

---

## Versioning

API version is in the URL path: `/v1/`, `/v2/`, etc.

- Breaking changes → new major version
- Additive changes → same version (new fields, endpoints)
- Deprecation → 6 month warning via `X-API-Deprecation` header

```http
X-API-Deprecation: This endpoint will be removed on 2026-07-01. Use /v2/events instead.
```
