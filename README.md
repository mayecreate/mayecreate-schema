# MayeCreate Schema

Outputs one connected schema.org graph (JSON-LD) on every page. All the pieces reference each other by `@id`, so search engines see one organization, one website, and pages that belong to them.

**Requires:** WordPress 6.0+, PHP 7.4+, ACF PRO.

---

## Setup checklist (for interns)

1. Install and activate the plugin.
2. Deactivate any other schema plugin (Schema & Structured Data for WP, Schema Pro). Yoast, Slim SEO and Rank Math schema is switched off automatically.
3. Go to **Settings → Schema** and fill in:
   - **Business:** organization type, name (match the Google Business Profile exactly), short description, logo.
   - **Contact & Location:** address, phone, service area. Turn on **Hide street address** if the Google Business Profile hides it.
   - **Online Profiles:** Google Maps share link, then Facebook, LinkedIn, etc. one per line.
   - **Key Pages:** only if the About, Contact, Services or Team page has an unusual name.
   - **Post Types:** add a row for each post type the site uses (News, Projects, Resources, Events...). Pick its main page (the page with the posts block) and what each item is.
   - **Team:** nothing to do if the site uses the MC Team block. Otherwise, add staff here for the team page.
4. Give the Home, About, Contact and Services pages a short description (the **Schema** box in the page sidebar).
5. Test the homepage, Contact page and one service page in:
   - https://validator.schema.org (should show no errors)
   - https://search.google.com/test/rich-results

That's it. Every other page is handled automatically.

---

## Choosing the organization type

The settings page has a "Which one do I pick?" guide under the dropdown. The short version:

- **Has a Google Business Profile?** Pick a local business type (General contractor, a trade, Law firm, Local business (general)...).
- **No profile, no walk-in customers?** Pick from Organizations (Organization, Nonprofit, College or university, Government, News / media publisher).
- **Nothing fits?** Use the general option, or look up a type at https://schema.org/LocalBusiness and choose *Other*. Don't use ProfessionalService; schema.org retired it.

## How pages are detected

| Page | Output |
|---|---|
| Front page | `WebPage` about the organization |
| About page | `AboutPage` |
| Contact page | `ContactPage` |
| Services page | `CollectionPage` + `ItemList` of its child pages |
| Pages under Services | `WebPage` + `Service` (provider = organization). A child that has its own children becomes a collection page. |
| Post type main page (set on Post Types tab) | `CollectionPage` + `ItemList` of that type's items |
| Post type items | `WebPage`, or `WebPage` + `Article` / `DigitalDocument` / `Event`, as set on Post Types tab |
| Resources page (only if resources are child pages) | `CollectionPage`; child pages become `Article` |
| Any page with MC Team blocks | `Person` for each team member (name, title, headshot, email, phone, bio) + `ItemList`, read from the blocks |
| Team page without team blocks | `WebPage` + `ItemList` of `Person` from Settings → Schema → Team |
| Blog posts | `WebPage` + `BlogPosting` + author `Person`. Breadcrumb goes through the Posts main page (Post Types tab, or Settings → Reading). |
| Blog page, categories, tags | `CollectionPage` + `ItemList` |
| Author archive | `ProfilePage` + `Person` |
| Everything else | `WebPage` |
| Search, 404 | Organization and WebSite only |

Key pages are found by the setting, or by slug if blank: `about`, `about-us`, `contact`, `contact-us`, `services`, `what-we-do`, `resources`, `team`, `our-team`, `staff`, `meet-the-team`, `leadership`. The blog's main page is the Posts row on the Post Types tab, then the Posts page in Settings → Reading, then a page with the slug `blog`, `news`, `articles` or `insights`. Other post types' main pages are the Post Types tab setting, then a page whose slug matches the post type (e.g. `/projects/` for `projects`), its URL base, or its plural label.

**Team blocks** are found on any page, including inside groups and columns. By default the plugin matches any ACF block with "team" in its name (e.g. `acf/mc-team`); set an exact name on the Team tab if another block matches by accident. It reads these fields, first match wins: name (`name`, `full_name`), title (`title`, `job_title`, `position`), photo (`headshot`, `photo`, `image`), `email`, `phone`, `bio`, `linkedin`. Works for one block per person or a repeater (`people_0_name`, `people_1_name`...). Change the field names with the `mcs_team_block_fields` filter. Blocks inside synced patterns aren't read.

**Resources** (any post type with "resource" in its name, or set to *Resource* on the Post Types tab) are decided per item, following the theme's rules:

