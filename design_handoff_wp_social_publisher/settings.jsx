// settings.jsx — Settings page (4 tabs)

function Settings({ tweaks }) {
  const [tab, setTab] = React.useState('facebook');

  return (
    <div className="fade-in">
      <h1 className="wp-heading">Social Publisher Settings</h1>
      <div className="wp-subhead">Connect each platform and configure global defaults. Credentials are encrypted with AES-256-CBC using your site's <kbd>AUTH_KEY</kbd>.</div>

      <div className="nav-tab-wrapper">
        <button className={`nav-tab ${tab==='facebook'?'nav-tab-active':''}`} onClick={()=>setTab('facebook')}>
          <PlatformIconSolo platform="facebook" size={12} /> Facebook / Instagram
        </button>
        <button className={`nav-tab ${tab==='linkedin'?'nav-tab-active':''}`} onClick={()=>setTab('linkedin')}>
          <PlatformIconSolo platform="linkedin" size={12} /> LinkedIn
        </button>
        <button className={`nav-tab ${tab==='twitter'?'nav-tab-active':''}`} onClick={()=>setTab('twitter')}>
          <PlatformIconSolo platform="twitter" size={12} /> X (Twitter)
        </button>
        <button className={`nav-tab ${tab==='defaults'?'nav-tab-active':''}`} onClick={()=>setTab('defaults')}>
          <Icon.Settings size={12} /> Defaults
        </button>
      </div>

      {tab === 'facebook' && <FacebookTab tweaks={tweaks} />}
      {tab === 'linkedin' && <LinkedInTab tweaks={tweaks} />}
      {tab === 'twitter'  && <TwitterTab  tweaks={tweaks} />}
      {tab === 'defaults' && <DefaultsTab tweaks={tweaks} />}
    </div>
  );
}

// Reusable: password field with reveal toggle
function SecretField({ value, onChange, placeholder, masked=true }) {
  const [show, setShow] = React.useState(false);
  return (
    <div style={{position: 'relative', display: 'inline-block', width: 360, maxWidth: '100%'}}>
      <input
        type={show ? 'text' : 'password'}
        className="regular-text code-text"
        value={value}
        onChange={(e)=>onChange?.(e.target.value)}
        placeholder={placeholder}
        style={{width: '100%', paddingRight: 36}}
      />
      <button type="button" className="button-link" onClick={()=>setShow(!show)}
              style={{position: 'absolute', right: 6, top: '50%', transform: 'translateY(-50%)', height: 22, padding: 4, color: 'var(--wp-text-3)'}}>
        {show ? <Icon.EyeOff size={14} /> : <Icon.Eye size={14} />}
      </button>
    </div>
  );
}

// Reusable: test connection button with simulated states
function TestConnectionButton({ platform, simulateErrors }) {
  const [state, setState] = React.useState('idle'); // idle | testing | ok | err
  const test = () => {
    setState('testing');
    setTimeout(() => {
      setState(simulateErrors && platform === 'twitter' ? 'err' : 'ok');
      setTimeout(() => setState('idle'), 4000);
    }, 1200);
  };
  return (
    <div style={{display: 'flex', alignItems: 'center', gap: 10, marginTop: 4}}>
      <button className="button" onClick={test} disabled={state==='testing'}>
        {state === 'testing' && <span className="spinner-inline"></span>}
        {state === 'testing' ? 'Testing…' : 'Test Connection'}
      </button>
      {state === 'ok' && (
        <span style={{color: 'var(--wp-success)', fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 4}}>
          <Icon.Check size={14} /> Connected · responded in 312ms
        </span>
      )}
      {state === 'err' && (
        <span style={{color: 'var(--wp-danger)', fontSize: 12, display: 'inline-flex', alignItems: 'center', gap: 4}}>
          <Icon.Alert size={14} /> HTTP 401 — OAuth signature did not validate
        </span>
      )}
    </div>
  );
}

