=== Foxi Builder ===
Contributors: foxi
Tags: page builder, visual editor, blocks, seo, starter sites
Requires at least: 6.6
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A visual page builder for WordPress: click-to-edit pages, a Brand Kit that re-themes the whole site, Swap Mode for re-skinning starter sites, built-in SEO and schema, and live Insights.

== Description ==

Foxi Builder adds Foxi Studio to the WordPress editor: a side rail of tools around a live, full-width view of your page.

= Build =
* 40+ blocks: sections, columns, headings, text, buttons, icons, icon lists, images, galleries, video, maps, sliders, carousels, accordions/FAQ, tabs, counters, star ratings, flip boxes, contact forms, booking widget, Google reviews, posts, breadcrumbs, search, page title and more.
* Every block has Content, Style, Motion and Advanced tabs, with separate desktop, tablet and phone settings, and hover states.
* Backgrounds: colour, gradient, image (with focal point and parallax), video (YouTube or file) and slideshow, plus overlays, patterns and shape dividers.
* Motion: entrance animations, staggered children, hover effects, looping effects and a typewriter heading.
* Section library and page templates from the starter sites.

= Brand Kit =
One place for colours, fonts, type scale, buttons, corners and spacing. Change it and every page updates. Business details (name, phone, address, hours, socials) fill in everywhere via tokens like {{phone}}.

= Swap Mode =
Every image and line of text on a page in one list, so re-skinning a starter site for a new client takes minutes.

= Headers, footers and layouts =
Build headers (logo left, logo centred, menu on its own row, top bar, sticky, see-through) and footers visually. The blog, categories, search results, single posts and the 404 page use Foxi layouts too, so the whole site matches.

= SEO =
Meta titles and descriptions with a Google preview, focus keyword checks, social sharing previews, canonical and noindex controls, business schema, FAQ schema from accordions, Article and breadcrumb schema, and custom JSON-LD per page. Pages hidden from Google are left out of the sitemap.

= Insights =
A live audit of the page you are editing: SEO, accessibility (alt text, heading order, link text, form labels), colour contrast (WCAG AA/AAA) and screen sizes (anything sticking out on phones, tiny text, small tap targets). Click an issue to jump to the block.

= Starter sites and site kits =
Import the Panorama Retreat template: the full panoramaretreat.com.au website (16 pages, header, footer, menu and Brand Kit) rebuilt in Foxi blocks, with placeholder images. Change the Business details and Brand Kit and it becomes the next property's site. Export any Foxi site as a site kit and import it on another WordPress site; images are copied across.

== Installation ==

1. Plugins › Add New › Upload Plugin, choose foxi-builder.zip, Install, Activate.
2. Foxi › Starter sites › Import Panorama Retreat (or start with a blank page).
3. Foxi › Brand & business: set colours, fonts, logo and contact details.
4. Edit any page: Foxi Studio opens automatically.

== Changelog ==

= 1.7.2 =
* Social media works with new Meta apps ("Unified login"), which no longer offer the Instagram Login token. Connect with a System User token from Meta Business settings: it never expires and needs no App ID or secret. Page tokens work too, and the Instagram account linked to the Page comes with it.
* The Facebook Page connection is now first and marked as recommended. The Instagram-only token box is kept for older Meta apps.

= 1.7.1 =
* Social media: connecting a Facebook Page now also brings in the Instagram Business account linked to it, so you don't need a separate Instagram token.
* Clearer messages when a token can't be read, has expired, or is the wrong kind (Facebook tokens start with EAA, Instagram tokens with IG). Spaces, quotes and "access_token=" copied with a token are removed automatically.

= 1.7.0 =
* New Social Feed block: your latest Instagram and/or Facebook Page posts, updated automatically, in six designs (grid with captions on hover, cards, carousel, collage, masonry and a full-width strip), with an optional @name and follow button. Posts can also be added by hand, e.g. for LinkedIn. Connect accounts in Foxi › Settings › Social media; the Instagram connection renews itself.
* Insights: colour-contrast problems now list each piece of text with its real colours and offer one-click fixes: the closest colour that passes, a stronger one, or the nearest passing Brand Kit colour. Mark anything you're happy with as fine and it stops counting. Every check has an "i" button explaining what it means and why it matters.
* Pages, posts and headers/footers now need a name before they can be saved or published, so the page address isn't left as a number.
* Images use the alt text from the Media Library when the block's own alt text is empty, and swapping an image no longer keeps the old picture's alt text.
* Fixed the Google Reviews spotlight quote sitting left of centre between the arrows.

= 1.6.0 =
* New custom scrollbar (Foxi › Settings › Scrollbar): Standard, Rounded, Floating, Gradient, Square or Minimal, in thin, regular or wide, with your own bar, track and gradient colours (brand colours follow your Brand Kit). Each style has a live preview.

