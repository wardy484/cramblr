---
name: Installable PWA
overview: Add a web app manifest and optional meta tags so the app can be installed as a PWA from the browser. No service worker or offline support is required—Chrome’s install criteria no longer require a service worker (since Chrome 108/112).
todos: []
isProject: false
---

# Installable PWA Plan

## Current state

- **Head**: Shared in [resources/views/partials/head.blade.php](resources/views/partials/head.blade.php), included by the app layout ([resources/views/layouts/app/sidebar.blade.php](resources/views/layouts/app/sidebar.blade.php)) and auth layout ([resources/views/layouts/auth/simple.blade.php](resources/views/layouts/auth/simple.blade.php)).
- **Icons**: [public/apple-touch-icon.png](public/apple-touch-icon.png), [public/favicon.ico](public/favicon.ico), [public/favicon.svg](public/favicon.svg).
- **App name**: `config('app.name')` (e.g. "Cramblr").

## Installability requirements (no service worker)

Chrome’s install rules only need:

1. **HTTPS** (you already need this in production).
2. **Web app manifest** linked from the page, with:

   - `name` or `short_name`
   - `start_url`
   - `display` in `standalone` | `minimal-ui` | `fullscreen` | `window-controls-overlay`
   - **Icons**: at least 192×192 and 512×512 (type and purpose as per spec).

A service worker is **not** required for install (since Chrome 108 mobile / 112 desktop).

---

## Implementation

### 1. Web app manifest

Add **`public/manifest.json`** (or a Laravel route that returns JSON).

**Option A – Static file (simplest)**

Create `public/manifest.json` with:

- `name`, `short_name` from a fixed value or by copying from `.env` during deploy.
- `start_url`: `"/"` or `"/dashboard"` (depends whether you want installs to open home or dashboard).
- `display`: `"standalone"`
- `background_color`, `theme_color`: e.g. `#18181b` (zinc-900) to match the dark UI in [resources/views/layouts/app/sidebar.blade.php](resources/views/layouts/app/sidebar.blade.php) (`class="dark"`, `bg-zinc-800`).
- `icons`: at least two entries, 192×192 and 512×512, `purpose: "any"` (or `"any maskable"` if you add maskable icons later).

**Icons**: You only have `apple-touch-icon.png`. Use it for both 192 and 512 in the manifest so the app is installable immediately. For better quality on splash/install UI, you can add real 192×192 and 512×512 PNGs later (e.g. `public/icons/icon-192.png`, `icon-512.png`) and point the manifest to those.

**Option B – Dynamic manifest (uses config)**

Add a route and controller (or inline closure) that returns JSON with `name`/`short_name` from `config('app.name')`, `start_url` from `url('/')` or `url('/dashboard')`, and the same `display`, `theme_color`, `background_color`, and `icons`. Link the page to this route as the manifest URL (e.g. `Route::get('manifest.json', ...)`).

Recommendation: start with **Option A** for fewer moving parts; switch to B if you need environment-specific names or start URLs.

### 2. Head changes

In [resources/views/partials/head.blade.php](resources/views/partials/head.blade.php):

- Add:
  - `<link rel="manifest" href="{{ asset('manifest.json') }}">`  

(or `href="{{ route('manifest') }}"` if you use a route).

  - `<meta name="theme-color" content="#18181b">`  

(or match whatever you put in `theme_color` in the manifest).

That makes every page using the shared head (app + auth) installable.

### 3. Welcome page (optional)

[welcome.blade.php](resources/views/welcome.blade.php) has its own `<head>` and does not include the partial. If you want “Install” to show on the landing page too, add the same manifest link and `theme-color` meta there.

### 4. No service worker

Skip service worker and offline logic. The app will still be installable as long as the manifest and meta are correct and the site is served over HTTPS.

---

## Files to add/change

| Action | File |

|--------|------|

| Create | `public/manifest.json` |

| Edit | [resources/views/partials/head.blade.php](resources/views/partials/head.blade.php) (manifest link + theme-color) |

| Optional | [resources/views/welcome.blade.php](resources/views/welcome.blade.php) (same meta/link if install from landing is desired) |

---

## Manifest shape (reference)

```json
{
  "name": "Cramblr",
  "short_name": "Cramblr",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#18181b",
  "theme_color": "#18181b",
  "icons": [
    { "src": "/apple-touch-icon.png", "sizes": "192x192", "type": "image/png", "purpose": "any" },
    { "src": "/apple-touch-icon.png", "sizes": "512x512", "type": "image/png", "purpose": "any" }
  ]
}
```

If `apple-touch-icon.png` is not 512×512, install will still work; for best results add proper 192×192 and 512×512 assets and point `icons` to them.

---

## Testing

- Deploy or serve over HTTPS (or use Chrome with localhost).
- Open DevTools → Application → Manifest; confirm manifest loads and has no errors.
- Use “Install” / “Add to Home Screen” from the browser and confirm the app installs and opens with the chosen `start_url` and theme.

---

## Summary

- Add `public/manifest.json` with name, short_name, start_url, display, theme/background colors, and 192/512 icon entries (reusing `apple-touch-icon.png` is enough to start).
- In [resources/views/partials/head.blade.php](resources/views/partials/head.blade.php), add the manifest `<link>` and `theme-color` meta.
- Optionally add the same to the welcome view.
- No service worker or offline behavior required for installability.