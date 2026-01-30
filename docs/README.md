# Tributary Architecture Documentation

## Overview

Tributary is a **unified life operating system** where different "streams" of life data flow together into one view. This documentation covers the complete product architecture.

## Concept

Life generates data across many domains - health metrics, calendar events, tasks, relationships, finances, work activity, and AI interactions. Tributary normalizes all this data into a unified stream-based model, enabling:

- **Single timeline view** of all life events
- **Cross-domain insights** (e.g., sleep affects productivity)
- **AI-powered suggestions** based on patterns
- **Unified search** across all data

## Streams

| Stream | Sources | Key Events |
|--------|---------|------------|
| **Health** | Eight Sleep, Whoop, Apple Health, Oura | Sleep, workouts, vitals, meals |
| **Calendar** | Google Calendar, Outlook, Apple Calendar | Meetings, appointments, focus time |
| **Tasks** | Todoist, Notion, Asana, Linear | Todos, projects, deadlines |
| **Relationships** | Google Contacts, CRM | Interactions, milestones |
| **Finance** | Plaid (future) | Transactions, bills |
| **Work** | GitHub, GitLab, Jira | Commits, PRs, deployments |
| **AI** | Trinity, OpenAI, Anthropic | Queries, insights, suggestions |

## Documentation

### [Data Model](./data-model.md)
Core schemas for streams, events, users, and integrations. Defines how all data is structured and related.

**Key concepts:**
- Stream schema (what defines a stream)
- Unified Event schema (common format for all stream items)
- Entity graph (people, places, projects)
- Cross-stream relationships

### [API Design](./api-design.md)
Complete REST and WebSocket API specification.

**Key endpoints:**
- `/streams` - Stream CRUD
- `/events` - Event queries and mutations
- `/sources` - Integration management
- `/timeline` - Unified timeline view
- `/insights` - AI-generated insights

### [Integration Architecture](./integration-architecture.md)
How external services connect to Tributary.

**Key components:**
- OAuth 2.0 flow for user authorization
- Webhook receiver for real-time updates
- Polling scheduler for services without webhooks
- Adapter system for data transformation

### [Database Schema](./database-schema.md)
PostgreSQL schema with partitioning, RLS, and full-text search.

**Key tables:**
- `users` - User accounts and preferences
- `streams` - Stream configurations
- `sources` - Integration connections
- `events` - Partitioned event store
- `entities` - People, places, projects
- `insights` - AI-generated insights

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                              CLIENTS                                     │
│         Web App │ Mobile App │ Widgets │ API Consumers                  │
└────────────────────────────────┬────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                            API GATEWAY                                   │
│            Auth │ Rate Limiting │ Routing │ WebSocket                   │
└────────────────────────────────┬────────────────────────────────────────┘
                                 │
         ┌───────────────────────┼───────────────────────┐
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   REST API      │    │  WebSocket API  │    │  Webhook API    │
│   Handlers      │    │  Handlers       │    │  Handlers       │
└────────┬────────┘    └────────┬────────┘    └────────┬────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                          CORE SERVICES                                   │
│  Stream Service │ Event Service │ Source Service │ Insight Service      │
└────────────────────────────────┬────────────────────────────────────────┘
                                 │
         ┌───────────────────────┼───────────────────────┐
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   PostgreSQL    │    │     Redis       │    │   Job Queue     │
│   (Primary)     │    │ (Cache/Pub-Sub) │    │ (Bull/Temporal) │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                                       │
                                 ┌─────────────────────┘
                                 ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                       INTEGRATION ENGINE                                 │
│         OAuth Manager │ Polling Scheduler │ Webhook Receiver            │
│                      Adapter Registry                                    │
└────────────────────────────────┬────────────────────────────────────────┘
                                 │
                                 ▼
┌─────────────────────────────────────────────────────────────────────────┐
│                        EXTERNAL SERVICES                                 │
│  Eight Sleep │ Whoop │ Google Calendar │ Todoist │ GitHub │ ...        │
└─────────────────────────────────────────────────────────────────────────┘
```

## Tech Stack (Recommended)

| Layer | Technology |
|-------|------------|
| **API** | Node.js (Fastify) or Go |
| **Database** | PostgreSQL 15+ with TimescaleDB |
| **Cache** | Redis |
| **Queue** | Bull (Redis) or Temporal |
| **Search** | PostgreSQL FTS (or Elasticsearch for scale) |
| **Auth** | Auth0 or custom JWT |
| **Hosting** | Railway, Render, or AWS |

## Next Steps

1. **MVP Focus**: Start with Health + Calendar + Tasks streams
2. **Core integrations**: Eight Sleep, Google Calendar, Todoist
3. **Basic UI**: Timeline view, stream configuration
4. **AI layer**: Daily summaries, basic insights

## Files in this Directory

```
tributary/docs/
├── README.md                 # This file
├── data-model.md            # Data schemas
├── api-design.md            # API specification
├── integration-architecture.md  # Integration patterns
└── database-schema.md       # PostgreSQL schema
```
