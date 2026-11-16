# Installation Guide — AI Visibility Booster

*Plugin developed by Akshay Bhimani.*

---

## Requirements

| Requirement | Minimum | Recommended |
|---|---|---|
| WordPress | 5.8 | 6.0+ |
| PHP | 7.4 | 8.1+ |
| MySQL / MariaDB | 5.6 | 8.0+ |
| Browser (admin) | Any modern browser | Chrome / Firefox latest |

No external libraries, Composer packages, or npm build steps are required. The plugin is self-contained.

---

## Installation Methods

### Method A — WordPress Admin Upload (Recommended)

1. Open https://github.com/akshay-hmmbiz/ai-visibility-booster, click **Code**, then **Download ZIP**.
2. In your WordPress admin, navigate to **Plugins → Add New**.
3. Click **Upload Plugin**.
4. Choose the `.zip` file and click **Install Now**.
5. After installation completes, click **Activate Plugin**.

### Method B — FTP / File Manager

1. Extract the downloaded ZIP on your computer. Rename the extracted folder to `ai-visibility-booster`.
2. Upload the `ai-visibility-booster/` folder to `/wp-content/plugins/` on your server.
3. In your WordPress admin, navigate to **Plugins → Installed Plugins**.
4. Locate **AI Visibility Booster** and click **Activate**.

### Method C — WP-CLI

```bash
wp plugin install /path/to/downloaded.zip --activate
```

---

## First-Run Setup

After activation, WordPress automatically:

- Registers the four custom URL endpoints:
  - `/ai-sitemap.xml`
  - `/ai-feed.json`
  - `/ai-knowledge.json`
  - `/ai-markdown/{post-slug}`
- Flushes rewrite rules so the endpoints resolve immediately.
- Creates one database table, `{prefix}aivb_bot_visits`, for the bot log.
- Adds the default settings (all features on).

No manual configuration is required.

---

## Permalink Flush (If Endpoints Return 404)

If any AI endpoint returns a 404 error after activation, flush your permalink settings:

1. Go to **Settings → Permalinks**.
2. Click **Save Changes** (no changes needed — just saving flushes the rules).

The endpoints work best with pretty permalinks (any permalink setting other than "Plain").

---

## Admin Dashboard

Navigate to **WordPress Admin → AI Visibility** in the left sidebar.

The dashboard displays:

- **Average AI Visibility Score** across the 50 most recently modified posts and pages
- **AI Content Summaries** — count of posts with summaries and bulk generate button
- **AI Sitemap** — live status and direct link to `/ai-sitemap.xml`
- **AI Discovery Feed** — live status and direct link to `/ai-feed.json`
- **AI Knowledge Graph** — live status and direct link to `/ai-knowledge.json`
- **Structured Data** — status and SEO plugin compatibility notice
- **AI Bot Analytics** — status, 30-day visit count and a link to the full analytics page

---

## Feature Configuration

All features are enabled by default. To selectively disable features:

1. Go to **AI Visibility** and scroll down to **Feature Settings**.
2. Uncheck any feature you want to disable.
3. Click **Save Settings**.

| Feature | Option Key | Default |
|---|---|---|
| AI Content Summaries | `aivb_enable_summary` | Enabled |
| AI Sitemap | `aivb_enable_sitemap` | Enabled |
| AI Discovery Feed | `aivb_enable_feed` | Enabled |
| AI Knowledge Graph | `aivb_enable_knowledge_graph` | Enabled |
| Structured Data | `aivb_enable_schema` | Enabled |
| AI Markdown Export | `aivb_enable_markdown` | Enabled |
| AI Bot Analytics | `aivb_enable_analytics` | Enabled |

Saving settings also flushes WordPress rewrite rules automatically.

---

## Generating AI Summaries

### Automatic (New Posts)

Summaries are generated automatically when a post or page is saved. No action is required.

### Bulk Generate (Existing Content)

For existing posts that were published before the plugin was installed:

1. Go to **AI Visibility** dashboard.
2. Click the **Bulk Generate Missing Summaries** button.
3. Wait for the confirmation message. The number of summaries generated is reported.