| Resource has | Visitors go to | Schema |
|---|---|---|
| No content + file | The file | `DigitalDocument`; the Resources list points to the file |
| No content + outside link | The other site | `WebPage`; the Resources list points to the link |
| Content + file or link | The resource page, with a button | `Article` + `relatedLink` to the file or link |
| Content, no link | The resource page | `Article` |

The link comes from the theme's `alternate_link` field (a URL, ACF link, or media file). A link to an uploaded file or a document (.pdf, .docx, .xlsx...) counts as a download; anything else is an outside link. Other common field names (`resource_file`, `file`, `external_url`, `link`...) are checked as a fallback, or set exact names on the Post Types tab. Fields inside an ACF group (saved as `<group>_alternate_link`) are found too.

**Events** read the theme's ACF fields: `event_start_date`, `event_end_date`, `event_start_time`, `event_end_time`, `event_location`, `registration_link`, `registration_title`, `registration_type`. Times use the site timezone. A location that's a URL or says online/virtual/Zoom/webinar becomes a `VirtualLocation`; a blank location falls back to the organization's address. An event with no start date stays a plain WebPage. Items of any post type that isn't on the Post Types tab become events automatically when `event_start_date` is filled in; add the post type on the tab as *Standard page* to turn that off. `registration_type`: "link" points the registration offer to `registration_link`; "form" points it to the event page itself, where the form is embedded. Different field names on a site: use the `mcs_event_fields` filter.

**Per-page override:** the Schema box on each page/post has a *Page type* dropdown (Standard, About, Contact, Collection, Service, Article, Downloadable document, Staff profile, Team) and a *No schema on this page* switch.

**Collection pages (recommended setup):** on a page with a posts block, set *Page type* to **Collection** and *Post type* to the type of posts it shows (or *Child pages*). This is saved with the page, so it survives renames and URL changes, and that post type's items use it in their breadcrumbs. Order of precedence for a post type's main page: Post Types tab → a page marked Collection of that type → slug match.

## ID pattern

All IDs are `<url>#<fragment>`, home URL always with a trailing slash:

- `https://site.com/#organization`, `/#website`, `/#logo`
- `<page-url>#webpage`, `#breadcrumb`, `#primaryimage`, `#service`, `#article`, `#document`, `#event`, `#person`, `#itemlist`, `#team`

A staff member with a profile page uses `<profile-url>#person` both on the team page and on their own page, so the two connect.

---

## For developers

**Map existing ACF fields** instead of re-entering data. Keys are the setting names without the `mcs_` prefix (`street`, `city`, `state`, `zip`, `phone`, `email`, `name`, `logo`, `org_type`, ...):

```php
add_filter( 'mcs_option', function ( $value, $key ) {
	$map = array(
		'street' => 'company_address',
		'city'   => 'company_city',
		'state'  => 'company_state',
		'zip'    => 'company_zip',
		'phone'  => 'company_phone',
		'email'  => 'company_email',
	);
	if ( empty( $value ) && isset( $map[ $key ] ) ) {
		return get_field( $map[ $key ], 'option' );
	}
	return $value;
}, 10, 2 );
```

**Other hooks**

- `mcs_graph` ($graph, $ctx): add, change or remove nodes before output.
- `mcs_node_organization`, `mcs_node_website`, `mcs_node_webpage`, `mcs_node_breadcrumbs`, `mcs_node_author`, `mcs_nodes_for_kind`: change a single node.
- `mcs_context` ($ctx): change how a page is classified (`$ctx['kind']`).
- `mcs_key_page` ($id, $which): change which page counts as about/contact/services/resources/team.
- `mcs_post_type_map` ($map): change post type settings in code.
- `mcs_event_fields` ($fields): change which ACF fields events read from.
- `mcs_team_block_fields`, `mcs_team_block_names`, `mcs_team_from_blocks`: control how staff are read from blocks.
- `mcs_collection_limit` (20): how many items a collection page lists.
- `mcs_enabled`: return false to stop output.

**Notes**

- Output goes in `<head>` as a single `<script type="application/ld+json" class="mayecreate-schema">`.
- Empty values are dropped, so there's never `"description": ""` or `"sameAs": []`.
- Placeholder images (file name containing `default` or `placeholder`) are left out of featured images and staff photos. Change with the `mcs_is_placeholder` filter.
- Page descriptions come from the Schema box, then the excerpt. Content is never auto-trimmed (avoids shortcodes like `[formidable id=…]` leaking in).
- No sitelinks SearchAction (Google retired it). No FAQ/HowTo.
- Check that the theme isn't adding its own schema or microdata; if it is, turn that off in the theme settings.
