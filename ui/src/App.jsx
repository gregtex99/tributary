import React, { useState, useEffect, useMemo, useRef } from 'react';
import { 
  Waves, 
  Activity, 
  Calendar, 
  CheckCircle2, 
  Users, 
  Wallet, 
  Zap, 
  Search, 
  Plus, 
  Moon, 
  Sun, 
  ChevronRight, 
  MoreHorizontal,
  ArrowUpRight,
  Droplets,
  Wind,
  Compass,
  MessageSquareText,
  Settings,
  Bell
} from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

// --- Constants & Brand Data ---
const STREAMS = [
  { id: 'health', name: 'Health', color: '#4A9B7F', icon: Activity, metric: '82%', status: 'Flowing', detail: '8,234 steps' },
  { id: 'calendar', name: 'Time', color: '#5B8FB9', icon: Calendar, metric: '3 Events', status: 'Clear', detail: 'Next: Sync at 2pm' },
  { id: 'tasks', name: 'Tasks', color: '#6B7C8F', icon: CheckCircle2, metric: '12 Items', status: 'Pooling', detail: '3 high priority' },
  { id: 'relationships', name: 'People', color: '#D4956A', icon: Users, metric: '2 Ripples', status: 'Flowing', detail: 'Call Mom' },
  { id: 'finance', name: 'Money', color: '#3D5A6C', icon: Wallet, metric: '$1,240', status: 'Settled', detail: 'Under budget' },
];

const MOCK_EVENTS = [
  { time: '08:00', stream: 'health', title: 'Morning Run', type: 'workout', impact: 'High' },
  { time: '10:00', stream: 'calendar', title: 'Product Strategy', type: 'meeting', impact: 'High' },
  { time: '11:30', stream: 'tasks', title: 'Design Review', type: 'task', impact: 'Med' },
  { time: '13:00', stream: 'finance', title: 'Rent Payment', type: 'bill', impact: 'Med' },
  { time: '15:00', stream: 'relationships', title: 'Coffee with Sarah', type: 'interaction', impact: 'High' },
  { time: '17:00', stream: 'tasks', title: 'Update Tributary Docs', type: 'task', impact: 'Low' },
];

// --- Sub-Components ---

/**
 * Background Particles Component
 * Creates a drifting, bubble-like effect on a canvas.
 */
const WaterParticles = ({ isDarkMode }) => {
  const canvasRef = useRef(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    const ctx = canvas.getContext('2d');
    let animationFrameId;

    let particles = [];
    const count = 40;

    const resize = () => {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    };

    class Particle {
      constructor() {
        this.init();
      }
      init() {
        this.x = Math.random() * canvas.width;
        this.y = Math.random() * canvas.height;
        this.size = Math.random() * 4 + 1;
        this.speedY = Math.random() * 0.5 + 0.1;
        this.speedX = (Math.random() - 0.5) * 0.2;
        this.opacity = Math.random() * 0.5;
      }
      update() {
        this.y -= this.speedY;
        this.x += this.speedX;
        if (this.y < -10) this.init();
      }
      draw() {
        ctx.beginPath();
        ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
        ctx.fillStyle = isDarkMode 
          ? `rgba(255, 255, 255, ${this.opacity})` 
          : `rgba(46, 139, 139, ${this.opacity})`;
        ctx.fill();
      }
    }

    const init = () => {
      resize();
      particles = Array.from({ length: count }, () => new Particle());
    };

    const animate = () => {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      particles.forEach(p => {
        p.update();
        p.draw();
      });
      animationFrameId = requestAnimationFrame(animate);
    };

    window.addEventListener('resize', resize);
    init();
    animate();

    return () => {
      cancelAnimationFrame(animationFrameId);
      window.removeEventListener('resize', resize);
    };
  }, [isDarkMode]);

  return <canvas ref={canvasRef} className="fixed inset-0 pointer-events-none z-0 opacity-40" />;
};

const GlassCard = ({ children, className = "", onClick }) => (
  <motion.div
    whileHover={{ y: -2 }}
    onClick={onClick}
    className={`backdrop-blur-xl bg-white/10 dark:bg-slate-900/40 border border-white/20 dark:border-white/5 rounded-3xl overflow-hidden shadow-xl ${className}`}
  >
    {children}
  </motion.div>
);

const StreamIndicator = ({ color, size = "md" }) => (
  <div className={`relative ${size === 'md' ? 'w-10 h-10' : 'w-8 h-8'}`}>
    <motion.div 
      animate={{ scale: [1, 1.2, 1], opacity: [0.3, 0.6, 0.3] }}
      transition={{ repeat: Infinity, duration: 3 }}
      className="absolute inset-0 rounded-full"
      style={{ backgroundColor: color }}
    />
    <div className="absolute inset-1.5 rounded-full border-2 border-white/30" style={{ backgroundColor: color }} />
  </div>
);