= 1.5.0 =
* New smooth scrolling (Foxi › Settings › Smooth scrolling: Subtle, Medium or Strong). Mouse-wheel and trackpad scrolling glides and eases to a stop. Phones keep their own scrolling, visitors who prefer reduced motion get normal scrolling, and pop-ups, menus and the lightbox are left alone. It's only loaded when switched on.

= 1.4.1 =
* Text sizes now use rem instead of px, so they follow each visitor's browser text-size setting. Page designs look the same at the normal setting.
* Large text sizes set in the Style tab now shrink smoothly on tablets and phones unless you've set a tablet or phone size yourself. Brand Kit sizes, section spacing and side margins were already fluid and now use rem too.

= 1.4.0 =
* New Foxi › Forms: build any number of forms (contact, accommodation and event enquiries, or start blank) and use them on any page with the Contact Form block.
* 16 field types: short and long text, name, email, phone, number, website, dropdown, pick one, pick several, agreement tick box, date, time, file upload, hidden value, headings and text blocks, plus multi-page forms with a progress bar.
* Show or hide fields (and whole pages) based on earlier answers; field widths, help text, default values (including from the page address) and custom error messages.
* Emails: as many as you like per form, each with its own recipients, CC/BCC, reply-to, subject and message using answers from the form, rules for when to send, and uploaded files attached.
* After sending: show a message, go to a thank-you page or to any web address.
* Entries: read, star, search, mark unread, delete and export to CSV, with a record of each email sent or why it failed. Unread count in the Foxi menu.
* Email delivery through Gmail, Microsoft 365 or any SMTP server, with a test email button. Passwords are stored encrypted.
* Spam protection: hidden traps, per-person limits, link and word blocking, plus reCAPTCHA v3, reCAPTCHA tick box, Cloudflare Turnstile or hCaptcha.
* Per-form limits, opening dates and logged-in-only forms; automatic deletion of old entries.
* Existing Contact Form blocks keep working and can be moved into Foxi › Forms with one click. The Panorama template now creates its forms in Foxi › Forms.
* Sections: new "Edge to edge" width with no side spacing (the old option is now called "Full width"), plus custom width and height.
* The Panorama offers section now runs edge to edge.
* Google Reviews: the "Reviews from Google · …" line is now off by default (switch "Say how reviews are picked" on to show it). The editor no longer shows carousel arrows when every review already fits, matching the live site.
* Fixed the spacing controls spilling out of the sidebar when editing each side separately.
* Fixed the sticky header flickering when the page is scrolled only slightly.

= 1.3.0 =
* New Google Reviews block: your Google rating and reviews, refreshed automatically (once a day by default) through the Google Places API.
* Six designs: carousel, grid, masonry wall, spotlight quote, list and rating badge, with quick colours and full styling.
* Filters: minimum stars, only reviews with comments, shortest comment, order (Google's, newest, highest), how many to show, and hide any review.
* Each review links to the reviewer and to the review on Google; overall rating with "Write a review" button.
* Foxi › Settings › Google: add your API key and find your listing by name. A block can use a different listing.
* The Panorama template's "What Our Guests Say" sections now show live Google reviews.

= 1.2.0 =
* New Booking Widget (replaces the Booking Bar; existing bars keep working): 7 looks (Pill, Card, Glass, Boxed, Outline, Solid band, Minimal), quick colours, corner rounding and glass blur, plus full font, border, shadow and background control on the Style tab.
* Layouts: bar, stacked (for a side column) or a single button that opens the form; phones can show stacked fields or just the button.
* Placement: left, centre, right or full width; overlap the bottom of the hero; optional booking bar that stays at the bottom of the screen after scrolling.
* Fields: arrival and departure or one date field, guests or adults and children (dropdown, pop-up counter or plus/minus), rooms and promo code. Every label and hint can be changed.
* New calendar: two months side by side, selected stay highlighted with a nights count, minimum stay, keyboard friendly, and a slide-up panel on phones.
* Booking engine settings are set once per site (Brand & business › Business details, or from the widget), with a SiteMinder preset and custom link details for any other engine.

= 1.1.1 =
* One-click updates: new versions now appear under Plugins with an "Update now" button, like plugins from wordpress.org.

= 1.1.0 =
* New Panorama Retreat template replaces the Retreat starter: every page of panoramaretreat.com.au rebuilt in Foxi, with placeholder photos.
* Brand Kit: optional accent font (used for the menu), plus Alata and Didact Gothic.
* Booking bar: icons, dividers, "2 Guests" wording, button icon and optional prefilled dates.
* Popup block: open any "#name" link as a popup (used for the event enquiry form).
* Video block: Facebook and Instagram reels, and a cover-only placeholder.
* New shape dividers (swoop, brush waves), a tall placeholder and logo/floor-plan placeholders.
* Fix: gallery tiles no longer grow past their shape when photos have different proportions.

= 1.0.0 =
* First release.
