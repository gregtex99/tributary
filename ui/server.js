const express = require('express');
const session = require('express-session');
const passport = require('passport');
const GoogleStrategy = require('passport-google-oauth20').Strategy;
const cookieParser = require('cookie-parser');
const http = require('http');
const path = require('path');
const { WebSocketServer } = require('ws');

const app = express();
const PORT = process.env.PORT || 3000;

// Allowed Google accounts
const ALLOWED_EMAILS = (process.env.ALLOWED_EMAILS || 'greg.haar@gmail.com,greg@absoluteio.com,greg@textickets.com').split(',');

// App state
let state = {
  streams: [
    { id: 'work', name: 'Work', icon: '💼', color: '#3b82f6', items: [] },
    { id: 'personal', name: 'Personal', icon: '🏠', color: '#10b981', items: [] },
    { id: 'health', name: 'Health', icon: '💪', color: '#ef4444', items: [] },
    { id: 'learning', name: 'Learning', icon: '📚', color: '#f59e0b', items: [] },
    { id: 'projects', name: 'Projects', icon: '🚀', color: '#8b5cf6', items: [] },
    { id: 'social', name: 'Social', icon: '👥', color: '#ec4899', items: [] }
  ],
  unifiedItems: [],
  aiStatus: { connected: false, thinking: false, lastActivity: null },
  user: null,
  stats: {
    uptime: Date.now(),
    itemsProcessed: 0
  }
};

// Connected WebSocket clients
const clients = new Set();

// Broadcast to all clients
function broadcast(data) {
  const message = JSON.stringify(data);
  clients.forEach(client => {
    if (client.readyState === 1) {
      client.send(message);
    }
  });
}

// Middleware
app.use(cookieParser());
app.use(express.json());
app.use(session({
  secret: process.env.SESSION_SECRET || 'tributary-secret-change-me',
  resave: false,
  saveUninitialized: false,
  cookie: { 
    secure: process.env.NODE_ENV === 'production',
    maxAge: 7 * 24 * 60 * 60 * 1000
  }
}));

// Passport setup
passport.serializeUser((user, done) => done(null, user));
passport.deserializeUser((obj, done) => done(null, obj));

if (process.env.GOOGLE_CLIENT_ID && process.env.GOOGLE_CLIENT_SECRET) {
  passport.use(new GoogleStrategy({
    clientID: process.env.GOOGLE_CLIENT_ID,
    clientSecret: process.env.GOOGLE_CLIENT_SECRET,
    callbackURL: process.env.GOOGLE_CALLBACK_URL || '/auth/google/callback'
  }, (accessToken, refreshToken, profile, done) => {
    const email = profile.emails?.[0]?.value;
    if (ALLOWED_EMAILS.includes(email)) {
      return done(null, { id: profile.id, email, name: profile.displayName, avatar: profile.photos?.[0]?.value });
    }
    return done(null, false, { message: 'Unauthorized email' });
  }));
}

app.use(passport.initialize());
app.use(passport.session());

// Auth middleware
function requireAuth(req, res, next) {
  if (!process.env.GOOGLE_CLIENT_ID) return next();
  if (req.path === '/health') return next();
  if (req.path.startsWith('/webhooks/')) return next();
  if (req.isAuthenticated()) return next();
  if (req.path.startsWith('/api/')) return res.status(401).json({ error: 'Unauthorized' });
  res.redirect('/auth/google');
}

// CORS
app.use((req, res, next) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  if (req.method === 'OPTIONS') return res.sendStatus(200);
  next();
});

// Health check
app.get('/health', (req, res) => {
  res.json({ status: 'healthy', timestamp: new Date().toISOString() });
});

// Auth routes
app.get('/auth/google', passport.authenticate('google', { scope: ['profile', 'email'] }));
app.get('/auth/google/callback',
  passport.authenticate('google', { failureRedirect: '/auth/failed' }),
  (req, res) => res.redirect('/')
);
app.get('/auth/failed', (req, res) => res.status(403).send('Authentication failed.'));
app.get('/auth/logout', (req, res) => req.logout(() => res.redirect('/')));
app.get('/auth/user', requireAuth, (req, res) => res.json(req.user || null));

// API endpoints
app.get('/api/state', requireAuth, (req, res) => res.json(state));

app.get('/api/streams', requireAuth, (req, res) => res.json(state.streams));

