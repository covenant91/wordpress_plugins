// log.jsx — Activity Log page with filters

const LOG_DATA = (simulateErrors) => [
  { id: 1024, post: 'Why edge databases are eating the stack', platform: 'linkedin',  status: 'sent',    social_id: 'urn:li:share:7203...8421', caption: 'Three shifts made edge databases viable: CRDTs, predictable WAN consensus...', date: '2026-05-28 14:32:11' },
  { id: 1023, post: 'Why edge databases are eating the stack', platform: 'twitter',   status: 'sent',    social_id: '1795231047825...', caption: 'edge databases are eating the stack. state belongs where your users are...', date: '2026-05-28 14:32:09' },
  { id: 1022, post: 'Why edge databases are eating the stack', platform: 'facebook',  status: 'sent',    social_id: '102934857601_984...', caption: 'New on the blog: why edge databases are eating the stack...', date: '2026-05-28 14:32:08' },
  { id: 1021, post: 'Why edge databases are eating the stack', platform: 'instagram', status: 'skipped', social_id: null, caption: null, error: 'No featured image found', date: '2026-05-28 14:32:05' },
  { id: 1020, post: 'Release notes — v4.2 (multi-region replicas)', platform: 'twitter', status: simulateErrors ? 'failed' : 'sent', social_id: simulateErrors ? null : '1795198321...', caption: 'v4.2 ships multi-region replicas with sub-50ms convergence...', error: simulateErrors ? 'OAuth signature did not validate (HTTP 401)' : null, date: '2026-05-28 13:08:42' },
  { id: 1019, post: 'Release notes — v4.2 (multi-region replicas)', platform: 'linkedin',  status: 'sent', social_id: 'urn:li:share:7203...0014', caption: "We're shipping multi-region replicas in 4.2...", date: '2026-05-28 13:08:39' },
  { id: 1018, post: 'Release notes — v4.2 (multi-region replicas)', platform: 'facebook',  status: 'sent', social_id: '102934857601_983...', caption: 'v4.2 is live. Multi-region replicas, sub-50ms convergence...', date: '2026-05-28 13:08:36' },
  { id: 1017, post: 'A field guide to vector indexes', platform: 'instagram', status: 'sent', social_id: '18028357201...', caption: 'A field guide to vector indexes — what HNSW, IVF and PQ actually mean...', date: '2026-05-28 10:14:02' },
  { id: 1016, post: 'A field guide to vector indexes', platform: 'facebook',  status: 'sent', social_id: '102934857601_982...', caption: 'A field guide to vector indexes...', date: '2026-05-28 10:13:58' },
  { id: 1015, post: 'A field guide to vector indexes', platform: 'linkedin',  status: 'sent', social_id: 'urn:li:share:7203...9912', caption: 'HNSW vs IVF vs PQ — practical tradeoffs we learned the hard way...', date: '2026-05-28 10:13:55' },
  { id: 1014, post: 'A field guide to vector indexes', platform: 'twitter',   status: 'sent', social_id: '1795094210...', caption: 'a field guide to vector indexes ↓', date: '2026-05-28 10:13:50' },
  { id: 1013, post: 'Customer story: how Halberd cut p99 latency by 64%', platform: 'twitter', status: simulateErrors ? 'failed' : 'pending', social_id: null, caption: 'how halberd cut p99 latency by 64% using regional replicas...', error: simulateErrors ? 'Rate limit exceeded (HTTP 429)' : null, date: '2026-05-27 18:42:00' },
  { id: 1012, post: 'Customer story: how Halberd cut p99 latency by 64%', platform: 'linkedin', status: 'sent', social_id: 'urn:li:share:7203...4421', caption: 'Halberd Logistics cut p99 latency by 64% by moving stateful workloads to regional replicas...', date: '2026-05-27 18:41:55' },
  { id: 1011, post: 'Customer story: how Halberd cut p99 latency by 64%', platform: 'facebook', status: 'sent', social_id: '102934857601_981...', caption: 'How Halberd Logistics cut p99 latency by 64%...', date: '2026-05-27 18:41:52' },
  { id: 1010, post: 'Postgres at 100K writes/sec — what actually breaks', platform: 'linkedin', status: 'sent', social_id: 'urn:li:share:7203...1102', caption: "We pushed a single Postgres node to 100k writes/sec. Here's what gave out first...", date: '2026-05-26 09:21:14' },
  { id: 1009, post: 'Postgres at 100K writes/sec — what actually breaks', platform: 'twitter',  status: 'sent', social_id: '1794681224...', caption: '100k writes/sec on a single postgres node. what breaks, in order:', date: '2026-05-26 09:21:08' },
  { id: 1008, post: 'Postgres at 100K writes/sec — what actually breaks', platform: 'instagram', status: 'skipped', social_id: null, caption: null, error: 'No featured image found', date: '2026-05-26 09:21:00' },
];

