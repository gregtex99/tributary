# Tributary Data Model

## Core Concept

Tributary treats life data as **streams** - continuous flows of events from various sources that converge into a unified view. Each stream represents a domain of life (health, work, relationships, etc.), and events within streams follow a common schema while supporting domain-specific extensions.

---

## Stream Schema

A **Stream** is a category of life data with a defined type and configuration.

```typescript
interface Stream {
  id: string;                    // UUID
  userId: string;                // Owner
  type: StreamType;              // Enum of supported stream types
  name: string;                  // Display name (e.g., "My Health")
  icon: string;                  // Emoji or icon identifier
  color: string;                 // Hex color for UI
  enabled: boolean;              // Active/inactive toggle
  sources: Source[];             // Connected data sources
  settings: StreamSettings;      // Stream-specific configuration
  createdAt: DateTime;
  updatedAt: DateTime;
}

enum StreamType {
  HEALTH = "health",
  CALENDAR = "calendar",
  TASKS = "tasks",
  RELATIONSHIPS = "relationships",
  FINANCE = "finance",
  WORK = "work",
  AI = "ai"
}

interface StreamSettings {
  visibility: "public" | "private" | "shared";
  retentionDays: number | null;  // null = forever
  syncFrequency: number;         // Minutes between syncs
  notifications: NotificationConfig;
}
```

### Stream Types & Their Focus

| Stream | Primary Data | Key Metrics |
|--------|-------------|-------------|
| Health | Sleep, exercise, vitals, nutrition | HRV, sleep score, activity |
| Calendar | Events, meetings, time blocks | Free time, meeting load |
| Tasks | Todos, projects, deadlines | Completion rate, overdue count |
| Relationships | Contacts, interactions, milestones | Last contact, relationship health |
| Finance | Transactions, accounts, budgets | Spending, savings rate |
| Work | Projects, tasks, deadlines | Velocity, blockers |
| AI | Assistant interactions, insights | Query patterns, suggestions |

---

## Event/Item Schema (Unified)

All stream data normalizes to **Events** - the atomic unit of Tributary.

```typescript
interface Event {
  // === Identity ===
  id: string;                    // UUID
  streamId: string;              // Parent stream
  sourceId: string;              // Which integration created this
  externalId: string | null;     // ID in source system
  
  // === Classification ===
  type: EventType;               // Category within stream
  subtype: string | null;        // More specific classification
  
  // === Temporal ===
  occurredAt: DateTime;          // When this happened/happens
  duration: number | null;       // Duration in seconds (if applicable)
  timezone: string;              // IANA timezone
  isAllDay: boolean;             // For calendar events
  
  // === Content ===
  title: string;                 // Primary display text
  description: string | null;    // Extended description (markdown)
  data: EventData;               // Type-specific structured data
  
  // === Metadata ===
  tags: string[];                // User-defined tags
  sentiment: number | null;      // -1 to 1 (AI-derived)
  importance: number;            // 0-100 priority score
  
  // === Relationships ===
  linkedEvents: string[];        // Related event IDs
  linkedEntities: EntityRef[];   // People, places, projects
  
  // === System ===
  createdAt: DateTime;
  updatedAt: DateTime;
  syncedAt: DateTime;            // Last sync with source
  deletedAt: DateTime | null;    // Soft delete
}

interface EntityRef {
  type: "person" | "place" | "project" | "company";
  id: string;
  name: string;
}
```

### Event Types by Stream

```typescript
// Health Stream Events
type HealthEventType = 
  | "sleep"           // Sleep session
  | "workout"         // Exercise session
  | "meal"            // Food intake
  | "measurement"     // Weight, BP, etc.
  | "medication"      // Med taken
  | "symptom";        // Health note

// Calendar Stream Events
type CalendarEventType =
  | "meeting"         // With other people
  | "appointment"     // Personal commitment
  | "reminder"        // Time-based alert
  | "focus_time"      // Blocked for deep work
  | "travel";         // Transit time

// Tasks Stream Events
type TaskEventType =
  | "task"            // Single todo
  | "milestone"       // Project checkpoint
  | "deadline"        // Due date
  | "habit";          // Recurring behavior

// Relationships Stream Events
type RelationshipEventType =
  | "interaction"     // Met, called, messaged
  | "milestone"       // Birthday, anniversary
  | "note"            // Observation about person
  | "introduction";   // Met someone new

// Finance Stream Events
type FinanceEventType =
  | "transaction"     // Money movement
  | "bill"            // Recurring charge
  | "income"          // Money received
  | "transfer";       // Between accounts

// Work Stream Events
type WorkEventType =
  | "commit"          // Code commit
  | "pr"              // Pull request
  | "deploy"          // Deployment
  | "task"            // Work task
  | "blocker";        // Impediment

// AI Stream Events
type AIEventType =
  | "query"           // Asked assistant
  | "insight"         // AI-generated observation
  | "suggestion"      // Recommended action
  | "summary";        // Auto-generated summary
```