// --- Main Application ---

export default function App() {
  const [isDarkMode, setIsDarkMode] = useState(true);
  const [activeTab, setActiveTab] = useState('confluence');
  const [searchQuery, setSearchQuery] = useState('');

  // Toggle theme
  useEffect(() => {
    if (isDarkMode) document.documentElement.classList.add('dark');
    else document.documentElement.classList.remove('dark');
  }, [isDarkMode]);

  const navItems = [
    { id: 'confluence', icon: Waves, label: 'Confluence' },
    { id: 'current', icon: Wind, label: 'The Current' },
    { id: 'delta', icon: Droplets, label: 'The Delta' },
    { id: 'sources', icon: Compass, label: 'Sources' },
  ];

  return (
    <div className={`min-h-screen transition-colors duration-700 font-sans selection:bg-teal-500/30
      ${isDarkMode ? 'bg-[#0D1B2A] text-slate-100' : 'bg-[#E8F4F8] text-slate-900'}`}>
      
      <WaterParticles isDarkMode={isDarkMode} />

      {/* --- Header / Navigation --- */}
      <nav className="fixed top-0 left-0 right-0 z-50 p-4 md:p-6 flex justify-between items-center">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 bg-gradient-to-br from-teal-400 to-blue-600 rounded-xl flex items-center justify-center shadow-lg shadow-teal-500/20">
            <Waves className="text-white w-6 h-6" />
          </div>
          <span className="text-xl font-bold tracking-tight hidden sm:block">Tributary</span>
        </div>

        <div className="backdrop-blur-2xl bg-white/20 dark:bg-white/5 border border-white/20 dark:border-white/10 p-1.5 rounded-2xl flex gap-1">
          {navItems.map((item) => (
            <button
              key={item.id}
              onClick={() => setActiveTab(item.id)}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl transition-all duration-300 ${
                activeTab === item.id 
                ? 'bg-white dark:bg-white/10 shadow-sm text-teal-600 dark:text-teal-400 font-medium' 
                : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
              }`}
            >
              <item.icon size={18} />
              <span className="hidden md:block text-sm">{item.label}</span>
            </button>
          ))}
        </div>

        <div className="flex items-center gap-3">
          <button 
            onClick={() => setIsDarkMode(!isDarkMode)}
            className="w-10 h-10 rounded-full flex items-center justify-center bg-white/20 dark:bg-white/5 hover:bg-white/30 transition-colors border border-white/20"
          >
            {isDarkMode ? <Sun size={18} /> : <Moon size={18} />}
          </button>
          <div className="w-10 h-10 rounded-full bg-slate-400 border-2 border-teal-500 overflow-hidden shadow-lg">
            <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Felix" alt="Profile" />
          </div>
        </div>
      </nav>

      {/* --- Main Content Area --- */}
      <main className="relative z-10 pt-28 pb-32 px-4 max-w-7xl mx-auto">
        
        {/* Search Bar */}
        <div className="mb-10 max-w-2xl mx-auto">
          <div className="relative group">
            <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-teal-500 transition-colors" size={20} />
            <input 
              type="text" 
              placeholder="Dive into your streams..."
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="w-full pl-12 pr-4 py-4 rounded-3xl backdrop-blur-xl bg-white/30 dark:bg-white/5 border border-white/20 dark:border-white/10 focus:outline-none focus:ring-2 focus:ring-teal-500/50 transition-all text-lg"
            />
          </div>
        </div>

        <AnimatePresence mode="wait">
          {activeTab === 'confluence' && (
            <motion.div 
              key="confluence"
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -20 }}
              className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
            >
              {/* Header Card */}
              <GlassCard className="lg:col-span-2 p-8 flex flex-col justify-between min-h-[300px] bg-gradient-to-br from-teal-500/10 to-transparent">
                <div>
                  <h2 className="text-4xl font-light mb-2">Good afternoon, <span className="font-bold">Felix</span></h2>
                  <p className="text-slate-500 dark:text-slate-400 max-w-md">Your life is flowing beautifully today. Health is optimal and the Current is clear for the next 2 hours.</p>
                </div>
                <div className="flex gap-10 mt-8 overflow-x-auto pb-4 no-scrollbar">
                  <div className="flex flex-col">
                    <span className="text-xs uppercase tracking-widest text-slate-400 mb-1">Flow Score</span>
                    <span className="text-3xl font-bold text-teal-500">92</span>
                  </div>
                  <div className="flex flex-col">
                    <span className="text-xs uppercase tracking-widest text-slate-400 mb-1">Active Streams</span>
                    <span className="text-3xl font-bold">5</span>
                  </div>
                  <div className="flex flex-col">
                    <span className="text-xs uppercase tracking-widest text-slate-400 mb-1">Items To Basin</span>
                    <span className="text-3xl font-bold">14</span>
                  </div>
                </div>
              </GlassCard>

              {/* Quick Drop Card */}
              <GlassCard className="p-8 flex flex-col justify-center items-center text-center border-dashed border-2 border-teal-500/30">
                <div className="w-16 h-16 rounded-full bg-teal-500 flex items-center justify-center text-white mb-4 shadow-xl shadow-teal-500/30">
                  <Plus size={32} />
                </div>
                <h3 className="text-xl font-bold mb-2">New Drop</h3>
                <p className="text-slate-500 text-sm mb-6">Capture a task, event, or thought instantly to the river.</p>
                <div className="flex gap-2 w-full">
                  <button className="flex-1 py-2 rounded-xl bg-white/5 border border-white/10 text-xs hover:bg-white/10 transition-colors">Task</button>
                  <button className="flex-1 py-2 rounded-xl bg-white/5 border border-white/10 text-xs hover:bg-white/10 transition-colors">Event</button>
                  <button className="flex-1 py-2 rounded-xl bg-white/5 border border-white/10 text-xs hover:bg-white/10 transition-colors">Note</button>
                </div>
              </GlassCard>

              {/* Individual Stream Cards */}
              {STREAMS.map((stream) => (
                <GlassCard key={stream.id} className="group cursor-pointer">
                  <div className="p-6">
                    <div className="flex justify-between items-start mb-6">
                      <div className="flex items-center gap-4">
                        <div className="p-3 rounded-2xl bg-white/5 border border-white/10 text-slate-300 group-hover:scale-110 transition-transform" style={{ color: stream.color }}>
                          <stream.icon size={24} />
                        </div>
                        <div>
                          <h4 className="font-bold text-lg">{stream.name}</h4>
                          <span className="text-xs text-slate-500 uppercase tracking-tighter">{stream.status}</span>
                        </div>
                      </div>
                      <StreamIndicator color={stream.color} />
                    </div>
                    
                    <div className="space-y-4">
                      <div className="flex justify-between items-end">
                        <span className="text-3xl font-bold tracking-tight">{stream.metric}</span>
                        <span className="text-sm text-slate-400">{stream.detail}</span>
                      </div>
                      <div className="h-1.5 w-full bg-slate-200 dark:bg-slate-800 rounded-full overflow-hidden">
                        <motion.div 
                          initial={{ width: 0 }}
                          animate={{ width: stream.metric.includes('%') ? stream.metric : '60%' }}
                          className="h-full rounded-full"
                          style={{ backgroundColor: stream.color }}
                        />
                      </div>
                    </div>
                  </div>
                  <div className="px-6 py-4 bg-white/5 dark:bg-black/10 flex justify-between items-center opacity-0 group-hover:opacity-100 transition-opacity">
                    <span className="text-xs font-medium">Dive Deeper</span>
                    <ArrowUpRight size={14} className="text-teal-500" />
                  </div>
                </GlassCard>
              ))}
            </motion.div>
          )}

          {activeTab === 'current' && (
            <motion.div 
              key="current"
              initial={{ opacity: 0, x: 20 }}
              animate={{ opacity: 1, x: 0 }}
              exit={{ opacity: 0, x: -20 }}
              className="max-w-4xl mx-auto"
            >
              <div className="flex justify-between items-center mb-8">
                <div>
                  <h2 className="text-3xl font-bold mb-1">The Current</h2>
                  <p className="text-slate-500">Friday, Jan 30 — Staying in flow</p>
                </div>
                <div className="flex gap-2">
                   <button className="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-sm">Today</button>
                   <button className="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-sm">Focus Mode</button>
                </div>
              </div>

              <div className="relative pl-8 border-l border-white/10 space-y-8">
                {/* Now Indicator */}
                <div className="absolute left-[-5px] top-20 w-[10px] h-[10px] bg-teal-500 rounded-full shadow-[0_0_15px_rgba(20,184,166,0.5)] z-20" />
                
                {MOCK_EVENTS.map((event, i) => {
                  const stream = STREAMS.find(s => s.id === event.stream);
                  return (
                    <GlassCard key={i} className="relative">
                      <div className="p-6 flex flex-col sm:flex-row gap-6 items-start sm:items-center">
                        <div className="text-lg font-mono text-slate-400 min-w-[70px]">{event.time}</div>
                        
                        <div className="flex-1 flex items-center gap-4">
                          <div className="p-2 rounded-lg" style={{ backgroundColor: `${stream.color}20`, color: stream.color }}>
                            <stream.icon size={20} />
                          </div>
                          <div>
                            <h4 className="font-bold">{event.title}</h4>
                            <span className="text-xs text-slate-500 capitalize">{event.type}</span>
                          </div>
                        </div>

                        <div className="flex items-center gap-4 w-full sm:w-auto justify-between sm:justify-end">
                           <div className="px-3 py-1 rounded-full bg-white/5 border border-white/10 text-[10px] uppercase tracking-widest text-slate-400">
                             Impact: {event.impact}
                           </div>
                           <button className="p-2 rounded-xl hover:bg-white/10 transition-colors">
                             <MoreHorizontal size={18} />
                           </button>
                        </div>
                      </div>
                    </GlassCard>
                  );
                })}
              </div>
            </motion.div>
          )}

          {activeTab === 'sources' && (
             <motion.div 
               key="sources"
               initial={{ opacity: 0, scale: 0.95 }}
               animate={{ opacity: 1, scale: 1 }}
               className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
             >
                <GlassCard className="p-6 flex flex-col items-center text-center">
                   <div className="w-12 h-12 bg-blue-500/20 rounded-2xl flex items-center justify-center mb-4">
                      <Calendar className="text-blue-500" />
                   </div>
                   <h5 className="font-bold">Google Calendar</h5>
                   <p className="text-xs text-slate-500 mb-4">Time Stream Source</p>
                   <div className="px-3 py-1 rounded-full bg-green-500/10 text-green-500 text-[10px] font-bold">CONNECTED</div>
                </GlassCard>
                <GlassCard className="p-6 flex flex-col items-center text-center border-dashed border-white/10">
                   <div className="w-12 h-12 bg-white/5 rounded-2xl flex items-center justify-center mb-4">
                      <Plus className="text-slate-400" />
                   </div>
                   <h5 className="font-bold">Add Source</h5>
                   <p className="text-xs text-slate-500 mb-4">New data origin</p>
                   <button className="text-xs font-bold text-teal-500">BROWSE ALL</button>
                </GlassCard>
             </motion.div>
          )}
        </AnimatePresence>

      </main>

      {/* --- The Guide (Floating AI) --- */}
      <motion.div 
        drag
        dragConstraints={{ left: -100, right: 100, top: -100, bottom: 100 }}
        className="fixed bottom-8 right-8 z-[100]"
      >
        <GlassCard className="w-16 h-16 sm:w-auto sm:h-auto sm:p-4 flex items-center gap-3 cursor-grab active:cursor-grabbing hover:bg-teal-500/20 group transition-all">
          <div className="relative">
             <motion.div 
               animate={{ scale: [1, 1.2, 1] }}
               transition={{ repeat: Infinity, duration: 4 }}
               className="absolute inset-0 bg-teal-400/30 rounded-full blur-lg"
             />
             <div className="w-10 h-10 bg-teal-500 rounded-full flex items-center justify-center text-white shadow-lg">
               <Zap size={20} className="fill-current" />
             </div>
          </div>
          <div className="hidden sm:block">
            <span className="block text-xs font-bold text-teal-500 uppercase tracking-tighter">The Guide</span>
            <span className="block text-[10px] text-slate-500 whitespace-nowrap">"Drink water before 3pm."</span>
          </div>
          <div className="hidden sm:flex ml-4 p-1.5 rounded-lg bg-white/10">
            <MessageSquareText size={14} className="text-slate-400" />
          </div>
        </GlassCard>
      </motion.div>

      {/* --- Footer / Quick Access --- */}
      <footer className="fixed bottom-0 left-0 right-0 z-40 p-6 pointer-events-none">
        <div className="max-w-7xl mx-auto flex justify-between items-end">
           <div className="pointer-events-auto">
              <GlassCard className="px-4 py-2 flex items-center gap-4 text-xs font-medium">
                <span className="flex items-center gap-2"><div className="w-2 h-2 rounded-full bg-green-500" /> System: Fluid</span>
                <span className="text-white/20">|</span>
                <span className="text-slate-400">Syncing 3 sources</span>
              </GlassCard>
           </div>

           <div className="flex gap-2 pointer-events-auto">
             <button className="w-12 h-12 rounded-2xl backdrop-blur-xl bg-white/10 border border-white/20 flex items-center justify-center text-slate-400 hover:text-white hover:bg-white/20 transition-all">
               <Bell size={20} />
             </button>
             <button className="w-12 h-12 rounded-2xl backdrop-blur-xl bg-white/10 border border-white/20 flex items-center justify-center text-slate-400 hover:text-white hover:bg-white/20 transition-all">
               <Settings size={20} />
             </button>
           </div>
        </div>
      </footer>
    </div>
  );
}
