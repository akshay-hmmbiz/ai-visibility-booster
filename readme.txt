=== AI Visibility Booster ===
Contributors: akshaybhimani
Tags: ai, llm, seo, schema, sitemap
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gives AI systems clean, structured versions of your content: AI sitemap, JSON feed, Markdown export, schema and a log of AI bot visits.

== Description ==

AI Visibility Booster makes your WordPress content easier for AI systems (ChatGPT, Claude, Gemini, Perplexity and others) to read. It does not call any external service and needs no account or API key.

= Features =

* **AI Content Summaries** - stores the first paragraph of each post as a short summary and prints `<meta name="ai-summary">` in the page head.
* **AI Sitemap** - serves `/ai-sitemap.xml`, a custom XML list of published posts and pages with title, summary, dates, category and tags.
* **AI Discovery Feed** - serves `/ai-feed.json` with the same content in JSON.
* **AI Knowledge Graph** - serves `/ai-knowledge.json` with site details, top-level pages, categories, tags and content.
* **AI Markdown Export** - serves any published post or top-level page as Markdown at `/ai-markdown/{post-slug}`.
* **JSON-LD Structured Data** - prints Article, WebPage, FAQPage, Organization and WebSite schema.
* **AI Visibility Score** - a 0 to 100 checklist score per post, shown in the block editor sidebar and the Classic Editor.
* **Bot Analytics** - logs visits from known AI and search bots and shows them on a dashboard.
* **Admin Dashboard** - status cards, endpoint links and an on/off setting for each feature.

= Compatibility =

When Yoast SEO, Rank Math or All in One SEO is active, the plugin does not print a meta description and prints Article schema on single posts only. Your SEO plugin may also print Article schema. Turn off Structured Data in the plugin settings if you do not want both.

= Privacy =

The plugin makes no external requests and sets no cookies. Bot Analytics logs only requests from known bot user agents. For each one it stores the bot name, user agent, page URL, time and a salted SHA-256 hash of the IP address in your own database. Rows older than 90 days are deleted weekly. Normal visitors are not logged.

= Limits =

No plugin can guarantee that an AI system will crawl, index or cite your site. The endpoint formats are this plugin's own and are not standards. Only posts and pages are included.

= Credits =

Designed, built and tested by Akshay Bhimani, Senior SEO Strategist.
GitHub: https://github.com/akshay-hmmbiz
LinkedIn: https://in.linkedin.com/in/akshay-bhimani

== Installation ==

1. Download the ZIP from https://github.com/akshay-hmmbiz/ai-visibility-booster (Code, then Download ZIP).
2. In WordPress go to Plugins, Add New, Upload Plugin. Choose the ZIP, install and activate.
3. Click **AI Visibility** in the admin menu. The settings are on that page.
4. Click **Bulk Generate Missing Summaries** to process existing content.
5. Open `/ai-sitemap.xml` and `/ai-feed.json` on your site to check they load. If they show a 404, go to Settings, Permalinks and click Save Changes.

== Frequently Asked Questions ==

= Does this plugin conflict with Yoast SEO or Rank Math? =

It detects Yoast SEO, Rank Math and All in One SEO. When one is active it skips the meta description and the Organization, WebSite, WebPage and FAQPage schema. It still prints one Article block on single posts.

= Does the plugin call any external APIs? =

No. Summaries are made on your server from the first paragraph of the post.

= Will the AI sitemap affect my SEO sitemap? =

No. `/ai-sitemap.xml` is a separate file in a custom format. It is sent with an `X-Robots-Tag: noindex` header and is not added to `robots.txt`.

= What is the AI Visibility Score based on? =

H1 in content (10), H2 in content (10), summary stored (20), internal links (up to 20), word count (up to 20), schema enabled (20).

= Can I disable individual features? =

Yes. Each feature has a checkbox on the AI Visibility page.

= Do the endpoints expose private content? =

No. Only posts and pages with status "publish" and no password are included. Drafts, private posts and password-protected posts are left out.

= How long are the sitemap, feed and knowledge graph cached? =

12 hours. The cache is cleared whenever a post or page is saved, unpublished or trashed.

= Does the plugin add anything to robots.txt? =

No.

= Does deleting the plugin remove its data? =

No. Settings, stored summaries and the bot log table stay in the database.

== Screenshots ==

1. Admin dashboard with status cards and feature settings.
2. Block editor sidebar with the AI Visibility Score.
3. Bot Analytics page.

== Changelog ==

= 1.1.1 =
* Security: password-protected posts are excluded from all endpoints, and their summary and FAQ text are no longer printed in the page head.
* Security: the score REST route checks that the user can edit the requested post.
* Security: bulk summary generation requires the manage_options capability.
* Fix: endpoint caches are cleared when a post is unpublished, trashed or made private.
* Fix: saving settings no longer ends on a blank page.
* Fix: the AI Content Summaries setting now controls the summary meta tag.
* Fix: the bulk generate message reports the real count.
* Fix: bot visit times are stored in UTC.
* Fix: the Bot Analytics page loads its stylesheet.
* Fix: Markdown export tag matching (br/b, img/i, pre/p, thead/th).

= 1.1.0 =
* New: Bot Analytics page with daily chart, top bots and recent activity.
* New: 30-minute crawl session grouping.
* New: weekly cleanup of old bot log rows.
* New: analytics on/off setting.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.1.1 =
Security and bug fixes. Password-protected posts are no longer exposed through the AI endpoints.
