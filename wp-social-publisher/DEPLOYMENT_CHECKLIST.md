# Deployment Checklist — WP Social Publisher v1.0.0

## Before Uploading to Live Site

### API Setup (complete these FIRST — takes 1–3 days)

- [ ] Facebook Developer App created at developers.facebook.com
- [ ] Facebook app has Pages API + Instagram Graph API permissions approved
- [ ] Long-lived Page Access Token generated (valid 60 days)
- [ ] Instagram account is Business or Creator type
- [ ] Instagram account linked to your Facebook Page
- [ ] LinkedIn Developer App created at linkedin.com/developers
- [ ] LinkedIn OAuth 2.0 flow completed, access token obtained (valid 60 days)
- [ ] Record the LinkedIn token expiry date — enter it in Settings > LinkedIn
- [ ] X Developer account approved at developer.x.com
- [ ] X app has Read + Write OAuth 1.0a permissions enabled
- [ ] X Consumer Key, Consumer Secret, Access Token, Access Token Secret obtained

### WordPress Site Prerequisites

- [ ] WordPress 5.8 or higher
- [ ] PHP 7.4 or higher
- [ ] Site is publicly accessible via HTTPS (required by all social APIs)
- [ ] Featured images hosted on the live site URL (required for Instagram)
- [ ] WP Cron is running — verify with the WP Crontrol plugin or `wp cron event list`

### Upload & Activate

- [ ] Back up the live site database before activating
- [ ] Run `bash build-zip.sh` from inside the `wp-social-publisher/` directory
- [ ] Upload `wp-social-publisher-1.0.0.zip` via Plugins > Add New > Upload Plugin
- [ ] Activate the plugin
- [ ] Visit Social Media > Settings
- [ ] Enter credentials for each platform (Facebook/Instagram, LinkedIn, X)
- [ ] Click "Test Connection" for each platform — confirm success
- [ ] Create a test draft post, check all four social channels, publish
- [ ] Verify entries appear in Social Media > Activity Log with status "sent"
- [ ] Check each social account to confirm posts arrived

### Post-Activation

- [ ] Confirm `wsp_token_check` and `wsp_purge_logs` cron events are scheduled
      (check with WP Crontrol: Tools > Cron Events)
- [ ] Set a calendar reminder to refresh Facebook and LinkedIn tokens before the 60-day expiry
- [ ] Review Activity Log after the first few posts for any errors
- [ ] (Optional) Configure default hashtags per platform in Settings > Defaults
- [ ] (Optional) Adjust log retention period in Settings > Defaults (default: 90 days)
