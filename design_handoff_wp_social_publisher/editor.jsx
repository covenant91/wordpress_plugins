// editor.jsx — Gutenberg post editor mock with Social Media sidebar

const SAMPLE_POST = {
  title: 'Why edge databases are eating the stack',
  excerpt: "Latency is a feature, not a finish line. Here's why we're betting on edge-resident state for the next decade of application architecture.",
  body: [
    { type: 'p', text: 'For years, "make it faster" meant CDN-cache the marketing site and call it a day. The application — the part that reads and writes user state — sat in a single region, gated by a round-trip that no amount of TLS resumption could hide.' },
    { type: 'image' },
    { type: 'p', text: "That equation broke when interactive products started shipping to global audiences from day one. A Tokyo user shouldn't pay 180ms to mark a todo as done. An edge database flips this: state lives where users are, not where DevOps is comfortable." },
    { type: 'h2', text: 'Three shifts that made it work' },
    { type: 'p', text: 'Conflict-free replicated data types, predictable consensus over WAN, and a generation of developers tired of writing their own caching layers. The runway exists — what we build on it is the interesting part.' },
  ],
};

function PostEditor({ tweaks, onNav }) {
  const [channels, setChannels] = React.useState({
    facebook: true, instagram: true, linkedin: true, twitter: true,
  });
  const [captions, setCaptions] = React.useState({
    facebook:  "New on the blog: why edge databases are eating the stack. State belongs where your users are — not where ops is comfortable.\n\nRead the full piece:",
    instagram: "Latency is a feature, not a finish line.\n\nWhy we're betting on edge-resident state for the next decade of app architecture. Link in bio. ⚡\n\n#edgecomputing #databases #devops #infrastructure #softwareengineering",
    linkedin:  "Three shifts made edge databases viable: CRDTs, predictable WAN consensus, and a generation of developers tired of writing their own caching layers.\n\nA short essay on why the next decade of application architecture is global by default.",
    twitter:   "edge databases are eating the stack.\n\nstate belongs where your users are — not where ops is comfortable. a short essay on the runway, and what we'll build on it ↓",
  });
  const [sidebarTab, setSidebarTab] = React.useState('post'); // post | block
  const [socialOpen, setSocialOpen] = React.useState(true);

  const alreadyPublished = tweaks.alreadyPublished;

  return (
    <div className="fade-in" style={{margin: '-20px -24px -60px', minHeight: 'calc(100vh - 32px)'}}>
      <div className="gb-shell" style={{borderRadius: 0, border: 'none', minHeight: 'calc(100vh - 32px)'}}>

        {/* HEADER */}
        <div className="gb-header">
          <button className="gb-btn wp-logo" onClick={()=>onNav('dashboard')} title="Back to WP Admin">
            <Icon.WP size={28} />
          </button>
          <button className="gb-btn" title="Add block"><Icon.Plus size={20} /></button>
          <button className="gb-btn" title="Tools"><Icon.Heading size={18} /></button>
          <button className="gb-btn" title="Undo"><Icon.Undo size={18} /></button>
          <button className="gb-btn" title="Redo"><Icon.Redo size={18} /></button>
          <div className="gb-divider"></div>
          <button className="gb-btn" title="Document overview"><Icon.ListView size={18} /></button>
          <div className="gb-spacer"></div>
          <button className="gb-save">Saved</button>
          <button className="gb-btn" title="Preview"><Icon.Eye size={18} /></button>
          <button className="gb-publish">Publish</button>
          <button className="gb-btn" title="Settings"><Icon.Settings size={18} /></button>
          <button className="gb-btn" title="Plugins"
                  onClick={()=>setSidebarTab(sidebarTab==='social'?'post':'social')}
                  style={sidebarTab==='social' ? {background:'#1d2327', color:'#fff'} : {}}>
            <Icon.Share size={18} />
          </button>
          <button className="gb-btn"><Icon.Dots size={18} /></button>
        </div>

        {/* CANVAS */}
        <div className="gb-canvas">
          <div className="gb-doc">
            <input className="gb-title" value={SAMPLE_POST.title} readOnly />
            <div className="gb-block"><p style={{color: 'var(--wp-text-2)', fontStyle: 'italic', borderLeft: '4px solid var(--wp-border)', paddingLeft: 14, margin: 0}}>{SAMPLE_POST.excerpt}</p></div>
            {SAMPLE_POST.body.map((b, i) => {
              if (b.type === 'p') return <div key={i} className="gb-block"><p>{b.text}</p></div>;
              if (b.type === 'h2') return <div key={i} className="gb-block"><h2 style={{fontFamily: 'Georgia, serif', fontWeight: 400, fontSize: 24, margin: '24px 0 8px'}}>{b.text}</h2></div>;
              if (b.type === 'image') return (
                <div key={i} className="gb-image" style={{
                  backgroundImage: 'linear-gradient(135deg, rgba(40,55,80,.55), rgba(20,15,50,.85)), radial-gradient(at 20% 30%, #f59e0b 0%, transparent 50%), radial-gradient(at 80% 70%, #ec4899 0%, transparent 50%), radial-gradient(at 50% 50%, #6366f1 0%, transparent 60%)',
                  color: 'rgba(255,255,255,.7)'
                }}>
                  <span style={{display:'inline-flex', alignItems:'center', gap:8, padding: '6px 10px', background:'rgba(0,0,0,.3)', borderRadius:4, fontSize:11, backdropFilter:'blur(4px)'}}>
                    <Icon.Image size={14} /> Featured image · edge-databases-hero.jpg
                  </span>
                </div>
              );
            })}
          </div>
        </div>

        {/* SIDEBAR */}
        <div className="gb-sidebar">
          <div className="gb-sidebar-tabs">
            <button className={`gb-sidebar-tab ${sidebarTab==='post'?'active':''}`} onClick={()=>setSidebarTab('post')}>Post</button>
            <button className={`gb-sidebar-tab ${sidebarTab==='block'?'active':''}`} onClick={()=>setSidebarTab('block')}>Block</button>
            <button className={`gb-sidebar-tab ${sidebarTab==='social'?'active':''}`} onClick={()=>setSidebarTab('social')} style={{flex: 'none', padding: '8px 12px'}}>
              <Icon.Share size={14} />
            </button>
          </div>

          {sidebarTab === 'post' && <PostSettingsPanel />}
          {sidebarTab === 'block' && (
            <div style={{padding: 24, textAlign: 'center', color: 'var(--wp-text-3)', fontSize: 12}}>
              No block selected
            </div>
          )}
          {sidebarTab === 'social' && (
            <SocialMediaPanel
              channels={channels} setChannels={setChannels}
              captions={captions} setCaptions={setCaptions}
              alreadyPublished={alreadyPublished}
              open={socialOpen} setOpen={setSocialOpen}
              tweaks={tweaks}
            />
          )}
        </div>
      </div>
    </div>
  );
}

