# Tributary UI

🌊 **Your life streams, unified** — A modern dashboard that brings together all aspects of your life into flowing streams, with AI assistance.

## Overview

Tributary transforms how you view and interact with your daily life by organizing information into intuitive "streams" — Work, Personal, Health, Learning, Projects, and Social. These streams flow together into a unified view, giving you a complete picture of your day.

### Key Features

- **🌊 Unified View** — See all your streams combined in one timeline
- **📊 Stream Cards** — Individual views for each life area
- **⚡ AI Assistant** — Trinity helps you navigate and manage your streams
- **🔗 Integrations** — Connect your apps (coming soon)
- **📱 Mobile Responsive** — Works on all devices
- **🌙 Dark Mode** — Easy on the eyes, water feels natural in dark mode

## Design Concept

The "streams" metaphor represents the constant flow of information in modern life. Like tributaries feeding into a river, your various life domains feed into a unified flow that Tributary helps you navigate.

- Central river visualization shows streams flowing together
- Each stream has its own color and identity
- Real-time updates create ripple effects
- AI assistant floats alongside to help

## Tech Stack

- **Frontend**: Vanilla HTML/CSS/JavaScript (no framework dependencies)
- **Backend**: Node.js + Express
- **Real-time**: WebSocket for live updates
- **Auth**: Google OAuth (optional, for production)
- **Deployment**: Railway-ready with Docker

## Local Development

### Prerequisites

- Node.js 18+ 
- npm

### Quick Start

```bash
# Clone/navigate to the directory
cd ~/clawd/tributary/ui

# Install dependencies
npm install

# Start development server
npm start

# Open http://localhost:3000
```

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `PORT` | Server port | 3000 |
| `GOOGLE_CLIENT_ID` | Google OAuth client ID | (disabled) |
| `GOOGLE_CLIENT_SECRET` | Google OAuth secret | (disabled) |
| `GOOGLE_CALLBACK_URL` | OAuth callback URL | /auth/google/callback |
| `SESSION_SECRET` | Session encryption key | tributary-secret-change-me |
| `ALLOWED_EMAILS` | Comma-separated allowed emails | greg.haar@gmail.com,... |

**Note**: If Google OAuth credentials are not set, auth is disabled (dev mode).

## Deployment to Railway

### Option 1: Railway CLI

```bash
# Login to Railway
railway login

# Create new project
railway init

# Deploy
railway up
```

### Option 2: GitHub Integration

1. Push code to GitHub
2. Connect repo in Railway dashboard
3. Railway auto-deploys on push

### Environment Setup in Railway

Add these environment variables in Railway dashboard:

```
NODE_ENV=production
SESSION_SECRET=<generate-secure-secret>
GOOGLE_CLIENT_ID=<your-google-client-id>
GOOGLE_CLIENT_SECRET=<your-google-secret>
GOOGLE_CALLBACK_URL=https://<your-app>.railway.app/auth/google/callback
ALLOWED_EMAILS=your-email@gmail.com
```

## API Endpoints

### State

- `GET /api/state` — Get current application state
- `GET /api/streams` — List all streams
- `GET /api/stream/:id` — Get single stream with items

### Items

- `POST /api/stream/:id/item` — Add item to stream

```json
{
  "title": "Review Q4 reports",
  "type": "task",
  "priority": "high"
}
```

### AI Status

- `POST /api/ai/status` — Update AI status
- `POST /api/ai/message` — Send message to broadcast

### WebSocket

Connect to `/ws` for real-time updates:

```javascript
const ws = new WebSocket('ws://localhost:3000/ws');

ws.onmessage = (e) => {
  const data = JSON.parse(e.data);
  // Handle: init, item_added, ai_status, ai_message, state_update
};
```

## Project Structure

```
tributary/ui/
├── index.html      # Main dashboard (SPA)
├── login.html      # Login page
├── server.js       # Express + WebSocket server
├── package.json    # Dependencies
├── Dockerfile      # Container config
├── railway.toml    # Railway config
└── README.md       # This file
```

## Customization

### Adding New Streams

Edit the `state.streams` array in `server.js`:

```javascript
{ id: 'finance', name: 'Finance', icon: '💰', color: '#84cc16', items: [] }
```

### Stream Colors

Update CSS variables in `index.html`:

```css
:root {
  --stream-finance: #84cc16;
}
```

### Theming

The UI uses CSS custom properties for easy theming. Key variables:

- `--bg-deep` — Deepest background
- `--bg-surface` — Card backgrounds
- `--accent` — Primary accent color
- `--accent-glow` — Glow effects

## Roadmap

- [ ] Backend integration with Clawdbot gateway
- [ ] Calendar integration (Google, Outlook)
- [ ] Task manager integration (Todoist, Things)
- [ ] Email stream (Gmail, Outlook)
- [ ] Notes integration (Obsidian, Notion)
- [ ] Health data (Apple Health, Fitbit)
- [ ] Custom stream creation
- [ ] Stream filtering and search
- [ ] Mobile app (PWA)

## Integration with Trinity

Tributary is designed to work with Trinity (the AI assistant). The `/api/ai/*` endpoints handle communication between the UI and the AI backend.

Future integration will allow Trinity to:
- Add items to streams automatically
- Provide daily summaries
- Suggest task prioritization
- Answer questions about your data

## License

MIT — Part of the Clawdbot project.

---

Built with 💧 by Trinity & Greg
