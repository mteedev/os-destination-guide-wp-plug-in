# os-destination-guide-wp-plug-in

Opensimulator Destination Guide WP Plug-In

- Tested on Opensimulator 9.2.2
- WordPress Version 6.8.3 or greater
- Uses w4os WordPress Plug-In https://w4os.org/ Version 2.10.0-beta-1

A quick and easy way to build a browsable destination guide for your grid — enter a Name, Description, Category, SLURL, and Photo for each destination, and get a category-tile browsing interface for both your website and, via w4os, your client's Destinations splash panel in-world.

## Features

- **Category-tile browsing** — visitors click a category tile (with thumbnail and live item count), then click a destination to see a detail view with image, description, and a one-click teleport
- **Admin dashboard** with AJAX add/edit/delete — no page reloads
- **Category management** — add, rename, reorder, and delete categories from the dashboard (see below); no code editing required
- `[destination_guide]` **shortcode** for embedding the guide on any WordPress page
- **Clean `/destination-guide/` endpoint** — outputs a bare HTML page with no theme wrapper, built specifically for the w4os in-world Destinations module

## Installation

Install the plugin as any normal WordPress plugin — upload it, activate it.

You'll see the plugin on the left admin menu:

<img width="610" height="483" alt="Screenshot1" src="https://github.com/user-attachments/assets/ba85b1e7-0d5c-41a1-b939-debd659239f9" />

Click on **Destination Guide** to access the dashboard.

## Adding a Destination

<img width="1749" height="765" alt="Screenshot2" src="https://github.com/user-attachments/assets/a4f6832a-6c7e-4204-a4a1-23555088e52f" />

Enter the information and click **Save Destination**. The Category dropdown is populated from your current category list (see Managing Categories below) — if you need a new one, there's a link right on the form to jump straight to the Categories screen.

As you build your destinations, scroll down to view your current listings:

<img width="1743" height="867" alt="Screenshot3" src="https://github.com/user-attachments/assets/0b31ca46-8665-43f1-ae44-f1d1f25e0192" />

Here you can Edit, Delete, and view all of your current listings. Clicking **Edit** takes you to the form at the top with all information filled in so you can update the photo, description, SLURL, or name, and save your changes with **Save Destination**.

Your destinations are stored in your WordPress database.

## Managing Categories

*(Not pictured in the screenshots above — added in v1.1.0.)*

Under **Destination Guide → Categories**, you can:

- **Add** a new category by name
- **Rename** a category — every destination using it updates automatically, nothing gets orphaned
- **Reorder** categories with the up/down arrows — this controls the actual display order of the tiles, both on the web and in-world
- **Delete** a category — only allowed once no destinations are assigned to it, so you can't accidentally strand entries

There's a configurable cap on total categories (default: 20). This isn't arbitrary — categories render as a horizontal-scrolling tile strip inside the in-world viewer UI, and that strip gets unwieldy fast. The cap keeps the browsing experience usable; it's a single constant in the plugin file if you need to adjust it for your grid.

## Displaying Your Destination Guide

To display your Destination Guide, create a WordPress page — it doesn't need to be published in a menu; this is primarily for access by w4os, so it's not necessary to have it publicly accessible unless you'd like to display it on your site too.

After creating the page, add the shortcode to it:

[destination_guide]


From the Edit window of the page, or your page list, click **View Page** and copy the URL. You should see a Destination Guide in your browser like this:

<img width="1691" height="341" alt="Screenshot4" src="https://github.com/user-attachments/assets/4fab9b25-c3eb-427c-a07c-6c2b0e3fa388" />

Paste the URL of your Destination Guide into w4os's **Source** field and follow its instructions for wiring this into your OpenSimulator grid's Destinations module.

## In-World Usage

In-world, go to your toolbar — or, if you already have the shortcut set up on the side of your screen, simply click it. Otherwise, select **World → Destinations** and you'll see the Destination Guide pop up in your client:

![destinations](https://github.com/user-attachments/assets/d05b7fb4-7aa8-43c7-9c48-3018d5cfc207)

## SLURL Format

Always use the **`hop://`** scheme, not `secondlife://` — `secondlife://` has no way to specify a grid hostname and will fail to resolve on any grid other than the one you're currently logged into.

If a region name contains a space, it must be URL-encoded as `%20` (e.g. `hop://your.grid.com:8002/Region%20Name/128/128/25`). The safest way to get this right every time is to use **Copy SLURL** from the in-world World Map rather than typing the region name by hand — this guarantees correct encoding.

## Troubleshooting

**"No regions found with that name" when teleporting**
Almost always a SLURL encoding issue — check that spaces in the region name are `%20`, not a literal space or missing entirely. Re-copy the SLURL from the in-world map if unsure.

**Teleport links don't open anything (Linux desktop browsers)**
Most Linux viewer installs don't register `hop://`/`secondlife://` as OS-level protocol handlers automatically the way Windows installers do. This needs to be set up manually via a `.desktop` file with the appropriate `MimeType=` entry and `xdg-mime default`. This is a one-time OS-level setup per machine, not a plugin issue.

**Shortcode showing up on every page**
This usually means the shortcode was added to a shared block template (e.g. the theme's default "Page" template in a block/FSE theme) rather than to a specific page's content. Check **Appearance → Editor → Templates** and make sure the shortcode lives only in the individual page(s) you intend it for.

**Can't add a new category**
You've hit the category cap (default 20). Delete an unused category, or adjust the cap in the plugin file if your use case genuinely needs more.

## License

GPL-3.0