Bulk generation only processes posts that do not yet have a summary — existing summaries are not overwritten.

---

## SEO Plugin Compatibility

AI Visibility Booster automatically detects the following SEO plugins and adjusts its output to prevent duplication:

| SEO Plugin | Detection Method | Behaviour |
|---|---|---|
| Yoast SEO | `WPSEO_VERSION` constant | Skips `<meta name="description">`, Organization, WebSite, WebPage, FAQPage schemas |
| RankMath | `RANK_MATH_VERSION` constant | Same as above |
| All in One SEO | `AIOSEO_VERSION` constant | Same as above |

Article schema is still output on single posts when an SEO plugin is active. The SEO plugin may print its own Article schema too. Turn off **Structured Data (Schema)** in the settings if you do not want both.

If you use a different SEO plugin, all features remain active. There is no conflict — the AI endpoints and meta tags are additive.

---

## Gutenberg Editor Integration

The AI Visibility Score sidebar panel appears automatically in the Gutenberg block editor for all posts and pages. To open it:

1. Open any post or page in the block editor.
2. Click the **Options** menu (⋮) in the top-right toolbar.
3. Select **AI Visibility Score** from the plugins list.

The panel shows your score, label, progress bar, and per-criterion breakdown. It refreshes after each save.

---

## Classic Editor Integration

If you use the Classic Editor plugin, a meta box titled **AI Visibility Score** appears in the post edit screen (right column). It shows the same score data rendered server-side.

---

## Verifying Endpoints

After installation, verify each endpoint is accessible:

```
https://yourdomain.com/ai-sitemap.xml
https://yourdomain.com/ai-feed.json
https://yourdomain.com/ai-knowledge.json
https://yourdomain.com/ai-markdown/hello-world
```

Replace `hello-world` with any published post slug. All endpoints should return a `200 OK` response with the correct `Content-Type` header.

Version 1.1.1 was not run in a live WordPress install before release (see the "Tested" section of README.md). Please do this check on a staging site first.

---

## Caching Notes

All three JSON/XML endpoints are cached using WordPress transients for 12 hours:

| Endpoint | Transient Key |
|---|---|
| `/ai-sitemap.xml` | `aivb_sitemap_cache` |
| `/ai-feed.json` | `aivb_feed_cache` |
| `/ai-knowledge.json` | `aivb_knowledge_cache` |

Caches are cleared whenever a post or page is saved, unpublished or trashed. You do not need to manually clear caches after publishing content.

If you use an external object cache (Redis, Memcached), the transients are stored there instead of the database, which is fully supported.

---

## Deactivation and Uninstallation

**Deactivation** removes the custom rewrite rules, the endpoint caches and the weekly cleanup task. Endpoints will return 404. Settings, summaries and the bot log are kept.

**Uninstallation** (deleting the plugin from Plugins → Installed Plugins) removes the plugin files only. The plugin has no uninstall routine. These stay in the database until you remove them yourself:
- Options whose names start with `aivb_`
- Post meta with the key `_aivb_summary`
- The table `{prefix}aivb_bot_visits`

---

## Multisite

Multisite has not been tested. Settings, summaries and endpoints are per site. Activate the plugin on each site separately: network activation creates the bot log table for the main site only.

---

## Troubleshooting

| Problem | Solution |
|---|---|
| Endpoints return 404 | Flush permalinks: **Settings → Permalinks → Save Changes** |
| Bulk generate button does nothing | Check the browser console for JavaScript errors. The button needs an Administrator account (`manage_options`). |
| Score not updating after save | Click **↻ Refresh Score** in the Gutenberg sidebar |
| Sitemap/feed shows stale data | Transient cache updates on next post save; or deactivate/reactivate to flush all caches |
| Schema not appearing | Check if Yoast/RankMath/AIOSEO is active — plugin defers to them automatically |
| Bot Analytics page is empty | Rows appear only after a known bot requests a front-end page. Page caching can serve bots without running WordPress, in which case nothing is logged. |
| `ai-markdown/{slug}` returns 404 | Flush permalinks and verify the slug belongs to a published post/page |

---

*Plugin developed by Akshay Bhimani.*
