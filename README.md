# Weather Display (OpenWeather)

This is a single-file, server-rendered weather dashboard designed for fixed displays (e-ink friendly), but configurable via URL parameters.

It fetches **current conditions** and the **5-day / 3-hour forecast** from **OpenWeather** and renders a static HTML page.

A live version of this widget, with support for all query parameters below, is available at: [https://dvr.cx/widgets/weather.php](https://dvr.cx/widgets/weather.php). Feel free to use it if you'd like, but you will need to provide your own OpenWeather API key via the `apikey=YOUR_API_KEY` URL parameter, which you can get for free from the links in the [Helpful OpenWeather Links](#helpful-openweather-links) section below. Though, I'd recommend hosting it yourself.

> [!NOTE]
> To use the live widget, you must provide your own OpenWeather API key, which you can get for free from the links in the [Helpful OpenWeather Links](#helpful-openweather-links) section below.
> If you are self-hosting this script, you can of course hardcode your API key into it if you prefer not to pass it via URL parameters.

## Background

This was originally designed specifically for the **Seeed reTerminal E Series 800x480 full color e-ink display**. Default layout and scaling is optimized for that resolution, but you can request any output size via the `res` or `w`, and `h` URL parameters. I wanted to have a low-power long-battery-life weather forecast display for my desk that I could glance to see current weather and a 5-day or hourly forecast. The defaults for this are optimized for the Seeed reTerminal, but I figured I'd make it easily configurable via URL parameters and open source it so others could use it as well. I mainly created this as I was unable to find an existing solution that met me needs and the limited options I found just would not work for me.

Originally, this was a simple HTML page with inline CSS and JS, but I found that the Seeed terminal using the default SeeedCraft software was unable to handle the JS-based rendering correctly since it was taking a snapshot of the initial page state, so I needed to have the server return a static and already rendered page instead.

> [!NOTE]
> If you need the web page as an image, you will need to use a third-party URL-to-image service or setup your own service for that, which you can then pass this to. This script does not provide image output natively. I was planning to add the ability to output the page as an image for stuff like ESPHome, but haven't gotten around to it yet. Might be a potential future feature. 🤷

## Table of Contents

- [Weather Display (OpenWeather)](#weather-display-openweather)
  - [Background](#background)
  - [Table of Contents](#table-of-contents)
  - [Requirements](#requirements)
  - [URL Parameter Reference](#url-parameter-reference)
  - [Helpful OpenWeather Links](#helpful-openweather-links)
  - [Quick Start](#quick-start)
  - [Location Parameters (Required: choose one)](#location-parameters-required-choose-one)
    - [Precedence (highest to lowest)](#precedence-highest-to-lowest)
    - [Option A: Coordinates](#option-a-coordinates)
    - [Option B: Zip Code](#option-b-zip-code)
    - [Option C: City details](#option-c-city-details)
    - [Option D: OpenWeather City ID](#option-d-openweather-city-id)
    - [Option E: Raw OpenWeather Query](#option-e-raw-openweather-query)
  - [API Key (Required)](#api-key-required)
  - [Display / Layout Parameters](#display--layout-parameters)
    - [Target Resolution + Scaling](#target-resolution--scaling)
    - [City Label Toggle](#city-label-toggle)
    - [Color Scheme / Palette](#color-scheme--palette)
  - [Forecast Mode Parameters](#forecast-mode-parameters)
    - [Daily vs Hourly](#daily-vs-hourly)
    - [Hourly Options](#hourly-options)
    - [Daily Options](#daily-options)
  - [Precipitation Display](#precipitation-display)
  - [Editing SVG Icons](#editing-svg-icons)
    - [Template placeholders](#template-placeholders)
  - [Debug Mode](#debug-mode)
  - [Location Behavior](#location-behavior)

## Requirements

- PHP (a simple local server is fine)
- An OpenWeather API key (required)

## URL Parameter Reference

All configuration for the widget is done via URL query parameters (ex: `weather.php?apikey=...&city=...`).

Notes:

- If you provide multiple location options, the first match wins per the [precedence list](#precedence-highest-to-lowest).
- Boolean parameters accept `1/0`, `true/false`, `yes/no`, `on/off`.

| Parameter | Type / allowed values | Default | Description | Examples |
|---|---|---|---|---|
| `apikey` | 32-char hex string | (required) | Your OpenWeather API key. If missing/invalid, the widget renders an error page. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&country=US` |
| `lat` | number (-90..90) | (none) | Location latitude. Used only when paired with `lon`. Highest precedence location option. | `weather.php?apikey=YOUR_KEY&lat=40.2601&lon=-74.2738` |
| `lon` | number (-180..180) | (none) | Location longitude. Used only when paired with `lat`. Highest precedence location option. | `weather.php?apikey=YOUR_KEY&lat=40.2601&lon=-74.2738` |
| `zip` | string | (none) | Location by postal code. If provided, `zipcountry` can be used to specify country. | `weather.php?apikey=YOUR_KEY&zip=07728` |
| `zipcountry` | string | `US` | Country code used with `zip`. | `weather.php?apikey=YOUR_KEY&zip=07728&zipcountry=US` |
| `city` | string | (none) | Location by city name. If provided, you can optionally also provide `state` and/or `country`. | `weather.php?apikey=YOUR_KEY&city=London&country=GB` |
| `state` | string | (none) | State/region used with `city` (commonly a US state abbreviation). | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&country=US` |
| `country` | string | `US` | Country code used with `city`/`state`. | `weather.php?apikey=YOUR_KEY&city=Paris&country=FR` |
| `cityid` | numeric string | (none) | Location by OpenWeather city id. | `weather.php?apikey=YOUR_KEY&cityid=5098278` |
| `q` | string | (none) | Raw OpenWeather `q=` query string (ex: `City,State,Country`). Lowest precedence location option. | `weather.php?apikey=YOUR_KEY&q=Freehold,NJ,US` |
| `autoloc` | boolean | enabled | When no explicit location parameters are provided, the script will (by default) attempt IP-based geolocation to determine approximate `lat/lon`. Use `autoloc=0` to disable. | `weather.php?apikey=YOUR_KEY&autoloc=0&city=Freehold&state=NJ` |
| `label` | boolean | disabled* | Shows the city name in a small corner overlay. *If auto-location is used and you did not explicitly set `label`, the label will be enabled automatically. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&label=1` |
| `res` | `WIDTHxHEIGHT` | `800x480` | Target output resolution. The UI is designed around 800x480 and scaled to fit. (Alternative to `w`/`h`.) | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&res=800x480` |
| `w` | integer pixels | `800` | Target output width (used with `h`). Alternative to `res`. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&w=1024&h=600` |
| `h` | integer pixels | `480` | Target output height (used with `w`). Alternative to `res`. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&w=1024&h=600` |
| `mono` | boolean | disabled | Forces a monochrome style (icons and UI use the foreground color). | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&mono=1` |
| `scheme` | `mono` | (none) | Convenience alias for palettes/modes. Currently `scheme=mono` is equivalent to `mono=1`. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&scheme=mono` |
| `bg` | hex color (`RRGGBB`, `#RRGGBB`, `RGB`, `#RGB`) | built-in | Background color override. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&bg=000000` |
| `fg` | hex color (`RRGGBB`, `#RRGGBB`, `RGB`, `#RGB`) | built-in | Foreground/text color override. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&fg=FFFFFF` |
| `sun` | hex color (`RRGGBB`, `#RRGGBB`, `RGB`, `#RGB`) | built-in | Sun color override used by icons. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&sun=FFFF00` |
| `sun2` | hex color (`RRGGBB`, `#RRGGBB`, `RGB`, `#RGB`) | built-in | Secondary sun / gradient accent color override used by icons. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&sun2=FF6A00` |
| `cloud` | hex color (`RRGGBB`, `#RRGGBB`, `RGB`, `#RGB`) | built-in | Cloud color override used by icons. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&cloud=FFFFFF` |
| `rain` | hex color (`RRGGBB`, `#RRGGBB`, `RGB`, `#RGB`) | built-in | Rain color override used by icons and precip styling. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&rain=006EFF` |
| `moon` | hex color (`RRGGBB`, `#RRGGBB`, `RGB`, `#RGB`) | built-in | Moon color override used by icons. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&moon=FFFAA2` |
| `hourly` | boolean | disabled | Show hourly tiles instead of daily forecast tiles. Equivalent to `mode=hourly`. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&hourly=1` |
| `mode` | `hourly` | (none) | Sets forecast mode. Currently `mode=hourly` is equivalent to `hourly=1`. | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&mode=hourly` |
| `hcount` | integer (`3..8`) | `5` | Number of hourly tiles (only relevant when hourly mode is enabled). | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&hourly=1&hcount=7&hstep=60` |
| `hstep` | integer minutes (`30..180`) | `60` | Minutes between hourly ticks (only relevant when hourly mode is enabled). | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&hourly=1&hcount=7&hstep=60` |
| `dcount` | integer (`3..10`) | `5` | Number of daily tiles (only relevant when daily mode is used). | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&dcount=7` |
| `debug` | boolean | disabled | Renders a plain debug readout instead of the UI (timestamps, timezone, upcoming forecast slots). | `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&debug=1` |

## Helpful OpenWeather Links

- **API keys / account**
  - [Sign up](https://home.openweathermap.org/users/sign_up)
  - [API keys dashboard](https://home.openweathermap.org/api_keys)
- **API documentation**
  - [Current weather (`/data/2.5/weather`)](https://openweathermap.org/current)
  - [5 day / 3 hour forecast (`/data/2.5/forecast`)](https://openweathermap.org/forecast5)
  - [Geocoding (helpful for lat/lon + place lookup)](https://openweathermap.org/api/geocoding-api)

> [!NOTE]
> OpenWeather rate limits apply based on your plan.

## Quick Start

1. Get an OpenWeather API key.
2. Open the page with at least:

- `apikey` (required)
- a location (required)

Example:

- `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&country=US`

If required parameters are missing or OpenWeather returns an error (e.g. city not found), the page renders a **black background** with a **bold red error message**.

## Location Parameters (Required: choose one)

The script requires *some* location input. The first matching option in the precedence list is used.

If you do **not** provide any location parameters, the script will (by default) attempt to **auto-locate** the requester using an IP geolocation lookup and then use the resulting approximate `lat/lon`.

Note: this makes an external request to a third-party IP geolocation service (see `autoloc` below).

### Precedence (highest to lowest)

1. `lat` + `lon`
2. `zip` (+ optional `zipcountry`)
3. `city` (+ optional `state`, `country`)
4. `cityid`
5. `q`

### Option A: Coordinates

- `lat` (number, -90..90)
- `lon` (number, -180..180)

Example:

- `weather.php?apikey=YOUR_KEY&lat=40.2601&lon=-74.2738`

### Option B: Zip Code

- `zip` (string)
- `zipcountry` (string, optional; defaults to `US`)

Examples:

- `weather.php?apikey=YOUR_KEY&zip=07728`
- `weather.php?apikey=YOUR_KEY&zip=07728&zipcountry=US`

### Option C: City details

- `city` (string)
- `state` (string, optional)
- `country` (string, optional; defaults to `US`)

Examples:

- `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&country=US`
- `weather.php?apikey=YOUR_KEY&city=London&country=GB`

### Option D: OpenWeather City ID

- `cityid` (numeric string)

Example:

- `weather.php?apikey=YOUR_KEY&cityid=5098278`

### Option E: Raw OpenWeather Query

- `q` (string)

Examples:

- `weather.php?apikey=YOUR_KEY&q=Freehold,NJ,US`
- `weather.php?apikey=YOUR_KEY&q=Paris,FR`

## API Key (Required)

- `apikey` (required)

Notes:

- Must look like a 32-character hex string.
- If missing or invalid, the page displays an error.

## Display / Layout Parameters

### Target Resolution + Scaling

Internally, the layout is designed around a base **800x480** canvas and then scaled to fit your requested output size.

Provide either:

- `res=WIDTHxHEIGHT`

or

- `w=WIDTH&h=HEIGHT`

Examples:

- `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&res=800x480`
- `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&w=1024&h=600`

### City Label Toggle

- `label=1` (optional)

Shows the city name in a small corner overlay.

If **auto-location** is used and you did not explicitly set `label`, the script will automatically enable the label.

Example:

- `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&label=1`

### Color Scheme / Palette

You can either switch to a monochrome mode or override individual colors.

- `mono=1`
  - Forces a black/white style (icons and UI will use the foreground color).
- `scheme=mono`
  - Equivalent to `mono=1`.

Optional color overrides (any not provided fall back to built-in defaults):

- `bg` (background)
- `fg` (foreground/text)
- `sun`
- `sun2`
- `cloud`
- `rain`
- `moon`

Colors accept `RRGGBB`, `#RRGGBB`, `RGB`, or `#RGB`.

Examples:

- Monochrome black/white:
  - `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&mono=1`
- Custom palette:
  - `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&bg=000000&fg=FFFFFF&rain=00FFFF&sun=FFFF00`

## Forecast Mode Parameters

### Daily vs Hourly

- `hourly=1` to show hourly tiles
- or `mode=hourly` (equivalent)

If neither is provided, daily mode is used.

Examples:

- Daily:
  - `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ`
- Hourly:
  - `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&hourly=1`

### Hourly Options

- `hcount` (number of hourly tiles)
  - allowed range: `3..8`
- `hstep` (minutes between hourly ticks)
  - allowed range: `30..180`

Example:

- `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&hourly=1&hcount=7&hstep=60`

### Daily Options

- `dcount` (number of daily tiles)
  - allowed range: `3..10`

Example:

- `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&dcount=7`

## Precipitation Display

The UI shows precipitation amounts and probability, and uses a small icon to indicate the precip type:

- rain
- snow
- mixed

## Editing SVG Icons

The weather and precipitation icons are stored as editable template files in:

- `./svg/`

These SVGs are **inlined** into the HTML by `weather.php` at runtime.

### Template placeholders

The templates use placeholders that `weather.php` replaces when rendering:

- `{{W}}`, `{{H}}` (rendered icon size)
- `{{SUN}}`, `{{SUN2}}`, `{{CLOUD}}`, `{{RAIN}}`, `{{MOON}}` (palette colors)
- `{{GRAD_ID}}` (unique per-icon id used for gradients)

Tip: if you add a gradient in an SVG, use `{{GRAD_ID}}` inside the `id="..."` so multiple icons on the same page don't clash.

## Debug Mode

- `debug=1`

Renders a plain debug readout (timestamps, timezone, and a small dump of upcoming forecast slots) instead of the UI.

Example:

- `weather.php?apikey=YOUR_KEY&city=Freehold&state=NJ&debug=1`

## Location Behavior

If you pass explicit location parameters (ex: `city`/`state`, etc.), the script will use those.

If you do not provide any explicit location parameters, the script will try to determine an approximate location from the requester IP address.

> [!WARNING]
> IP-based location uses an external IP geolocation request.

---

[Back to top](#weather-display-openweather--weatherphp)

---
