// dashboard.jsx — Social Publisher overview / dashboard page

function Dashboard({ tweaks, onNav }) {
  return (
    <div className="fade-in">
      <h1 className="wp-heading">
        <Icon.Share size={22} />
        Social Publisher
      </h1>
      <div className="wp-subhead">Cross-post overview · last 30 days</div>

      {tweaks.showTokenWarning && (
        <div className="notice warning">
          <div className="notice-icon"><Icon.Alert size={18} /></div>
          <div className="notice-body">
            <strong>Facebook Page Access Token expires in 6 days.</strong>{' '}
            Regenerate a long-lived token in <a href="#" onClick={(e)=>{e.preventDefault(); onNav('settings');}}>Settings → Facebook / Instagram</a>{' '}
            to avoid interrupted publishing.
          </div>
        </div>
      )}

      <div className="grid-4" style={{marginBottom: 20}}>
        <div className="stat-card">
          <div className="stat-label">Posts published</div>
          <div className="stat-value">142</div>
          <div className="stat-delta"><span className="up">↑ 18%</span> vs prior period</div>
        </div>
        <div className="stat-card">
          <div className="stat-label">Cross-posts sent</div>
          <div className="stat-value">487</div>
          <div className="stat-delta"><span className="up">↑ 12%</span> vs prior period</div>
        </div>
        <div className="stat-card">
          <div className="stat-label">Failures</div>
          <div className="stat-value" style={{color: 'var(--wp-danger)'}}>9</div>
          <div className="stat-delta"><span className="down">2 needing retry</span></div>
        </div>
        <div className="stat-card">
          <div className="stat-label">X API usage</div>
          <div className="stat-value">823<span style={{fontSize:14, color:'var(--wp-text-3)', fontWeight:400}}> / 1,500</span></div>
          <div className="progress" style={{marginTop: 6}}>
            <div className="bar warn" style={{width: '55%'}}></div>
          </div>
        </div>
      </div>

      <div className="grid-2">
        <div className="postbox">
          <div className="postbox-header">
            <h2 className="postbox-title">Token health</h2>
            <button className="button-link" onClick={()=>onNav('settings')}>Manage credentials →</button>
          </div>
          <div className="postbox-body" style={{padding: '4px 16px 12px'}}>
            <TokenRow platform="facebook"  name="Facebook Page Access Token" sub="Page: TerraSync Cloud · Long-lived" days={tweaks.showTokenWarning ? 6 : 54} />
            <TokenRow platform="instagram" name="Instagram Graph Token"      sub="Account: @terrasync.cloud · Business" days={tweaks.showTokenWarning ? 6 : 54} />
            <TokenRow platform="linkedin"  name="LinkedIn OAuth Token"        sub="Org: TerraSync, Inc · UGC scope" days={43} />
            <TokenRow platform="twitter"   name="X (OAuth 1.0a)"               sub="App: TerraSync Publisher · Read+Write" days={null} />
          </div>
        </div>

        <div className="postbox">
          <div className="postbox-header">
            <h2 className="postbox-title">Recent activity</h2>
            <button className="button-link" onClick={()=>onNav('log')}>View full log →</button>
          </div>
          <div className="postbox-body" style={{padding: 0}}>
            <ActivityMini onNav={onNav} simulateErrors={tweaks.simulateErrors} />
          </div>
        </div>
      </div>

      <div className="grid-2" style={{marginTop: 16}}>
        <div className="postbox">
          <div className="postbox-header">
            <h2 className="postbox-title">Channel breakdown · 30 days</h2>
          </div>
          <div className="postbox-body">
            <ChannelBars />
          </div>
        </div>
        <div className="postbox">
          <div className="postbox-header">
            <h2 className="postbox-title">Quick start</h2>
          </div>
          <div className="postbox-body">
            <ol style={{margin: 0, paddingLeft: 18, lineHeight: 1.9, color: 'var(--wp-text-2)'}}>
              <li>Add API credentials for each platform in <a href="#" onClick={(e)=>{e.preventDefault(); onNav('settings');}}>Settings</a>.</li>
              <li>Open any post and check the channels in the <strong>Social Media</strong> sidebar.</li>
              <li>Hit Publish — cross-posts dispatch as async cron jobs within 5 seconds.</li>
              <li>Review delivery status in the <a href="#" onClick={(e)=>{e.preventDefault(); onNav('log');}}>Activity Log</a>.</li>
            </ol>
            <div style={{marginTop: 14, paddingTop: 14, borderTop: '1px solid var(--wp-border-2)', display: 'flex', gap: 8}}>
              <button className="button button-primary" onClick={()=>onNav('editor')}>
                <Icon.Plus size={14} /> New post with social
              </button>
              <button className="button" onClick={()=>onNav('settings')}>
                <Icon.Settings size={14} /> Open settings
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

function TokenRow({ platform, name, sub, days }) {
  const isInfinite = days === null;
  const cls = isInfinite ? '' : (days <= 7 ? 'danger' : days <= 14 ? 'warn' : '');
  const label = isInfinite ? 'No expiry' : days <= 0 ? 'Expired' : `${days} days left`;
  return (
    <div className="token-row">
      <PlatformIconSolo platform={platform} size={16} />
      <div className="token-meta">
        <div className="token-name">{name}</div>
        <div className="token-sub">{sub}</div>
      </div>
      <span className={`token-pill ${cls}`}>{label}</span>
    </div>
  );
}

function ActivityMini({ onNav, simulateErrors }) {
  const rows = [
    { post: 'Why edge databases are eating the stack', platform: 'linkedin',  status: 'sent',    when: '2m ago' },
    { post: 'Why edge databases are eating the stack', platform: 'twitter',   status: 'sent',    when: '2m ago' },
    { post: 'Why edge databases are eating the stack', platform: 'facebook',  status: 'sent',    when: '2m ago' },
    { post: 'Why edge databases are eating the stack', platform: 'instagram', status: 'skipped', when: '2m ago' },
    { post: 'Release notes — v4.2 (multi-region replicas)', platform: 'twitter',   status: simulateErrors ? 'failed' : 'sent', when: '1h ago' },
    { post: 'Release notes — v4.2 (multi-region replicas)', platform: 'linkedin',  status: 'sent', when: '1h ago' },
    { post: 'A field guide to vector indexes', platform: 'facebook',  status: 'sent', when: '4h ago' },
    { post: 'A field guide to vector indexes', platform: 'instagram', status: 'sent', when: '4h ago' },
  ];
  return (
    <table className="wp-list-table" style={{border: 'none', boxShadow: 'none', borderRadius: 0}}>
      <tbody>
        {rows.map((r,i) => (
          <tr key={i}>
            <td style={{paddingLeft: 16, width: 28}}><PlatformIconSolo platform={r.platform} size={14} /></td>
            <td className="col-title" style={{padding: '10px 8px', fontSize: 12}}>{r.post}</td>
            <td style={{padding: '10px 8px', width: 90}}><span className={`status status-${r.status}`}><span className="status-dot"></span>{r.status}</span></td>
            <td style={{padding: '10px 16px 10px 8px', textAlign: 'right', width: 60, color: 'var(--wp-text-3)', fontSize: 11}}>{r.when}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function ChannelBars() {
  const data = [
    { id: 'facebook',  sent: 132, failed: 1, skipped: 0 },
    { id: 'instagram', sent: 98,  failed: 0, skipped: 18 },
    { id: 'linkedin',  sent: 128, failed: 2, skipped: 0 },
    { id: 'twitter',   sent: 121, failed: 6, skipped: 0 },
  ];
  const max = Math.max(...data.map(d => d.sent + d.failed + d.skipped));
  return (
    <div style={{display: 'flex', flexDirection: 'column', gap: 14}}>
      {data.map(d => {
        const total = d.sent + d.failed + d.skipped;
        return (
          <div key={d.id} style={{display: 'flex', alignItems: 'center', gap: 12}}>
            <div style={{width: 100, display: 'flex', alignItems: 'center', gap: 8, fontSize: 12, fontWeight: 500, color: 'var(--wp-text)'}}>
              <PlatformIconSolo platform={d.id} size={14} />
              {PLATFORMS.find(p=>p.id===d.id).name}
            </div>
            <div style={{flex: 1, height: 14, background: 'var(--wp-border-2)', borderRadius: 7, overflow: 'hidden', display: 'flex'}}>
              <div style={{width: `${(d.sent/max)*100}%`, background: 'var(--wp-success)'}}></div>
              <div style={{width: `${(d.failed/max)*100}%`, background: 'var(--wp-danger)'}}></div>
              <div style={{width: `${(d.skipped/max)*100}%`, background: 'var(--wp-text-3)'}}></div>
            </div>
            <div style={{width: 60, textAlign: 'right', fontSize: 12, color: 'var(--wp-text-2)', fontVariantNumeric: 'tabular-nums'}}>{total} sent</div>
          </div>
        );
      })}
      <div style={{display: 'flex', gap: 14, fontSize: 11, color: 'var(--wp-text-3)', marginTop: 4}}>
        <span><span style={{display: 'inline-block', width: 8, height: 8, background: 'var(--wp-success)', borderRadius: 2, marginRight: 4}}></span>Sent</span>
        <span><span style={{display: 'inline-block', width: 8, height: 8, background: 'var(--wp-danger)',  borderRadius: 2, marginRight: 4}}></span>Failed</span>
        <span><span style={{display: 'inline-block', width: 8, height: 8, background: 'var(--wp-text-3)',  borderRadius: 2, marginRight: 4}}></span>Skipped</span>
      </div>
    </div>
  );
}

Object.assign(window, { Dashboard });