function PostSettingsPanel() {
  return (
    <div>
      <div className="gb-panel">
        <div className="gb-panel-header"><Icon.ChevDown size={12} /><span className="gb-panel-title">Summary</span></div>
        <div className="gb-panel-body">
          <div style={{display: 'flex', justifyContent: 'space-between', padding: '6px 0', borderBottom: '1px solid var(--wp-border-2)', fontSize: 12}}>
            <span className="muted">Visibility</span><span>Public</span>
          </div>
          <div style={{display: 'flex', justifyContent: 'space-between', padding: '6px 0', borderBottom: '1px solid var(--wp-border-2)', fontSize: 12}}>
            <span className="muted">Publish</span><span>Immediately</span>
          </div>
          <div style={{display: 'flex', justifyContent: 'space-between', padding: '6px 0', borderBottom: '1px solid var(--wp-border-2)', fontSize: 12}}>
            <span className="muted">URL</span><span style={{maxWidth: 130, overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap'}}>terrasync.io/edge-databases</span>
          </div>
          <div style={{display: 'flex', justifyContent: 'space-between', padding: '6px 0', fontSize: 12}}>
            <span className="muted">Template</span><span>Single posts</span>
          </div>
        </div>
      </div>
      <div className="gb-panel">
        <div className="gb-panel-header"><Icon.ChevRight size={12} /><span className="gb-panel-title">Categories</span></div>
      </div>
      <div className="gb-panel">
        <div className="gb-panel-header"><Icon.ChevRight size={12} /><span className="gb-panel-title">Tags</span></div>
      </div>
      <div className="gb-panel">
        <div className="gb-panel-header"><Icon.ChevRight size={12} /><span className="gb-panel-title">Featured image</span></div>
      </div>
      <div className="gb-panel">
        <div className="gb-panel-header"><Icon.ChevRight size={12} /><span className="gb-panel-title">Excerpt</span></div>
      </div>
      <div className="gb-panel">
        <div className="gb-panel-header"><Icon.ChevRight size={12} /><span className="gb-panel-title">Discussion</span></div>
      </div>
    </div>
  );
}

function SocialMediaPanel({ channels, setChannels, captions, setCaptions, alreadyPublished, open, setOpen, tweaks }) {
  const checkedCount = Object.values(channels).filter(Boolean).length;

  return (
    <div>
      <div className="gb-panel" style={{borderBottom: 'none'}}>
        <div className="gb-panel-header" onClick={()=>setOpen(!open)}>
          {open ? <Icon.ChevDown size={12} /> : <Icon.ChevRight size={12} />}
          <span className="gb-panel-title" style={{display:'inline-flex', alignItems:'center', gap:6}}>
            <Icon.Share size={14} />
            Social Media
          </span>
          <span className="muted" style={{fontSize: 11}}>{checkedCount}/4</span>
        </div>
        {open && (
          <div className="gb-panel-body">
            <p style={{margin: '0 0 12px', fontSize: 12, color: 'var(--wp-text-3)'}}>
              Cross-post this article when you hit Publish. Tokens auto-renewed daily.
            </p>

            {PLATFORMS.map(p => (
              <PlatformRow
                key={p.id}
                platform={p}
                checked={channels[p.id]}
                onToggle={(v)=>setChannels({...channels, [p.id]: v})}
                caption={captions[p.id]}
                onCaption={(v)=>setCaptions({...captions, [p.id]: v})}
                sentAlready={alreadyPublished && (p.id === 'facebook' || p.id === 'linkedin')}
                isInstagramNoImage={false}
              />
            ))}

            <div className="sm-summary">
              <div style={{display: 'flex', justifyContent: 'space-between', marginBottom: 4}}>
                <strong>On publish</strong>
                <span>{checkedCount} channel{checkedCount===1?'':'s'}</span>
              </div>
              <div style={{color: 'var(--wp-text-3)'}}>
                Dispatched as async cron jobs · 5s delay · status in <span style={{color: 'var(--wp-link)'}}>Activity Log</span>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

function PlatformRow({ platform, checked, onToggle, caption, onCaption, sentAlready }) {
  const len = caption.length;
  const overLimit = len > platform.limit;
  const warnLimit = !overLimit && len > platform.limit * 0.9;
  const cls = sentAlready ? 'sm-platform sent-already' : 'sm-platform';

  return (
    <div className={cls}>
      <label className="sm-platform-head">
        <input type="checkbox" checked={checked} disabled={sentAlready} onChange={(e)=>onToggle(e.target.checked)} />
        <PlatformIconSolo platform={platform.id} size={14} />
        <span className="sm-platform-name">{platform.name}</span>
        <span className="sm-platform-meta">
          {sentAlready ? 'sent' : checked ? `${platform.limit.toLocaleString()} chars` : ''}
        </span>
      </label>
      {sentAlready && (
        <div style={{padding: '0 10px 10px', fontSize: 11, color: 'var(--wp-text-3)', display: 'flex', alignItems: 'center', gap: 6}}>
          <Icon.Check size={12} /> Already published · 2h ago
        </div>
      )}
      {checked && !sentAlready && (
        <div className="sm-platform-body">
          <textarea
            value={caption}
            onChange={(e)=>onCaption(e.target.value)}
            placeholder={`Custom caption for ${platform.name}…`}
            rows={platform.id === 'twitter' ? 3 : 4}
          />
          <div className="sm-charcounter">
            <span>{platform.id === 'twitter' ? 'Post URL auto-appended' : 'Custom caption'}</span>
            <span className={overLimit ? 'over' : warnLimit ? 'warn' : ''}>
              {len.toLocaleString()} / {platform.limit.toLocaleString()}
              {overLimit && <> · −{(len - platform.limit).toLocaleString()}</>}
            </span>
          </div>
        </div>
      )}
    </div>
  );
}

Object.assign(window, { PostEditor });
