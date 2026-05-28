// app.jsx — Main shell: admin bar, sidebar nav, route switcher

const TWEAK_DEFAULTS = {
  theme: 'light',
  density: 'regular',
  showTokenWarning: true,
  simulateErrors: true,
  alreadyPublished: false,
};

const NAV_ITEMS = [
  { id: 'dashboard-wp',  label: 'Dashboard',   icon: 'Dashboard' },
  { id: 'posts',         label: 'Posts',       icon: 'Posts' },
  { id: 'media',         label: 'Media',       icon: 'Media' },
  { id: 'pages',         label: 'Pages',       icon: 'Pages' },
  { id: 'comments',      label: 'Comments',    icon: 'Comments', badge: 4 },
  { id: '_sep1',         sep: true },
  { id: 'social',        label: 'Social Publisher', icon: 'Share', plugin: true,
    sub: [
      { id: 'dashboard', label: 'Overview' },
      { id: 'editor',    label: 'New Post' },
      { id: 'log',       label: 'Activity Log' },
      { id: 'settings',  label: 'Settings' },
    ]
  },
  { id: '_sep2',         sep: true },
  { id: 'plugins',       label: 'Plugins',     icon: 'Plugins' },
  { id: 'users',         label: 'Users',       icon: 'Users' },
  { id: 'tools',         label: 'Tools',       icon: 'Tools' },
  { id: 'settings-wp',   label: 'Settings',    icon: 'Settings' },
];

function AdminBar({ onNav }) {
  return (
    <div className="adminbar">
      <a href="#" className="ab-item" onClick={(e)=>{e.preventDefault(); onNav('dashboard');}}>
        <span className="ab-icon"><Icon.WP size={18} /></span>
      </a>
      <a href="#" className="ab-item" onClick={(e)=>e.preventDefault()}>
        <span className="ab-icon"><Icon.Refresh size={14} /></span>
        TerraSync Cloud
      </a>
      <a href="#" className="ab-item" onClick={(e)=>e.preventDefault()}>
        <span className="ab-icon"><Icon.Comments size={14} /></span>
        <span className="ab-badge">4</span>
      </a>
      <a href="#" className="ab-item" onClick={(e)=>{e.preventDefault(); onNav('editor');}}>
        <span className="ab-icon"><Icon.Plus size={14} /></span>
        New
      </a>
      <div className="ab-spacer"></div>
      <a href="#" className="ab-item" onClick={(e)=>e.preventDefault()}>
        <span className="ab-howdy">Howdy,</span>
        <span style={{marginLeft: 2}}>Maya Park</span>
        <span className="ab-avatar">MP</span>
      </a>
    </div>
  );
}

function Sidebar({ route, onNav }) {
  const socialActive = ['dashboard','editor','log','settings'].includes(route);
  return (
    <div className="sidebar">
      {NAV_ITEMS.map(item => {
        if (item.sep) return <div key={item.id} className="menu-sep"></div>;
        const I = item.icon === 'Share' ? Icon.Share : (Icon[item.icon] || (() => null));
        const isActive = item.plugin && socialActive;
        return (
          <React.Fragment key={item.id}>
            <div className={`menu-item ${isActive ? 'active' : ''}`}
                 onClick={()=>{ if (item.plugin) onNav('dashboard'); }}
                 style={!item.plugin ? {cursor: 'default', opacity: .85} : {}}>
              <span className="menu-icon"><I size={16} /></span>
              <span>{item.label}</span>
              {item.badge && <span className="ab-badge" style={{marginLeft: 'auto'}}>{item.badge}</span>}
            </div>
            {item.sub && isActive && (
              <div className="submenu">
                {item.sub.map(s => (
                  <div key={s.id} className={`sub-item ${route===s.id?'active':''}`} onClick={()=>onNav(s.id)}>
                    {s.label}
                  </div>
                ))}
              </div>
            )}
          </React.Fragment>
        );
      })}
    </div>
  );
}

function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [route, setRoute] = React.useState('dashboard');

  React.useEffect(() => {
    document.documentElement.setAttribute('data-theme', t.theme);
    document.documentElement.setAttribute('data-density', t.density);
  }, [t.theme, t.density]);

  return (
    <React.Fragment>
      <AdminBar onNav={setRoute} />
      <div className="shell">
        <Sidebar route={route} onNav={setRoute} />
        <div className="content" style={route === 'editor' ? {padding: 0, overflow: 'hidden'} : {}}>
          {route === 'dashboard' && <Dashboard tweaks={t} onNav={setRoute} />}
          {route === 'editor'    && <PostEditor tweaks={t} onNav={setRoute} />}
          {route === 'log'       && <ActivityLog tweaks={t} />}
          {route === 'settings'  && <Settings tweaks={t} />}
        </div>
      </div>

      <TweaksPanel>
        <TweakSection label="Plugin states" />
        <TweakToggle label="Token-expiring warning" value={t.showTokenWarning} onChange={(v)=>setTweak('showTokenWarning', v)} />
        <TweakToggle label="Simulated API errors"   value={t.simulateErrors}   onChange={(v)=>setTweak('simulateErrors', v)} />
        <TweakToggle label="Post already cross-posted (editor)" value={t.alreadyPublished} onChange={(v)=>setTweak('alreadyPublished', v)} />

        <TweakSection label="Appearance" />
        <TweakRadio label="Theme"   value={t.theme}   options={['light','dark']}
                    onChange={(v)=>setTweak('theme', v)} />
        <TweakRadio label="Density" value={t.density} options={['compact','regular','comfy']}
                    onChange={(v)=>setTweak('density', v)} />

        <TweakSection label="Jump to screen" />
        <div style={{display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 6}}>
          <button className="button button-small" onClick={()=>setRoute('dashboard')}>Overview</button>
          <button className="button button-small" onClick={()=>setRoute('editor')}>Editor</button>
          <button className="button button-small" onClick={()=>setRoute('log')}>Log</button>
          <button className="button button-small" onClick={()=>setRoute('settings')}>Settings</button>
        </div>
      </TweaksPanel>
    </React.Fragment>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
