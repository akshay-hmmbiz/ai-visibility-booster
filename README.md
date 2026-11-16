# AI Visibility Booster

AI Visibility Booster is a free WordPress plugin that gives AI systems (ChatGPT, Claude, Gemini, Perplexity and others) clean, structured versions of your content to read. It adds an AI sitemap, a JSON feed, a knowledge graph, Markdown copies of your posts, JSON-LD schema, and a log of which AI bots visit your site.

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)](https://www.php.net)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green)](LICENSE)
[![Version](https://img.shields.io/badge/Version-1.1.1-orange)](https://github.com/akshay-hmmbiz/ai-visibility-booster)

---

## Benefits

- **One place for machines to read your site.** Three public files list your published posts and pages with a title, URL, short summary, dates, categories and tags.
- **A short summary for every post.** The plugin takes the first paragraph of each post, trims it, and stores it. No AI service is called and nothing leaves your server.
- **Schema without writing code.** JSON-LD is added to the page head for you.
- **See which AI bots visit.** A dashboard shows visits from 17 known bot user agents, by day, by bot and by page.
- **A simple checklist per post.** A 0 to 100 score in the editor shows what is missing: headings, summary, internal links, length, schema.
- **Works next to your SEO plugin.** If Yoast SEO, Rank Math or All in One SEO is active, the plugin skips the meta description and most schema so they are not duplicated.
- **No account, no API key, no external calls.**

---

## Features

| Feature | What the code does |
|---|---|
| **AI Sitemap** | Serves `/ai-sitemap.xml`. This is a custom XML format (`<ai-sitemap>`), not a standard sitemaps.org sitemap. Up to 500 published posts and pages. |
| **AI Discovery Feed** | Serves `/ai-feed.json`. Same content as the sitemap, in JSON. Up to 500 items. |
| **Knowledge Graph** | Serves `/ai-knowledge.json`: site name, top-level pages, categories, up to 100 tags, and up to 200 posts and pages. |
| **Markdown Export** | Serves `/ai-markdown/{post-slug}`: one published post or top-level page converted to Markdown. |
| **Content Summaries** | Stores the first paragraph (about 35 words) as post meta `_aivb_summary`. Prints `<meta name="ai-summary">` in the page head. |
| **Schema Markup** | Prints JSON-LD: `Article` (posts), `WebPage` (pages), `FAQPage` (when FAQ-style content is found), `Organization` and `WebSite` (homepage). |
| **AI Visibility Score** | A 0 to 100 score per post, shown in the block editor sidebar and in a Classic Editor meta box. |
| **Bot Analytics** | Logs front-end requests from known AI and search bots to a table in your own database. Shows totals, a daily chart, top bots and recent visits. |

Details for each feature are in [FEATURES.md](FEATURES.md).

---

## Install in 5 minutes

1. On this GitHub page, click the green **Code** button, then **Download ZIP**.
2. In your WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Choose the ZIP file, click **Install Now**, then **Activate Plugin**.
4. In the left menu, click **AI Visibility**. This is the dashboard and the settings page. Each feature has a checkbox. All are on by default.
5. Click **Bulk Generate Missing Summaries** once, so existing posts get a summary.

### Check that it is working

Open these addresses in your browser. Replace `example.com` with your domain.

| Address | What you should see |
|---|---|
| `https://example.com/ai-sitemap.xml` | XML listing your posts and pages |
| `https://example.com/ai-feed.json` | JSON listing your posts and pages |
| `https://example.com/ai-knowledge.json` | JSON describing your site, categories and tags |
| `https://example.com/ai-markdown/hello-world` | One post as Markdown. Replace `hello-world` with the slug of a published post. |

If an address shows "page not found", go to **Settings → Permalinks** and click **Save Changes**. Then try again.

Bot visits appear under **AI Visibility → Bot Analytics**. The page is empty until a known bot visits your site.

To see the score, open any post in the editor. In the block editor, open the **⋮** menu and choose **AI Visibility Score**. In the Classic Editor, look for the **AI Visibility Score** box in the right column.

More detail is in [INSTALLATION.md](INSTALLATION.md).

---

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL or MariaDB as used by WordPress
- Pretty permalinks recommended (any setting other than "Plain")

---

## How it behaves next to an SEO plugin

When Yoast SEO, Rank Math or All in One SEO is active:

- The plugin does not print `<meta name="description">`.
- The plugin does not print `Organization`, `WebSite`, `FAQPage` or `WebPage` schema.
- The plugin still prints one `Article` block on single posts. Your SEO plugin may print its own `Article` schema too. If you do not want two, turn off **Structured Data (Schema)** in the plugin settings.
- `<meta name="ai-summary">`, the three endpoint files and the Markdown export are not affected.

---

## AI Visibility Score

| Check | Points |
|---|---|
| An `<h1>` inside the post content | 10 |
| An `<h2>` inside the post content | 10 |
| A summary is stored | 20 |
| Internal links (1+ = 10, 5+ = 15, 10+ = 20) | up to 20 |
| Word count (100+ = 5, 300+ = 10, 600+ = 15, 1000+ = 20) | up to 20 |
| Schema is enabled, or an SEO plugin is active | 20 |

The score is a checklist made by the author. It is not a measure used by any AI company.

---

## Privacy

- The plugin makes no external requests. All data stays in your WordPress database.
- It sets no cookies.
- Bot Analytics only logs requests whose user agent matches a known bot. Normal visitors are not logged.
- For each bot visit it stores: bot name, user agent, page URL, time, and a salted SHA-256 hash of the IP address. The raw IP address is not stored.
- Logged rows older than 90 days are deleted by a weekly scheduled task.
- Author names are not included in the sitemap, feed or knowledge graph.
- Password-protected, private and draft posts are not included in any endpoint.

This is a description of what the code does. It is not legal advice about GDPR or any other law.

---

## FAQ

See [FAQ.md](FAQ.md).

---

## Tested

This section says plainly what was and was not checked for version 1.1.1.

**What was done**

- Every PHP file (11 files) was parsed with the `php-parser` JavaScript library. All parsed without syntax errors.
- Both JavaScript files passed `node --check` (syntax only).
- Every function the plugin calls with the `aivb_` prefix was checked to exist, and every hook callback was checked to point at a real function.
- All PHP and JavaScript was read line by line for security and correctness. The issues found and fixed are listed in the changelog below.

**What was not done**

- Version 1.1.1 has **not been run inside a WordPress install** as part of this release. No PHP interpreter was available during the review.
- The endpoints, the settings page, the editor sidebar and the bot log were not exercised in a browser for this release.
- It was not tested against specific WordPress versions, PHP versions, themes, page builders, caching plugins or multisite.
- It was not tested next to Yoast SEO, Rank Math or All in One SEO.

"Parsed cleanly" is not the same as "works in WordPress". Please try the plugin on a staging site first. If something breaks, open an issue.

---

## Honest limits

- **No plugin can make an AI system cite your site.** Each AI company decides what to crawl, index and cite. This plugin only makes your content easier to read.
- `/ai-sitemap.xml`, `/ai-feed.json`, `/ai-knowledge.json` and `<meta name="ai-summary">` are this plugin's own formats. They are not standards. There is no evidence in this repository that any AI crawler requests or uses them.
- The plugin does not add these addresses to `robots.txt` and does not link to them from your pages. A crawler has to be told about them some other way.
- Only posts and pages are included. Custom post types (products, events and so on) are not.
- The Markdown export works for posts and top-level pages. Child pages are not supported.
- Summaries are the first paragraph of the post. They are not written by AI. Page builders that do not store text in the post content may give short or empty summaries.
- FAQ detection is a pattern match. Any H2, H3 or H4 that ends with a question mark and is followed by a paragraph is treated as a question and answer.
- The plugin does not read "noindex" settings from SEO plugins. A published post set to noindex in Yoast or Rank Math still appears in the endpoints. The endpoints themselves are sent with `X-Robots-Tag: noindex`.
- "Citation" in Bot Analytics means a visit from a user agent the plugin labels as citation type (for example `ChatGPT-User`). It does not prove your page was quoted in an answer. User agents can be faked, and the plugin does not verify them.
- Bot Analytics writes one database row per bot request, including Googlebot and Bingbot. On a busy site the table can grow large within the 90-day window. There is no setting screen for the retention period.
- Deleting the plugin does not remove its settings, stored summaries or the bot log table. See [INSTALLATION.md](INSTALLATION.md) for how to remove them.
- On multisite, activate the plugin on each site separately. Network activation only creates the bot log table for the main site.

---

## Changelog

### 1.1.1

Fixes found during a full code review.

- Security: password-protected posts no longer appear in the AI sitemap, feed, knowledge graph or Markdown export, and their summary and FAQ text are no longer printed in the page head.
- Security: the score REST route now checks that the user can edit the requested post.
- Security: bulk summary generation now requires the `manage_options` capability.
- Fix: endpoint caches are cleared when a post is unpublished, trashed or made private. Before, it could stay in the cached files for up to 12 hours.
- Fix: saving settings no longer ends on a blank page.
- Fix: the "AI Content Summaries" setting now turns the summary meta tag on and off.
- Fix: the bulk generate message now reports the real number of summaries created.
- Fix: bot visit times are stored in UTC, matching the queries that read them.
- Fix: the Bot Analytics page now loads its stylesheet.
- Fix: Markdown export no longer confuses `<br>` with `<b>`, `<img>` with `<i>`, `<pre>` with `<p>`, or `<thead>` with `<th>`.
- Docs: corrected claims that did not match the code.

### 1.1.0

- New: Bot Analytics page with daily chart, top bots table and recent activity log.
- New: 30-minute crawl session grouping.
- New: weekly cleanup of old bot log rows.
- New: analytics on/off setting.

### 1.0.0

- Initial release.

---

## About the author

**AI Visibility Booster** was designed, built and tested by **Akshay Bhimani**, Senior SEO Strategist.

I have 5+ years of hands-on SEO experience with B2B clients worldwide. My work covers technical SEO, tracking and analytics audits, and visibility in AI search. I built this plugin because most websites give AI systems very little to read, and fixing that by hand on every page does not scale.

- LinkedIn: [in.linkedin.com/in/akshay-bhimani](https://in.linkedin.com/in/akshay-bhimani)
- GitHub: [github.com/akshay-hmmbiz](https://github.com/akshay-hmmbiz)
- Email: akshay.bhimani7@gmail.com

---

## Licence

GPL-2.0-or-later. The full text is in [LICENSE](LICENSE).

This plugin is not affiliated with or endorsed by OpenAI, Anthropic, Google, Microsoft, Perplexity or any other company named here. See [DISCLAIMER.txt](DISCLAIMER.txt).