function ActivityLog({ tweaks }) {
  const [filterPlatform, setFilterPlatform] = React.useState('all');
  const [filterStatus, setFilterStatus]     = React.useState('all');
  const [search, setSearch]                  = React.useState('');
  const [selected, setSelected]              = React.useState(new Set());

  const rows = LOG_DATA(tweaks.simulateErrors);

  const filtered = rows.filter(r =>
    (filterPlatform === 'all' || r.platform === filterPlatform) &&
    (filterStatus === 'all'   || r.status === filterStatus) &&
    (!search || r.post.toLowerCase().includes(search.toLowerCase()) || (r.caption||'').toLowerCase().includes(search.toLowerCase()))
  );

  const allSelected = filtered.length > 0 && filtered.every(r => selected.has(r.id));

  return (
    <div className="fade-in">
      <h1 className="wp-heading">
        Activity Log
        <button className="page-title-action">Export CSV</button>
      </h1>
      <div className="wp-subhead">Every cross-post attempt, with API response. Auto-purges entries older than 90 days.</div>

      {tweaks.simulateErrors && (
        <div className="notice error">
          <div className="notice-icon"><Icon.Alert size={18} /></div>
          <div className="notice-body">
            <strong>2 publishes failed in the last hour.</strong>{' '}
            X API returned 401 (OAuth signature) on the v4.2 release post. <a href="#">Re-test your X credentials</a> or click <strong>Retry</strong> below.
          </div>
        </div>
      )}

      <div className="postbox">
        <div className="postbox-body" style={{padding: 0}}>
          <div className="tablenav" style={{padding: '12px 16px', borderBottom: '1px solid var(--wp-border-2)'}}>
            <div className="alignleft">
              <select value={filterPlatform} onChange={(e)=>setFilterPlatform(e.target.value)}>
                <option value="all">All platforms</option>
                {PLATFORMS.map(p => <option key={p.id} value={p.id}>{p.name}</option>)}
              </select>
              <select value={filterStatus} onChange={(e)=>setFilterStatus(e.target.value)}>
                <option value="all">All statuses</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
                <option value="pending">Pending</option>
                <option value="skipped">Skipped</option>
              </select>
              <select defaultValue="30">
                <option value="1">Last 24 hours</option>
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
              </select>
              <div style={{position: 'relative'}}>
                <Icon.Search size={14} />
                <input
                  type="text"
                  placeholder="Search posts or captions…"
                  value={search}
                  onChange={(e)=>setSearch(e.target.value)}
                  style={{paddingLeft: 28, width: 240}}
                />
                <span style={{position: 'absolute', left: 8, top: '50%', transform: 'translateY(-50%)', color: 'var(--wp-text-3)', pointerEvents: 'none'}}>
                  <Icon.Search size={14} />
                </span>
              </div>
            </div>
            <div className="alignright">
              <span className="displaying-num">{filtered.length} item{filtered.length===1?'':'s'}</span>
            </div>
          </div>

          {selected.size > 0 && (
            <div style={{padding: '10px 16px', background: '#f0f6fc', borderBottom: '1px solid var(--wp-border-2)', display: 'flex', alignItems: 'center', gap: 12, fontSize: 12}}>
              <strong>{selected.size} selected</strong>
              <button className="button button-small"><Icon.Refresh size={12} /> Retry</button>
              <button className="button button-small button-danger"><Icon.Trash size={12} /> Delete</button>
              <button className="button-link" onClick={()=>setSelected(new Set())}>Clear selection</button>
            </div>
          )}

          <table className="wp-list-table" style={{border: 'none', borderRadius: 0, boxShadow: 'none'}}>
            <thead>
              <tr>
                <th className="check-col">
                  <input type="checkbox" checked={allSelected} onChange={(e)=>{
                    if (e.target.checked) setSelected(new Set(filtered.map(r=>r.id)));
                    else setSelected(new Set());
                  }} />
                </th>
                <th>Post</th>
                <th style={{width: 110}}>Platform</th>
                <th style={{width: 90}}>Status</th>
                <th>Caption</th>
                <th style={{width: 160}}>Social ID</th>
                <th style={{width: 150}}>Date</th>
                <th style={{width: 80}}></th>
              </tr>
            </thead>
            <tbody>
              {filtered.map(r => (
                <LogRow key={r.id} row={r} selected={selected.has(r.id)} onSelect={(v)=>{
                  const next = new Set(selected);
                  if (v) next.add(r.id); else next.delete(r.id);
                  setSelected(next);
                }} />
              ))}
              {filtered.length === 0 && (
                <tr><td colSpan={8} style={{textAlign: 'center', padding: 40, color: 'var(--wp-text-3)'}}>No matching log entries.</td></tr>
              )}
            </tbody>
          </table>

          <div className="tablenav" style={{padding: '10px 16px', borderTop: '1px solid var(--wp-border-2)'}}>
            <div className="alignleft">
              <span className="displaying-num">Showing 1–{filtered.length} of {filtered.length}</span>
            </div>
            <div className="alignright">
              <button className="button button-small" disabled>‹</button>
              <button className="button button-small" disabled>›</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function LogRow({ row, selected, onSelect }) {
  const [expanded, setExpanded] = React.useState(false);
  return (
    <React.Fragment>
      <tr>
        <td className="check-col"><input type="checkbox" checked={selected} onChange={(e)=>onSelect(e.target.checked)} /></td>
        <td className="col-title">
          <a href="#" onClick={(e)=>e.preventDefault()}>{row.post}</a>
          <div className="row-actions">
            <a href="#" onClick={(e)=>{e.preventDefault(); setExpanded(!expanded);}}>{expanded ? 'Hide' : 'View'} details</a>
            {(row.status === 'failed' || row.status === 'pending') && <a href="#" onClick={(e)=>e.preventDefault()}>Retry</a>}
            <a href="#" onClick={(e)=>e.preventDefault()}>Edit post</a>
            <a href="#" onClick={(e)=>e.preventDefault()} className="danger">Delete</a>
          </div>
        </td>
        <td><PlatformBadge platform={row.platform} /></td>
        <td><span className={`status status-${row.status}`}><span className="status-dot"></span>{row.status}</span></td>
        <td style={{maxWidth: 260, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: 12, color: 'var(--wp-text-3)'}} title={row.caption || row.error || ''}>
          {row.caption || <em style={{color: 'var(--wp-danger)'}}>{row.error}</em>}
        </td>
        <td style={{fontFamily: 'ui-monospace, monospace', fontSize: 11, color: 'var(--wp-text-3)'}}>
          {row.social_id || '—'}
        </td>
        <td style={{fontSize: 12, color: 'var(--wp-text-3)', whiteSpace: 'nowrap'}}>{row.date}</td>
        <td style={{textAlign: 'right'}}>
          {row.status === 'failed' && <button className="button button-small"><Icon.Refresh size={12} /></button>}
        </td>
      </tr>
      {expanded && (
        <tr>
          <td colSpan={8} style={{background: '#f6f7f7', padding: 16}}>
            <div style={{display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16}}>
              <div>
                <div style={{fontSize: 11, fontWeight: 600, color: 'var(--wp-text-3)', textTransform: 'uppercase', marginBottom: 4}}>Caption sent</div>
                <div style={{background: '#fff', border: '1px solid var(--wp-border-2)', borderRadius: 4, padding: 10, fontSize: 12, fontFamily: 'ui-monospace, monospace', whiteSpace: 'pre-wrap'}}>{row.caption || '(none)'}</div>
              </div>
              <div>
                <div style={{fontSize: 11, fontWeight: 600, color: 'var(--wp-text-3)', textTransform: 'uppercase', marginBottom: 4}}>API response</div>
                <div style={{background: '#1d2327', color: '#a3e635', borderRadius: 4, padding: 10, fontSize: 11, fontFamily: 'ui-monospace, monospace', whiteSpace: 'pre-wrap'}}>
                  {row.status === 'sent' && `HTTP 200 OK\n{\n  "id": "${row.social_id}",\n  "post_id": "${row.social_id?.split(':').pop() || '—'}",\n  "created_at": "${row.date}Z"\n}`}
                  {row.status === 'failed' && `HTTP 401 Unauthorized\n{\n  "errors": [{\n    "code": 32,\n    "message": "${row.error}"\n  }]\n}`}
                  {row.status === 'skipped' && `Skipped — ${row.error}\nNo API call was made.`}
                  {row.status === 'pending' && `Awaiting cron · scheduled for ${row.date}`}
                </div>
              </div>
            </div>
          </td>
        </tr>
      )}
    </React.Fragment>
  );
}

Object.assign(window, { ActivityLog });
