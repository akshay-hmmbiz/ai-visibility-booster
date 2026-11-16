# AI Visibility Booster — Feature Reference

*Plugin developed by Akshay Bhimani.*

---

## 1. AI Content Summaries

**How it works**

Each time a published post or page is saved, the plugin extracts the first non-empty `<p>` tag from the post content, strips HTML, normalises whitespace, and trims the result to approximately 200 characters or 35 words. The summary is stored as `_aivb_summary` post meta.

**Frontend output**

```html
<meta name="ai-summary" content="Your post summary here...">
```

When no major SEO plugin is active, the plugin also outputs:

```html
<meta name="description" content="Your post summary here...">
```

**Bulk generation**

Use the **Bulk Generate Missing Summaries** button on the admin dashboard to generate summaries for all published posts and pages that do not yet have one.

The summary is regenerated on every save, so a hand-edited `_aivb_summary` value is overwritten the next time the post is saved.

Password-protected posts never output a summary.

Turning off **AI Content Summaries** in the settings removes the meta tags. Summaries are still stored, because the sitemap, feed and schema use them.

**Hooks**: `save_post` (automatic), `wp_head` priority 5

---

## 2. AI XML Sitemap

**Endpoint**: `/ai-sitemap.xml`

A supplementary XML file listing up to 500 published posts and pages, newest changes first. It uses a custom format, not the sitemaps.org protocol, so it cannot be submitted to search engines as a sitemap. It does not replace the sitemap from WordPress, Yoast or Rank Math. Password-protected, private and draft posts are left out.

**Schema format**:

```xml
<ai-sitemap>
  <meta>
    <generated>ISO-8601 timestamp</generated>
    <site-name>Site Name</site-name>
    <site-url>https://example.com/</site-url>
    <total-posts>42</total-posts>
  </meta>
  <posts>
    <post>
      <title>Post Title</title>
      <url>https://example.com/post-slug/</url>
      <summary>First-paragraph summary</summary>
      <published>ISO-8601 date</published>
      <lastmod>ISO-8601 date</lastmod>
      <type>post|page</type>
      <category>Category Name</category>
      <tags>tag1, tag2</tags>
    </post>
    ...
  </posts>
</ai-sitemap>
```

**Cache**: 12 hours via WordPress transient (`aivb_sitemap_cache`). Cleared whenever a post or page is saved, unpublished or trashed.

**Headers**: `Content-Type: application/xml`, `X-Robots-Tag: noindex`

---

## 3. AI Discovery JSON Feed

**Endpoint**: `/ai-feed.json`

A JSON index of up to 500 published posts and pages. Password-protected, private and draft posts are left out.

**Structure**:

```json
{
  "version": "1.0",
  "generated": "ISO-8601 timestamp",
  "plugin": "AI Visibility Booster by Akshay Bhimani",
  "site": {
    "name": "Site Name",
    "description": "Site description",
    "url": "https://example.com/",
    "language": "en-US"
  },
  "total_posts": 42,
  "posts": [
    {
      "id": 1,
      "title": "Post Title",
      "url": "https://example.com/post-slug/",
      "summary": "First-paragraph summary",
      "date": "ISO-8601",
      "modified": "ISO-8601",
      "type": "post",
      "categories": ["Category"],
      "tags": ["tag1", "tag2"]
    }
  ]
}
```

**Privacy**: Author names are excluded by design to minimise PII exposure.

**Cache**: 12 hours, auto-flushed on post save.

**Headers**: `Content-Type: application/json`, `Access-Control-Allow-Origin: *`

---

## 4. AI Knowledge Graph

**Endpoint**: `/ai-knowledge.json`

An entity graph describing the site's structure and content relationships. Intended to help AI systems understand topical authority, site navigation, and content categorisation.

**Includes**:
- Site metadata (name, description, URL, language, logo)
- Top-level navigation pages
- Topics (categories with post counts and descriptions)
- Concepts (tags with post counts)
- Content entities (up to 200 most-recently-modified posts/pages with full metadata)

**Additional output**: A `WebSite` JSON-LD schema block with `SearchAction` is output on the homepage when no major SEO plugin is active.

**Cache**: 12 hours, auto-flushed on post save.

---

## 5. JSON-LD Structured Data

Outputs multiple JSON-LD schema types in the page `<head>`.

### Article / WebPage Schema
Output on single posts (`Article`) and pages (`WebPage`). Includes:
- `headline`, `description`, `url`, `datePublished`, `dateModified`
- `inLanguage`, `image` (featured image)
- `author` as Organization (prevents individual user data exposure)
- `keywords` from categories and tags
- `isPartOf` WebSite reference

### FAQPage Schema
Auto-detected from post content using two patterns:
1. `<strong>Q: question?</strong>` … `<p>A: answer</p>`
2. `<h2>/<h3>/<h4>` ending with `?` followed by a `<p>` answer

Supports up to 20 FAQ items per post.

### Organization Schema
Output on the site homepage with name, description, URL, and logo (if set).

### WebSite Schema
Output on the homepage with `SearchAction` / sitelinks searchbox hint.