### Stream-Specific Data Extensions

```typescript
// Health: Sleep Event
interface SleepEventData {
  sleepScore: number;           // 0-100
  timeInBed: number;            // seconds
  timeAsleep: number;           // seconds
  remSleep: number;             // seconds
  deepSleep: number;            // seconds
  lightSleep: number;           // seconds
  awakeTime: number;            // seconds
  heartRateAvg: number;
  hrvAvg: number;
  respiratoryRate: number;
  bedTemp: number | null;       // Eight Sleep specific
}

// Health: Workout Event
interface WorkoutEventData {
  activityType: string;         // "running", "weights", etc.
  calories: number;
  distance: number | null;      // meters
  heartRateAvg: number | null;
  heartRateMax: number | null;
  zones: HeartRateZone[];
}

// Calendar: Meeting Event
interface MeetingEventData {
  location: string | null;
  isVirtual: boolean;
  meetingUrl: string | null;
  attendees: Attendee[];
  status: "confirmed" | "tentative" | "cancelled";
  organizer: string;
}

// Tasks: Task Event
interface TaskEventData {
  status: "pending" | "in_progress" | "completed" | "cancelled";
  priority: "low" | "medium" | "high" | "urgent";
  dueAt: DateTime | null;
  completedAt: DateTime | null;
  projectId: string | null;
  parentTaskId: string | null;  // For subtasks
  labels: string[];
}

// Relationships: Interaction Event
interface InteractionEventData {
  contactId: string;
  channel: "in_person" | "call" | "video" | "email" | "message" | "social";
  initiatedBy: "me" | "them" | "mutual";
  quality: number | null;       // 1-5 rating
  notes: string | null;
  followUpNeeded: boolean;
}

// Finance: Transaction Event
interface TransactionEventData {
  amount: number;               // Always positive
  direction: "in" | "out";
  currency: string;             // ISO 4217
  accountId: string;
  category: string;
  merchant: string | null;
  isRecurring: boolean;
}
```

---

## User Schema

```typescript
interface User {
  id: string;                    // UUID
  email: string;                 // Primary identifier
  name: string;
  avatar: string | null;
  timezone: string;              // Default timezone
  locale: string;                // Language/region
  
  // Preferences
  preferences: UserPreferences;
  
  // Plan
  plan: "free" | "pro" | "team";
  planExpiresAt: DateTime | null;
  
  // System
  createdAt: DateTime;
  updatedAt: DateTime;
  lastActiveAt: DateTime;
}

interface UserPreferences {
  theme: "light" | "dark" | "system";
  weekStartsOn: 0 | 1;          // Sunday or Monday
  timeFormat: "12h" | "24h";
  defaultStreamView: "timeline" | "calendar" | "list";
  
  // Notifications
  emailDigest: "daily" | "weekly" | "none";
  pushEnabled: boolean;
  quietHours: { start: string; end: string } | null;
  
  // Privacy
  shareInsights: boolean;       // Contribute to anonymized analytics
  aiEnabled: boolean;           // Allow AI processing
}
```

---

## Integration/Source Schema

```typescript
interface Source {
  id: string;                    // UUID
  userId: string;
  streamId: string;              // Which stream this feeds
  
  // Integration Details
  provider: Provider;            // Which service
  name: string;                  // User-friendly name
  icon: string;
  
  // Connection
  status: SourceStatus;
  credentials: EncryptedCredentials;
  
  // Sync Configuration
  syncConfig: SyncConfig;
  lastSyncAt: DateTime | null;
  lastSyncStatus: "success" | "partial" | "failed";
  lastSyncError: string | null;
  
  // Metadata
  createdAt: DateTime;
  updatedAt: DateTime;
}

enum Provider {
  // Health
  EIGHT_SLEEP = "eight_sleep",
  APPLE_HEALTH = "apple_health",
  WHOOP = "whoop",
  OURA = "oura",
  FITBIT = "fitbit",
  GARMIN = "garmin",
  
  // Calendar
  GOOGLE_CALENDAR = "google_calendar",
  OUTLOOK_CALENDAR = "outlook_calendar",
  APPLE_CALENDAR = "apple_calendar",
  
  // Tasks
  TODOIST = "todoist",
  NOTION = "notion",
  ASANA = "asana",
  LINEAR = "linear",
  
  // Relationships
  GOOGLE_CONTACTS = "google_contacts",
  APPLE_CONTACTS = "apple_contacts",
  
  // Finance
  PLAID = "plaid",
  
  // Work
  GITHUB = "github",
  GITLAB = "gitlab",
  JIRA = "jira",
  
  // AI
  OPENAI = "openai",
  ANTHROPIC = "anthropic",
  
  // Generic
  WEBHOOK = "webhook",
  CSV_IMPORT = "csv_import",
  MANUAL = "manual"
}

enum SourceStatus {
  CONNECTED = "connected",
  DISCONNECTED = "disconnected",
  EXPIRED = "expired",          // OAuth token expired
  ERROR = "error",              // Persistent failure
  SYNCING = "syncing"
}

interface SyncConfig {
  mode: "realtime" | "polling" | "manual";
  intervalMinutes: number | null;  // For polling
  webhookUrl: string | null;       // For realtime
  filters: SyncFilter[];           // What to sync
  transforms: Transform[];         // Data transformations
}

interface SyncFilter {
  field: string;
  operator: "eq" | "ne" | "gt" | "lt" | "contains" | "in";
  value: any;
}
```