app.get('/api/stream/:id', requireAuth, (req, res) => {
  const stream = state.streams.find(s => s.id === req.params.id);
  if (!stream) return res.status(404).json({ error: 'Stream not found' });
  res.json(stream);
});

app.post('/api/stream/:id/item', requireAuth, (req, res) => {
  const stream = state.streams.find(s => s.id === req.params.id);
  if (!stream) return res.status(404).json({ error: 'Stream not found' });
  
  const item = {
    id: Date.now().toString(),
    ...req.body,
    streamId: stream.id,
    createdAt: new Date().toISOString()
  };
  
  stream.items.unshift(item);
  stream.items = stream.items.slice(0, 100);
  
  // Add to unified view
  state.unifiedItems.unshift({ ...item, streamName: stream.name, streamColor: stream.color, streamIcon: stream.icon });
  state.unifiedItems = state.unifiedItems.slice(0, 50);
  
  state.stats.itemsProcessed++;
  
  broadcast({ type: 'item_added', item, streamId: stream.id });
  res.json({ success: true, item });
});

app.post('/api/ai/status', requireAuth, (req, res) => {
  state.aiStatus = { ...state.aiStatus, ...req.body };
  broadcast({ type: 'ai_status', status: state.aiStatus });
  res.json({ success: true, status: state.aiStatus });
});

app.post('/api/ai/message', requireAuth, (req, res) => {
  const { message, fromAI } = req.body;
  broadcast({ type: 'ai_message', message, fromAI, timestamp: Date.now() });
  res.json({ success: true });
});

// Demo endpoint to add sample items
app.post('/api/demo/populate', requireAuth, (req, res) => {
  const sampleItems = [
    { streamId: 'work', title: 'Review Q4 reports', type: 'task', priority: 'high' },
    { streamId: 'work', title: 'Team standup at 10am', type: 'event', time: '10:00 AM' },
    { streamId: 'personal', title: 'Call mom', type: 'reminder', priority: 'medium' },
    { streamId: 'health', title: 'Morning run completed', type: 'achievement', value: '5.2 km' },
    { streamId: 'learning', title: 'Read chapter 5', type: 'task', book: 'Designing Data-Intensive Applications' },
    { streamId: 'projects', title: 'Tributary UI prototype', type: 'milestone', status: 'in-progress' },
    { streamId: 'social', title: 'Dinner with Alex', type: 'event', time: '7:00 PM' }
  ];
  
  sampleItems.forEach(item => {
    const stream = state.streams.find(s => s.id === item.streamId);
    if (stream) {
      const fullItem = {
        id: Date.now().toString() + Math.random().toString(36).substr(2, 9),
        ...item,
        createdAt: new Date().toISOString()
      };
      stream.items.unshift(fullItem);
      state.unifiedItems.unshift({ 
        ...fullItem, 
        streamName: stream.name, 
        streamColor: stream.color,
        streamIcon: stream.icon
      });
    }
  });
  
  broadcast({ type: 'state_update', state });
  res.json({ success: true, message: 'Demo data populated' });
});

// Webhook endpoints
app.post('/webhooks/:source', (req, res) => {
  console.log(`📥 Webhook: ${req.params.source}`, JSON.stringify(req.body).slice(0, 200));
  broadcast({ type: 'webhook', source: req.params.source, data: req.body });
  res.json({ received: true });
});

// Static files
app.use(requireAuth, express.static(__dirname));
app.get('*', requireAuth, (req, res) => res.sendFile(path.join(__dirname, 'index.html')));

// Create server
const server = http.createServer(app);

// WebSocket
const wss = new WebSocketServer({ server, path: '/ws' });

wss.on('connection', (ws) => {
  clients.add(ws);
  console.log(`🌊 WebSocket connected (${clients.size} clients)`);
  ws.send(JSON.stringify({ type: 'init', state }));
  
  ws.on('close', () => {
    clients.delete(ws);
    console.log(`🌊 WebSocket disconnected (${clients.size} clients)`);
  });
  
  ws.on('message', (data) => {
    try {
      const msg = JSON.parse(data);
      if (msg.type === 'ping') ws.send(JSON.stringify({ type: 'pong' }));
    } catch (e) {
      console.error('Invalid message:', data.toString());
    }
  });
});

server.listen(PORT, () => {
  console.log(`🌊 Tributary running on port ${PORT}`);
  console.log(`   Auth: ${process.env.GOOGLE_CLIENT_ID ? 'Google OAuth' : 'Disabled (dev)'}`);
});