function FacebookTab({ tweaks }) {
  return (
    <div>
      <div className="postbox">
        <div className="postbox-header">
          <h2 className="postbox-title">
            <span style={{display: 'inline-flex', alignItems: 'center', gap: 8}}>
              <PlatformIconSolo platform="facebook" size={14} />
              Facebook Page
            </span>
          </h2>
          <span className="status status-sent"><Icon.Check size={11} /> Connected</span>
        </div>
        <div className="postbox-body">
          <table className="form-table">
            <tbody>
              <tr>
                <th><label>App ID</label></th>
                <td>
                  <input type="text" className="regular-text code-text" defaultValue="1148903257641902" />
                  <p className="description">From Meta for Developers → Your App → Settings → Basic.</p>
                </td>
              </tr>
              <tr>
                <th><label>App Secret</label></th>
                <td><SecretField value="••••••••••••••••••••••••" placeholder="App Secret" /></td>
              </tr>
              <tr>
                <th><label>Page Access Token</label></th>
                <td>
                  <SecretField value="EAAGm0PX4ZCpsBAJ...•••••...sLgZD" />
                  <p className="description">Long-lived page token. {tweaks.showTokenWarning ? <span style={{color: 'var(--wp-warning)', fontWeight: 600}}>Expires in 6 days — regenerate soon.</span> : 'Expires 27 Jul 2026 · 54 days remaining.'}</p>
                </td>
              </tr>
              <tr>
                <th><label>Page ID</label></th>
                <td>
                  <input type="text" className="regular-text code-text" defaultValue="102934857601289" />
                  <p className="description">Numeric ID of the Page you want to publish to.</p>
                </td>
              </tr>
              <tr>
                <th></th>
                <td><TestConnectionButton platform="facebook" simulateErrors={tweaks.simulateErrors} /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div className="postbox">
        <div className="postbox-header">
          <h2 className="postbox-title">
            <span style={{display: 'inline-flex', alignItems: 'center', gap: 8}}>
              <PlatformIconSolo platform="instagram" size={14} />
              Instagram (Graph API)
            </span>
          </h2>
          <span className="status status-sent"><Icon.Check size={11} /> Connected</span>
        </div>
        <div className="postbox-body">
          <div className="notice" style={{margin: '0 0 14px'}}>
            <div className="notice-icon"><Icon.Info size={16} /></div>
            <div className="notice-body">
              Instagram requires a <strong>Business or Creator account linked to a Facebook Page</strong> and uses the same App credentials above.
              Posts without a featured image are skipped (logged, not failed).
            </div>
          </div>
          <table className="form-table">
            <tbody>
              <tr>
                <th><label>Instagram User ID</label></th>
                <td>
                  <input type="text" className="regular-text code-text" defaultValue="17841405822913094" />
                  <p className="description">Numeric IG Business Account ID. Find via <code>?fields=instagram_business_account</code>.</p>
                </td>
              </tr>
              <tr>
                <th><label>Image source</label></th>
                <td>
                  <select defaultValue="featured" style={{width: 280}}>
                    <option value="featured">Featured image (full size)</option>
                    <option value="firstblock">First image block in post</option>
                  </select>
                </td>
              </tr>
              <tr>
                <th></th>
                <td><TestConnectionButton platform="instagram" simulateErrors={tweaks.simulateErrors} /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function LinkedInTab({ tweaks }) {
  const [authorType, setAuthorType] = React.useState('organization');
  return (
    <div className="postbox">
      <div className="postbox-header">
        <h2 className="postbox-title">
          <span style={{display: 'inline-flex', alignItems: 'center', gap: 8}}>
            <PlatformIconSolo platform="linkedin" size={14} />
            LinkedIn
          </span>
        </h2>
        <span className="status status-sent"><Icon.Check size={11} /> Connected</span>
      </div>
      <div className="postbox-body">
        <table className="form-table">
          <tbody>
            <tr>
              <th><label>Client ID</label></th>
              <td>
                <input type="text" className="regular-text code-text" defaultValue="78x9aq2bv6kt3p" />
              </td>
            </tr>
            <tr>
              <th><label>Client Secret</label></th>
              <td><SecretField value="••••••••••••••••" /></td>
            </tr>
            <tr>
              <th><label>Access Token</label></th>
              <td>
                <SecretField value="AQVJiQNXz0t...•••...nm7K" />
                <p className="description">OAuth 2.0 access token. Scopes: <code>w_member_social</code>, <code>w_organization_social</code>.</p>
              </td>
            </tr>
            <tr>
              <th><label>Token expiry</label></th>
              <td>
                <input type="text" className="regular-text" value="2026-07-10 · 43 days remaining" disabled />
                <p className="description">Populated automatically when the token is saved.</p>
              </td>
            </tr>
            <tr>
              <th><label>Publish as</label></th>
              <td>
                <label className="radio-label">
                  <input type="radio" name="authortype" checked={authorType==='person'} onChange={()=>setAuthorType('person')} />
                  <span>Personal profile</span>
                </label>
                <label className="radio-label">
                  <input type="radio" name="authortype" checked={authorType==='organization'} onChange={()=>setAuthorType('organization')} />
                  <span>Company / organization page</span>
                </label>
              </td>
            </tr>
            <tr>
              <th><label>{authorType === 'person' ? 'Person URN' : 'Organization URN'}</label></th>
              <td>
                <input type="text" className="regular-text code-text"
                       defaultValue={authorType === 'person' ? 'urn:li:person:XYZ123abc' : 'urn:li:organization:104821095'} />
                <p className="description">{authorType === 'person'
                  ? 'From the LinkedIn /v2/me endpoint.'
                  : 'From your LinkedIn Page admin URL.'}</p>
              </td>
            </tr>
            <tr>
              <th><label>Default visibility</label></th>
              <td>
                <select defaultValue="PUBLIC" style={{width: 280}}>
                  <option value="PUBLIC">Public — anyone on or off LinkedIn</option>
                  <option value="CONNECTIONS">Connections only</option>
                </select>
              </td>
            </tr>
            <tr>
              <th></th>
              <td><TestConnectionButton platform="linkedin" simulateErrors={tweaks.simulateErrors} /></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  );
}

function TwitterTab({ tweaks }) {
  return (
    <div>
      <div className="postbox">
        <div className="postbox-header">
          <h2 className="postbox-title">
            <span style={{display: 'inline-flex', alignItems: 'center', gap: 8}}>
              <PlatformIconSolo platform="twitter" size={14} />
              X (Twitter) — OAuth 1.0a
            </span>
          </h2>
          {tweaks.simulateErrors
            ? <span className="status status-failed"><Icon.Alert size={11} /> Auth failed</span>
            : <span className="status status-sent"><Icon.Check size={11} /> Connected</span>}
        </div>
        <div className="postbox-body">
          <div className="notice warning" style={{margin: '0 0 16px'}}>
            <div className="notice-icon"><Icon.Info size={16} /></div>
            <div className="notice-body">
              X API <strong>Free tier</strong> allows <strong>1,500 tweets per month</strong>. This site is at <strong>823 / 1,500</strong> (55%). Resets on the 1st.
              <div className="progress" style={{marginTop: 8, maxWidth: 360}}>
                <div className="bar warn" style={{width: '55%'}}></div>
              </div>
            </div>
          </div>
          <table className="form-table">
            <tbody>
              <tr>
                <th><label>Consumer Key</label></th>
                <td><input type="text" className="regular-text code-text" defaultValue="kLp9XaQz72wRdN3VtH8mYbCe" /></td>
              </tr>
              <tr>
                <th><label>Consumer Secret</label></th>
                <td><SecretField value="••••••••••••••••••••••••••••••••" /></td>
              </tr>
              <tr>
                <th><label>Access Token</label></th>
                <td><input type="text" className="regular-text code-text" defaultValue="1485903271-aXk2pQ...sR9" /></td>
              </tr>
              <tr>
                <th><label>Access Token Secret</label></th>
                <td><SecretField value="••••••••••••••••••••••••••••••••" /></td>
              </tr>
              <tr>
                <th><label>Append post URL</label></th>
                <td>
                  <label className="toggle"><input type="checkbox" defaultChecked /><span className="slider"></span></label>
                  <span className="muted" style={{marginLeft: 10, fontSize: 12}}>Auto-truncate caption to fit 280 chars including URL.</span>
                </td>
              </tr>
              <tr>
                <th><label>Upload featured image</label></th>
                <td>
                  <label className="toggle"><input type="checkbox" defaultChecked /><span className="slider"></span></label>
                  <span className="muted" style={{marginLeft: 10, fontSize: 12}}>Uses v1.1 media endpoint, then attaches <code>media_ids</code>.</span>
                </td>
              </tr>
              <tr>
                <th></th>
                <td><TestConnectionButton platform="twitter" simulateErrors={tweaks.simulateErrors} /></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

function DefaultsTab() {
  return (
    <div>
      <div className="postbox">
        <div className="postbox-header"><h2 className="postbox-title">Caption defaults</h2></div>
        <div className="postbox-body">
          <table className="form-table">
            <tbody>
              <tr>
                <th><label>Default hashtags</label></th>
                <td style={{padding: '14px 0'}}>
                  <div style={{display: 'grid', gridTemplateColumns: '120px 1fr', gap: '10px 16px', alignItems: 'center', maxWidth: 560}}>
                    {PLATFORMS.map(p => (
                      <React.Fragment key={p.id}>
                        <label style={{display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12, fontWeight: 500}}>
                          <PlatformIconSolo platform={p.id} size={12} /> {p.name}
                        </label>
                        <input type="text" defaultValue={
                          p.id === 'twitter' ? '#engineering #databases' :
                          p.id === 'instagram' ? '#devops #databases #infra #softwareengineering' :
                          p.id === 'linkedin' ? '#engineering #databases #infrastructure' :
                          '#TerraSync #engineering'
                        } />
                      </React.Fragment>
                    ))}
                  </div>
                  <p className="description" style={{marginTop: 10}}>Appended to captions that don't already include them. Comma- or space-separated.</p>
                </td>
              </tr>
              <tr>
                <th><label>Auto-append post URL</label></th>
                <td>
                  <label className="toggle"><input type="checkbox" defaultChecked /><span className="slider"></span></label>
                  <span className="muted" style={{marginLeft: 10, fontSize: 12}}>Append the post permalink to caption tail (except X, which has its own setting).</span>
                </td>
              </tr>
              <tr>
                <th><label>Caption fallback</label></th>
                <td>
                  <select defaultValue="excerpt" style={{width: 280}}>
                    <option value="excerpt">Post excerpt</option>
                    <option value="title">Post title</option>
                    <option value="both">Title + excerpt</option>
                  </select>
                  <p className="description">Used when a platform's per-post caption is empty.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div className="postbox">
        <div className="postbox-header"><h2 className="postbox-title">Behaviour</h2></div>
        <div className="postbox-body">
          <table className="form-table">
            <tbody>
              <tr>
                <th><label>Enabled post types</label></th>
                <td>
                  <label className="checkbox-label"><input type="checkbox" defaultChecked /> Post</label>
                  <label className="checkbox-label"><input type="checkbox" defaultChecked /> Article (custom)</label>
                  <label className="checkbox-label"><input type="checkbox" /> Page</label>
                  <label className="checkbox-label"><input type="checkbox" defaultChecked /> Release notes (custom)</label>
                  <label className="checkbox-label"><input type="checkbox" /> Documentation</label>
                </td>
              </tr>
              <tr>
                <th><label>Default selected channels</label></th>
                <td>
                  {PLATFORMS.map(p => (
                    <label className="checkbox-label" key={p.id} style={{display: 'inline-flex', marginRight: 18}}>
                      <input type="checkbox" defaultChecked={p.id !== 'instagram'} />
                      <PlatformIconSolo platform={p.id} size={12} />
                      {p.name}
                    </label>
                  ))}
                  <p className="description" style={{marginTop: 8}}>Pre-checked when opening a new post. Editors can deselect per post.</p>
                </td>
              </tr>
              <tr>
                <th><label>Cron delay</label></th>
                <td>
                  <input type="number" defaultValue="5" min="0" max="60" style={{width: 80}} /> <span className="muted">seconds</span>
                  <p className="description">Delay between Publish action and async cron dispatch. Prevents publish-action timeout.</p>
                </td>
              </tr>
              <tr>
                <th><label>Log retention</label></th>
                <td>
                  <input type="number" defaultValue="90" min="7" max="3650" style={{width: 80}} /> <span className="muted">days</span>
                  <p className="description">Activity Log entries older than this are purged daily by cron.</p>
                </td>
              </tr>
              <tr>
                <th><label>Expiry email alerts</label></th>
                <td>
                  <label className="toggle"><input type="checkbox" defaultChecked /><span className="slider"></span></label>
                  <span className="muted" style={{marginLeft: 10, fontSize: 12}}>Email admin when any token has ≤ 7 days left.</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div style={{display: 'flex', gap: 8, marginTop: 20}}>
        <button className="button button-primary">Save Changes</button>
        <button className="button">Reset to defaults</button>
      </div>
    </div>
  );
}

Object.assign(window, { Settings });