---

## Stream Relationships

Streams don't exist in isolation - they connect and influence each other.

### Cross-Stream Links

```typescript
interface StreamLink {
  id: string;
  fromEventId: string;
  toEventId: string;
  linkType: LinkType;
  strength: number;              // 0-1 confidence
  createdBy: "user" | "system";
  createdAt: DateTime;
}

enum LinkType {
  CAUSED_BY = "caused_by",       // A happened because of B
  BLOCKED_BY = "blocked_by",     // A prevented by B
  RELATED_TO = "related_to",     // General association
  FOLLOWED_BY = "followed_by",   // Temporal sequence
  PART_OF = "part_of"            // Hierarchical
}
```

### Relationship Examples

| Link | Example |
|------|---------|
| Health → Tasks | Poor sleep → Low productivity |
| Calendar → Relationships | Meeting → Interaction logged |
| Tasks → Work | Todo completed → Commit made |
| Finance → Calendar | Bill due → Reminder created |
| AI → All | Insight generated → Links to evidence |

### Entity Graph

Events link to shared entities across streams:

```typescript
interface Entity {
  id: string;
  userId: string;
  type: "person" | "place" | "project" | "company";
  name: string;
  aliases: string[];             // Alternative names
  metadata: Record<string, any>;
  
  // For people
  email: string | null;
  phone: string | null;
  birthday: string | null;       // MM-DD format
  
  // For places
  address: string | null;
  coordinates: { lat: number; lng: number } | null;
  
  createdAt: DateTime;
  updatedAt: DateTime;
}
```

---

## Aggregations & Views

### Daily Summary

```typescript
interface DailySummary {
  userId: string;
  date: string;                  // YYYY-MM-DD
  
  // Per-stream summaries
  health: {
    sleepScore: number | null;
    activeMinutes: number;
    steps: number;
    caloriesBurned: number;
  };
  
  calendar: {
    eventCount: number;
    meetingHours: number;
    focusHours: number;
  };
  
  tasks: {
    completed: number;
    added: number;
    overdue: number;
  };
  
  relationships: {
    interactionsCount: number;
    newContacts: number;
  };
  
  finance: {
    spent: number;
    earned: number;
  };
  
  work: {
    commits: number;
    prsOpened: number;
    prsMerged: number;
  };
  
  // AI-generated
  highlights: string[];
  score: number;                 // Overall day score
  
  generatedAt: DateTime;
}
```

### Insights

```typescript
interface Insight {
  id: string;
  userId: string;
  type: InsightType;
  title: string;
  description: string;
  
  // Evidence
  relatedStreams: StreamType[];
  relatedEvents: string[];
  confidence: number;            // 0-1
  
  // Action
  actionable: boolean;
  suggestedAction: string | null;
  actionTaken: boolean;
  
  // Display
  priority: number;
  expiresAt: DateTime | null;
  dismissedAt: DateTime | null;
  
  createdAt: DateTime;
}

enum InsightType {
  CORRELATION = "correlation",   // A affects B
  ANOMALY = "anomaly",           // Unusual pattern
  TREND = "trend",               // Directional change
  REMINDER = "reminder",         // Time-based prompt
  SUGGESTION = "suggestion"      // Recommended action
}
```

---

## Data Flow Summary

```
┌─────────────────────────────────────────────────────────────────┐
│                         EXTERNAL SOURCES                         │
│  Eight Sleep │ Google Cal │ Todoist │ Whoop │ GitHub │ ...      │
└──────┬───────┴─────┬──────┴────┬────┴───┬───┴────┬───┴──────────┘
       │             │           │        │        │
       ▼             ▼           ▼        ▼        ▼
┌─────────────────────────────────────────────────────────────────┐
│                      INTEGRATION LAYER                           │
│  OAuth Tokens │ Webhooks │ Polling │ Transforms │ Normalization │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                         EVENT STORE                              │
│  Unified Events │ Entity Graph │ Stream Links │ Metadata        │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                      AGGREGATION LAYER                           │
│  Daily Summaries │ Insights │ Trends │ Correlations │ AI        │
└──────────────────────────────┬──────────────────────────────────┘
                               │
                               ▼
┌─────────────────────────────────────────────────────────────────┐
│                         API / UI                                 │
│  REST │ WebSocket │ Web App │ Mobile │ Widgets │ Integrations   │
└─────────────────────────────────────────────────────────────────┘
```
