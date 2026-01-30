# Tributary

> All the streams of your life, flowing into one place.

## Concept

Tributary is a unified life operating system where different "streams" of your life converge:

- **Health Stream** - Sleep, fitness, nutrition, vitals
- **Calendar Stream** - Events, scheduling, time blocks  
- **Tasks Stream** - Todos, projects, deadlines
- **Relationships Stream** - Contacts, interactions, birthdays
- **Finance Stream** - Accounts, transactions, budgets
- **Work Stream** - Projects, meetings, deadlines
- **AI Stream** - Assistant interactions, insights

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      TRIBUTARY UI                           │
│                  (trytributary.com)                        │
└─────────────────────────┬───────────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────────┐
│                    TRIBUTARY API                            │
│              (api.trytributary.com)                        │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐          │
│  │  Auth   │ │ Streams │ │ Events  │ │Webhooks │          │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘          │
└─────────────────────────┬───────────────────────────────────┘
                          │
┌─────────────────────────▼───────────────────────────────────┐
│                     INTEGRATIONS                            │
│  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐   │
│  │Google  │ │Todoist │ │ Apple  │ │ Eight  │ │Clawdbot│   │
│  │Calendar│ │        │ │ Health │ │ Sleep  │ │   AI   │   │
│  └────────┘ └────────┘ └────────┘ └────────┘ └────────┘   │
└─────────────────────────────────────────────────────────────┘
```

## Project Structure

```
tributary/
├── docs/           # Documentation
│   ├── brand-guide.md
│   ├── data-model.md
│   └── api-design.md
├── ui/             # Frontend application
├── backend/        # API server
└── scripts/        # Deployment & utilities
```

## Current Status

- [ ] Brand identity
- [ ] Data model
- [ ] API design
- [ ] UI prototype
- [ ] Infrastructure (trytributary.com)
- [ ] Health stream integration
- [ ] Calendar stream integration
- [ ] Tasks stream integration

## Team

Built with ⚡ by Trinity (AI) and Greg Haar