**SEO plugin compatibility**: When Yoast, RankMath, or AIOSEO is detected, only `Article` schema on single posts is output. Organization, WebSite, WebPage and FAQPage schemas are skipped. The SEO plugin may print its own Article schema as well; turn off Structured Data in the settings if you do not want both.

FAQPage schema is not output for password-protected posts.

---

## 6. AI Visibility Score

A composite score (0–100) measuring how AI-readable a post or page is.

| Criterion | Max Points | Scoring Rule |
|---|---|---|
| H1 Heading | 10 | An `<h1>` inside the post content = 10, absent = 0. The theme's title heading is not counted. |
| H2 Subheadings | 10 | Present = 10, absent = 0 |
| AI Summary stored | 20 | Stored = 20, absent = 0 |
| Internal links | 20 | ≥10 links = 20, ≥5 = 15, ≥1 = 10, 0 = 0. Only links that contain the full site URL are counted. |
| Content word count | 20 | ≥1000 = 20, ≥600 = 15, ≥300 = 10, ≥100 = 5 |
| Structured data | 20 | Schema enabled or SEO plugin active = 20 |

**Labels**: Excellent (80–100), Good (60–79), Moderate (40–59), Needs Improvement (0–39)

**Gutenberg sidebar**: Displays score, label, progress bar, and per-criterion breakdown with improvement tips.

**Classic Editor meta box**: Same display, rendered server-side.

**REST API**: `GET /wp-json/aivb/v1/score/{post_id}` returns full score data. The user must be logged in and able to edit that post.

The score is calculated each time it is requested. It is not stored.

---

## 7. AI Markdown Export

**Endpoint**: `/ai-markdown/{post-slug}`

Serves any published post or top-level page as Markdown at runtime (no files written to disk). Child pages are not supported. Password-protected, private and draft posts return a 404.

**Output format**:

```markdown
# Post Title

**URL:** https://example.com/post-slug/
**Published:** YYYY-MM-DD
**Modified:** YYYY-MM-DD
**Category:** Category Name

## Summary

First-paragraph summary.

---

## Content

Full post content converted to Markdown.

---

## FAQs

**Q: Detected FAQ question?**

A: Detected FAQ answer.

---

*Generated by AI Visibility Booster — Plugin by Akshay Bhimani*
```

**HTML-to-Markdown conversion** handles: headings (H1–H6), bold, italic, inline code, fenced code blocks, links, images, blockquotes, horizontal rules, line breaks, unordered and ordered lists, basic tables, paragraphs. Dangerous elements (scripts, styles, noscript, HTML comments) are stripped before conversion.

**Headers**: `Content-Type: text/markdown`, `Content-Disposition: inline; filename="{slug}.md"`, `X-Robots-Tag: noindex`

---

## 8. Admin Dashboard

Located at **WordPress Admin → AI Visibility**.

**Status cards**:
- Average AI Visibility Score across the 50 most recently modified posts and pages
- Posts with AI Summary count + bulk generate button
- AI Sitemap status + live URL
- AI Discovery Feed status + live URL
- Knowledge Graph status + live URL
- Structured Data status + SEO plugin notice
- Bot Analytics status, 30-day visit count and link to the analytics page

**Feature toggles** (saved via `admin_post`):
- AI Content Summaries
- AI Sitemap
- AI Discovery Feed
- AI Knowledge Graph
- Structured Data
- AI Markdown Export
- AI Bot Analytics

All toggles are saved as WordPress options and flush rewrite rules on save. The page and the bulk generate button require the `manage_options` capability (Administrator).

---

## 9. Bot Analytics

Located at **WordPress Admin → AI Visibility → Bot Analytics**.

**What is logged**

On each front-end request the plugin compares the User-Agent header with a list of 17 known fragments: ClaudeBot, anthropic-ai, OAI-SearchBot, GPTBot, ChatGPT-User, AI_Citation, PerplexityBot, Bytespider, cohere-ai, Applebot, YouBot, Diffbot, meta-externalagent, Google-Extended, Googlebot, bingbot and CCBot. If one matches, a row is written to the `{prefix}aivb_bot_visits` table with the bot name, company, type, user agent, page URL, UTC time, a salted SHA-256 hash of the IP address and a session id. Requests that do not match are not logged.

**What is shown**

- Totals for 7, 14, 30 or 90 days, or all time: visits, pages, bot types, citation-type visits, sessions
- Daily bar chart for up to 14 days
- Top 10 bots with visits, pages and sessions
- The 25 most recent visits

**Sessions**: visits from the same bot and IP hash inside the same 30-minute clock window share one session id.

**Citation type**: `ChatGPT-User` and `AI_Citation` are labelled "citation". All others are labelled "crawl". A citation-type visit means that user agent requested a page. It does not prove the page was quoted in an answer.

**Retention**: a weekly WP-Cron task deletes rows older than the `aivb_analytics_retention` option (default 90 days, minimum 7). There is no settings field for this option.

**Limits**: user agents are not verified, so a fake user agent is logged as the bot it claims to be. There is no cap on the number of rows.
