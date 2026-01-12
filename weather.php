<?php
header('Content-Type: text/html; charset=utf-8');

// -----------------------------
// URL PARAMETERS (keep all input reads in one place)
// -----------------------------
$resParam = filter_input(INPUT_GET, 'res', FILTER_UNSAFE_RAW);
$wParam = filter_input(INPUT_GET, 'w', FILTER_UNSAFE_RAW);
$hParam = filter_input(INPUT_GET, 'h', FILTER_UNSAFE_RAW);
$apiKeyParam = filter_input(INPUT_GET, 'apikey', FILTER_UNSAFE_RAW);

$monoParam = filter_input(INPUT_GET, 'mono', FILTER_UNSAFE_RAW);
$schemeParam = filter_input(INPUT_GET, 'scheme', FILTER_UNSAFE_RAW);

$hourlyParam = filter_input(INPUT_GET, 'hourly', FILTER_UNSAFE_RAW);
$modeParam = filter_input(INPUT_GET, 'mode', FILTER_UNSAFE_RAW);

$cityIdParam = filter_input(INPUT_GET, 'cityid', FILTER_UNSAFE_RAW);
$qParam = filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW);
$debugParam = filter_input(INPUT_GET, 'debug', FILTER_UNSAFE_RAW);

$labelParam = filter_input(INPUT_GET, 'label', FILTER_UNSAFE_RAW);
$autoLocParam = filter_input(INPUT_GET, 'autoloc', FILTER_UNSAFE_RAW);

$latParam = filter_input(INPUT_GET, 'lat', FILTER_UNSAFE_RAW);
$lonParam = filter_input(INPUT_GET, 'lon', FILTER_UNSAFE_RAW);
$zipParam = filter_input(INPUT_GET, 'zip', FILTER_UNSAFE_RAW);
$zipCountryParam = filter_input(INPUT_GET, 'zipcountry', FILTER_UNSAFE_RAW);
$cityParam = filter_input(INPUT_GET, 'city', FILTER_UNSAFE_RAW);
$stateParam = filter_input(INPUT_GET, 'state', FILTER_UNSAFE_RAW);
$countryParam = filter_input(INPUT_GET, 'country', FILTER_UNSAFE_RAW);

$hourlyCountParam = filter_input(INPUT_GET, 'hcount', FILTER_UNSAFE_RAW);
$hourlyStepParam = filter_input(INPUT_GET, 'hstep', FILTER_UNSAFE_RAW);
$dailyCountParam = filter_input(INPUT_GET, 'dcount', FILTER_UNSAFE_RAW);

// Color overrides (used for MONO and/or custom themes)
$bgParam = filter_input(INPUT_GET, 'bg', FILTER_UNSAFE_RAW);
$fgParam = filter_input(INPUT_GET, 'fg', FILTER_UNSAFE_RAW);
$sunParam = filter_input(INPUT_GET, 'sun', FILTER_UNSAFE_RAW);
$sun2Param = filter_input(INPUT_GET, 'sun2', FILTER_UNSAFE_RAW);
$cloudParam = filter_input(INPUT_GET, 'cloud', FILTER_UNSAFE_RAW);
$rainParam = filter_input(INPUT_GET, 'rain', FILTER_UNSAFE_RAW);
$moonParam = filter_input(INPUT_GET, 'moon', FILTER_UNSAFE_RAW);

// -----------------------------
// CONSTANTS / DEFAULTS (mostly static globals)
// -----------------------------
$BASE_W = 800;
$BASE_H = 480;

$DEFAULT_PALETTE = [
    'bg' => '#000000',
    'fg' => '#FFFFFF',
    'sun' => '#FFD400',
    'sun2' => '#FF6A00',
    'cloud' => '#FFFFFF',
    'rain' => '#006EFF',
    'moon' => '#FFFAA2',
];

// -----------------------------
// RUNTIME VARIABLE DECLARATIONS (defaults only; main logic runs later)
// -----------------------------

$TARGET_W = $BASE_W;
$TARGET_H = $BASE_H;
$SCALE = 1.0;

$MONO = false;
$DEBUG = false;
$API_KEY = null;
$CITY_QUERY = null;
$PALETTE = $DEFAULT_PALETTE;

$labelWasProvided = false;
$SHOW_LABEL = false;
$AUTO_LOC = true;
$DAILY_COUNT = 5;
$SHOW_HOURLY = false;
$HOURLY_COUNT = 5;
$HOURLY_STEP_MINUTES = 60;

// -----------------------------
// FUNCTIONS
// -----------------------------

/**
 * Render a full-page error message sized to the requested target resolution, then exit.
 *
 * @param int|float|string $targetW Target width in pixels.
 * @param int|float|string $targetH Target height in pixels.
 * @param string $message Error message to display.
 * @return never
 */
function renderErrorAndExit($targetW, $targetH, $message)
{
    $w = (int)$targetW;
    $h = (int)$targetH;
    $msg = (string)$message;

    echo "<!DOCTYPE html><html><head><meta charset=\"utf-8\"><style>body{margin:0;width:{$w}px;height:{$h}px;background:#000;color:#f00;font:24px/1.4 Arial, sans-serif;font-weight:800;padding:20px;box-sizing:border-box;}</style></head><body>";
    echo htmlspecialchars($msg);
    echo "</body></html>";
    exit;
}

/**
 * Normalize a hex color string into #RRGGBB.
 *
 * Accepts forms like "#RGB", "RGB", "#RRGGBB", or "RRGGBB".
 * Returns null for invalid values.
 *
 * @param mixed $value Raw user input.
 * @return string|null Normalized #RRGGBB (uppercase) or null if invalid.
 */
function normalizeHexColor($value)
{
    if (!is_string($value)) {
        return null;
    }
    $v = trim($value);
    if ($v === '') {
        return null;
    }
    if ($v[0] !== '#') {
        $v = '#' . $v;
    }
    if (preg_match('/^#([A-Fa-f0-9]{3})$/', $v, $m)) {
        $h = $m[1];
        return '#' . $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    }
    if (preg_match('/^#([A-Fa-f0-9]{6})$/', $v)) {
        return strtoupper($v);
    }
    return null;
}

/**
 * Normalize a raw color value and fall back to a default.
 *
 * @param mixed $raw Raw user input.
 * @param string $default Default #RRGGBB value.
 * @return string Normalized #RRGGBB value.
 */
function colorValue($raw, $default)
{
    $c = normalizeHexColor($raw);
    return $c !== null ? $c : $default;
}

/**
 * Read an SVG template file from disk (cached).
 *
 * @param string $relativePath Path relative to this script directory.
 * @return string|null SVG contents, or null if the file can't be read.
 */
function svgTemplate($relativePath)
{
    static $cache = [];
    $path = (string)$relativePath;
    if (isset($cache[$path])) {
        return $cache[$path];
    }

    $full = __DIR__ . DIRECTORY_SEPARATOR . $path;
    $data = @file_get_contents($full);
    if ($data === false) {
        $cache[$path] = null;
        return null;
    }
    $cache[$path] = $data;
    return $data;
}

/**
 * Render an SVG template by replacing {{PLACEHOLDERS}}.
 *
 * Also uniquifies the common gradient id="g" so multiple inline SVGs
 * do not clash in the DOM.
 *
 * @param string $relativePath Template path relative to this script directory.
 * @param array $vars Map of placeholder variables (case-insensitive keys).
 * @return string Rendered SVG (empty string if template missing).
 */
function renderSvgTemplate($relativePath, $vars)
{
    $tpl = svgTemplate($relativePath);
    if ($tpl === null) {
        return '';
    }
    $map = [];
    foreach ((array)$vars as $k => $v) {
        $map['{{' . strtoupper((string)$k) . '}}'] = (string)$v;
    }
    $out = strtr($tpl, $map);

    if (strpos($out, 'id="g"') !== false || strpos($out, "id='g'") !== false) {
        $gid = 'g' . substr(sha1(uniqid('', true)), 0, 10);
        $out = str_replace('id="g"', 'id="' . $gid . '"', $out);
        $out = str_replace("id='g'", "id='" . $gid . "'", $out);
        $out = str_replace('url(#g)', 'url(#' . $gid . ')', $out);
    }

    return $out;
}

/**
 * Parse a boolean-like URL parameter.
 *
 * @param mixed $value Raw input value.
 * @return bool|null True/false if recognized, otherwise null.
 */
function parseBoolParam($value)
{
    if (!is_string($value)) {
        return null;
    }
    $v = strtolower(trim($value));
    if ($v === '') {
        return null;
    }
    if (in_array($v, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($v, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    return null;
}

/**
 * Parse an integer-like URL parameter.
 *
 * @param mixed $value Raw input value.
 * @return int|null Parsed integer, or null if not a plain integer string.
 */
function parseIntParam($value)
{
    if (!is_string($value)) {
        return null;
    }
    $v = trim($value);
    if ($v === '' || !preg_match('/^\d+$/', $v)) {
        return null;
    }
    return (int)$v;
}

/**
 * Parse an OpenWeather API key (32 hex characters).
 *
 * @param mixed $value Raw input value.
 * @return string|null Key string if valid.
 */
function parseApiKeyParam($value)
{
    if (!is_string($value)) {
        return null;
    }
    $v = trim($value);
    if (!preg_match('/^[A-Fa-f0-9]{32}$/', $v)) {
        return null;
    }
    return $v;
}

/**
 * Parse a resolution parameter in the form "WIDTHxHEIGHT".
 *
 * @param mixed $value Raw input value.
 * @return array{0:int,1:int}|null [width,height] or null if invalid.
 */
function parseResolutionParam($value)
{
    if (!is_string($value)) {
        return null;
    }

    $v = trim($value);
    if (!preg_match('/^(\d{2,5})x(\d{2,5})$/i', $v, $m)) {
        return null;
    }
    return [(int)$m[1], (int)$m[2]];
}

/**
 * Fetch a URL or render an error and exit.
 *
 * @param string $url URL to fetch.
 * @param int $targetW Target width for error render.
 * @param int $targetH Target height for error render.
 * @param string $errorMessage Error message to show if fetch fails.
 * @return string Response body.
 */
function fetchUrlOrError($url, $targetW, $targetH, $errorMessage)
{
    $out = @file_get_contents((string)$url);
    if ($out === false) {
        renderErrorAndExit($targetW, $targetH, (string)$errorMessage);
    }
    return $out;
}

/**
 * Validate an OpenWeather response payload (cod/message) or render an error and exit.
 *
 * @param mixed $data Decoded JSON (associative array).
 * @param int $targetW Target width for error render.
 * @param int $targetH Target height for error render.
 * @return void
 */
function validateOpenWeatherOrError($data, $targetW, $targetH)
{
    if (!is_array($data)) {
        renderErrorAndExit($targetW, $targetH, 'Error: unexpected response from OpenWeather (invalid JSON).');
    }

    $cod = isset($data['cod']) ? (string)$data['cod'] : '';
    if ($cod !== '' && $cod !== '200') {
        $msg = isset($data['message']) ? (string)$data['message'] : 'Unknown OpenWeather error.';
        renderErrorAndExit($targetW, $targetH, 'Error: ' . $msg);
    }
}

/**
 * Read a 3-hour precipitation value (mm) from a forecast list item.
 *
 * @param array $item Forecast list item.
 * @param string $kind "rain" or "snow".
 * @return float Millimeters over 3 hours.
 */
function readForecastPrecipMm3h($item, $kind)
{
    if (!is_array($item) || !is_string($kind)) {
        return 0.0;
    }
    if (isset($item[$kind]['3h'])) {
        return (float)$item[$kind]['3h'];
    }
    return 0.0;
}

/**
 * Read an hourly precipitation rate (mm/hr) from the current weather payload.
 *
 * @param array $current Current weather payload.
 * @param string $kind "rain" or "snow".
 * @return float Millimeters per hour.
 */
function readCurrentPrecipMmPerHour($current, $kind)
{
    if (!is_array($current) || !is_string($kind)) {
        return 0.0;
    }
    if (isset($current[$kind]['1h'])) {
        return (float)$current[$kind]['1h'];
    }
    if (isset($current[$kind]['3h'])) {
        return (float)$current[$kind]['3h'] / 3.0;
    }
    return 0.0;
}

/**
 * Format inches to a string for display.
 *
 * @param int|float|string $in Precipitation in inches.
 * @return string Formatted inches with 2 decimals.
 */
function fmtIn($in)
{
    $v = (float)$in;
    if ($v < 0.005) {
        return '0.00';
    }
    return number_format($v, 2);
}

/**
 * Clamp and format probability of precipitation.
 *
 * @param int|float|string $popPct Probability as a percentage.
 * @return int Probability in the range [0, 100].
 */
function fmtPop($popPct)
{
    $p = (int)$popPct;
    if ($p < 0) $p = 0;
    if ($p > 100) $p = 100;
    return $p;
}

/**
 * Determine precipitation type from rain/snow amounts and/or icon code.
 *
 * @param int|float|string $rainMm Rain amount in mm.
 * @param int|float|string $snowMm Snow amount in mm.
 * @param string|null $icon OpenWeather icon code (e.g. 10d) used as fallback.
 * @param int|float|string $popPct Probability of precipitation in percent.
 * @return string One of: "rain", "snow", "mix", "none".
 */
function precipType($rainMm, $snowMm, $icon, $popPct)
{
    $r = (float)$rainMm;
    $s = (float)$snowMm;
    $p = (int)$popPct;

    if ($r > 0.0 && $s > 0.0) {
        return 'mix';
    }
    if ($r > 0.0) {
        return 'rain';
    }
    if ($s > 0.0) {
        return 'snow';
    }

    if ($p > 0 && is_string($icon)) {
        $code = substr($icon, 0, 2);
        if ($code === '13') {
            return 'snow';
        }
        if ($code === '09' || $code === '10') {
            return 'rain';
        }
    }
    return 'none';
}

/**
 * Render a precipitation icon SVG for the given type.
 *
 * Uses current global palette settings and mono mode.
 *
 * @param string $type One of: "rain", "snow", "mix", "none".
 * @param int|float|string $size Intended pixel size (not always used by template).
 * @return string SVG markup (empty string for "none").
 */
function precipIconSvg($type, $size)
{
    global $PALETTE;
    global $MONO;
    $t = (string)$type;

    $cssVars = '';
    $cssVars .= '--fg:' . $PALETTE['fg'] . ';';
    $cssVars .= '--cloud:' . ($MONO ? $PALETTE['fg'] : $PALETTE['cloud']) . ';';
    $cssVars .= '--rain:' . ($MONO ? $PALETTE['fg'] : $PALETTE['rain']) . ';';

    if ($t === 'rain') {
        return renderSvgTemplate('svg/precip_rain.svg', ['css_vars' => $cssVars]);
    }
    if ($t === 'snow') {
        return renderSvgTemplate('svg/precip_snow.svg', ['css_vars' => $cssVars]);
    }
    if ($t === 'mix') {
        return renderSvgTemplate('svg/precip_mix.svg', ['css_vars' => $cssVars]);
    }
    return '';
}

/**
 * Render the main weather icon SVG for a given OpenWeather icon code.
 *
 * @param string $icon OpenWeather icon code (e.g. 01d, 10n).
 * @param int|float|string $size Intended pixel size (not always used by template).
 * @return string SVG markup.
 */
function weatherSvg($icon, $size)
{
    global $PALETTE;
    global $MONO;
    $code = substr($icon, 0, 2);
    $isDay = substr($icon, -1) === 'd';

    $sun = $PALETTE['sun'];
    $sun2 = $PALETTE['sun2'];
    $cloud = $PALETTE['cloud'];
    $rain = $PALETTE['rain'];
    $moon = $PALETTE['moon'];

    $cssVars = '';
    $cssVars .= '--bg:' . $PALETTE['bg'] . ';';
    $cssVars .= '--fg:' . $PALETTE['fg'] . ';';
    $cssVars .= '--sun:' . $PALETTE['sun'] . ';';
    $cssVars .= '--sun2:' . ($MONO ? $PALETTE['sun'] : $PALETTE['sun2']) . ';';
    $cssVars .= '--cloud:' . $PALETTE['cloud'] . ';';
    $cssVars .= '--rain:' . $PALETTE['rain'] . ';';
    $cssVars .= '--moon:' . $PALETTE['moon'] . ';';

    $vars = [
        'css_vars' => $cssVars,
        'sun' => $sun,
        'sun2' => $sun2,
        'cloud' => $cloud,
        'rain' => $rain,
        'moon' => $moon,
    ];

    $tpl = null;
    if ($code === '01') {
        $tpl = $isDay ? 'svg/weather_01d.svg' : 'svg/weather_01n.svg';
    } elseif ($code === '02') {
        $tpl = $isDay ? 'svg/weather_02d.svg' : 'svg/weather_02n.svg';
    } elseif ($code === '03' || $code === '04') {
        $tpl = 'svg/weather_03.svg';
    } elseif ($code === '09') {
        $tpl = 'svg/weather_09.svg';
    } elseif ($code === '10') {
        $tpl = $isDay ? 'svg/weather_10d.svg' : 'svg/weather_09.svg';
    } elseif ($code === '13') {
        $tpl = 'svg/weather_13.svg';
    }

    if ($tpl !== null) {
        $out = renderSvgTemplate($tpl, $vars);
        if ($out !== '') {
            return $out;
        }
    }

    return renderSvgTemplate('svg/weather_03.svg', $vars);
}

/**
 * Format a scale factor for safe CSS insertion.
 *
 * @param int|float|string $scale Raw scale value.
 * @return string Normalized scale string without trailing zeros.
 */
function fmtScale($scale)
{
    $s = (float)$scale;
    return rtrim(rtrim(number_format($s, 6, '.', ''), '0'), '.');
}

/**
 * Read a UNIX timestamp (UTC seconds) from a forecast list item.
 *
 * @param array $item Forecast list item.
 * @return int Timestamp (seconds).
 */
function readForecastDt($item)
{
    if (!is_array($item) || !isset($item['dt'])) {
        return 0;
    }
    return (int)$item['dt'];
}

/**
 * Read probability of precipitation (0..1) from a forecast list item.
 *
 * @param array $item Forecast list item.
 * @return float POP value.
 */
function readForecastPop($item)
{
    if (!is_array($item) || !isset($item['pop'])) {
        return 0.0;
    }
    return (float)$item['pop'];
}

/**
 * Read the OpenWeather icon code from a forecast list item.
 *
 * @param array $item Forecast list item.
 * @return string|null Icon code.
 */
function readForecastIcon($item)
{
    if (!is_array($item) || !isset($item['weather'][0]['icon'])) {
        return null;
    }
    return (string)$item['weather'][0]['icon'];
}

/**
 * Read the weather description from a forecast list item.
 *
 * @param array $item Forecast list item.
 * @return string Description (empty string if missing).
 */
function readForecastDescription($item)
{
    if (!is_array($item) || !isset($item['weather'][0]['description'])) {
        return '';
    }
    return (string)$item['weather'][0]['description'];
}

/**
 * Read the temperature from a forecast list item.
 *
 * @param array $item Forecast list item.
 * @return float Temperature.
 */
function readForecastTemp($item)
{
    if (!is_array($item) || !isset($item['main']['temp'])) {
        return 0.0;
    }
    return (float)$item['main']['temp'];
}

// -----------------------------
// Derive runtime settings from URL parameters
// -----------------------------

// -----------------------------
// OpenWeather API fetch
// -----------------------------

$currentJson = fetchUrlOrError(
    "https://api.openweathermap.org/data/2.5/weather?{$CITY_QUERY}&appid={$API_KEY}&units=imperial",
    $TARGET_W,
    $TARGET_H,
    'Error: failed to fetch current weather from OpenWeather.'
);

$forecastJson = fetchUrlOrError(
    "https://api.openweathermap.org/data/2.5/forecast?{$CITY_QUERY}&appid={$API_KEY}&units=imperial",
    $TARGET_W,
    $TARGET_H,
    'Error: failed to fetch forecast from OpenWeather.'
);

$current = json_decode($currentJson, true);
$forecast = json_decode($forecastJson, true);

// OpenWeather response validation
validateOpenWeatherOrError($current, $TARGET_W, $TARGET_H);
validateOpenWeatherOrError($forecast, $TARGET_W, $TARGET_H);

if (!isset($current['weather'][0]['icon']) || !isset($current['main']['temp']) || !isset($forecast['list']) || !is_array($forecast['list'])) {
    renderErrorAndExit($TARGET_W, $TARGET_H, 'Error: unexpected response from OpenWeather (missing fields).');
}

// Current precipitation (best-effort)
$mmToIn = 1 / 25.4;
$currentPrecipMm = 0.0;
$currentPrecipMm += readCurrentPrecipMmPerHour($current, 'rain');
$currentPrecipMm += readCurrentPrecipMmPerHour($current, 'snow');

// Aggregate today's total precip (rain+snow) and max POP; also capture the next upcoming POP for "now".
foreach ($forecast['list'] as $item) {
    if ($nowPopPct === null && isset($item['pop']) && readForecastDt($item) > $nowUtc) {
        $nowPopPct = (int)round(readForecastPop($item) * 100.0);
        $nowIcon = readForecastIcon($item);
        $nowRainMm3h = readForecastPrecipMm3h($item, 'rain');
        $nowSnowMm3h = readForecastPrecipMm3h($item, 'snow');
    }

    $rain3h = readForecastPrecipMm3h($item, 'rain');
    $snow3h = readForecastPrecipMm3h($item, 'snow');
    $todayPrecipMm += ($rain3h + $snow3h);
    $todayRainMm += $rain3h;
    $todaySnowMm += $snow3h;

    $pop = readForecastPop($item);
    if ($pop > $todayPopMax) {
        $todayPopMax = $pop;
    }
}

if ($nowPopPct === null) {
    $nowPopPct = 0;
}

$todayPrecipIn = $todayPrecipMm * $mmToIn;
$todayPopPct = (int)round($todayPopMax * 100.0);
$todayPrecipType = precipType($todayRainMm, $todaySnowMm, null, $todayPopPct);
$nowPrecipType = precipType($nowRainMm3h, $nowSnowMm3h, $nowIcon, $nowPopPct);

// Forecast aggregation
$days = [];
if (!$SHOW_HOURLY) {
    $middayTarget = 12 * 3600;
    foreach ($forecast['list'] as $item) {
        $localTs = readForecastDt($item) + $tzOffset;
        $dayKey = gmdate('D', $localTs);
        if ($dayKey === $todayKey) {
            continue;
        }

        $precipMm = readForecastPrecipMm3h($item, 'rain') + readForecastPrecipMm3h($item, 'snow');
        $pop = readForecastPop($item);

        // Choose a representative icon/description near local midday.
        $secondsIntoDay = (int)gmdate('H', $localTs) * 3600 + (int)gmdate('i', $localTs) * 60;
        $dist = abs($secondsIntoDay - $middayTarget);

        if (!isset($days[$dayKey]) || $dist < $days[$dayKey]['_dist']) {
            $icon = readForecastIcon($item);
            $days[$dayKey] = [
                'temp' => round(readForecastTemp($item)),
                'desc' => readForecastDescription($item),
                'icon' => $icon !== null ? $icon : '',
                '_dist' => $dist,
                'precipMm' => 0.0,
                'rainMm' => 0.0,
                'snowMm' => 0.0,
                'popMax' => 0.0,
            ];
        }

        $days[$dayKey]['precipMm'] += $precipMm;
        $days[$dayKey]['rainMm'] += readForecastPrecipMm3h($item, 'rain');
        $days[$dayKey]['snowMm'] += readForecastPrecipMm3h($item, 'snow');
        if ($pop > $days[$dayKey]['popMax']) {
            $days[$dayKey]['popMax'] = $pop;
        }
    }

    // drop internal distance helper, keep first 5 days
    foreach ($days as $k => $v) {
        unset($days[$k]['_dist']);
    }

    $daysWithType = [];
    foreach ($days as $k => $v) {
        $popPct = (int)round($v['popMax'] * 100.0);
        $daysWithType[$k] = $v;
        $daysWithType[$k]['ptype'] = precipType($v['rainMm'], $v['snowMm'], $v['icon'], $popPct);
    }

    $days = $daysWithType;
    $days = array_slice($days, 0, $DAILY_COUNT);
} else {
    $days = [];
}

// Hourly forecast
$SLOT_COUNT = $SHOW_HOURLY ? $HOURLY_COUNT : $DAILY_COUNT;
$SLOT_SCALE = min(1.0, 5.0 / max(1.0, (float)$SLOT_COUNT));

$hourly = [];

if ($SHOW_HOURLY) {
    $points = [];
    foreach ($forecast['list'] as $item) {

        $rain3h = readForecastPrecipMm3h($item, 'rain');
        $snow3h = readForecastPrecipMm3h($item, 'snow');
        $precipMm = $rain3h + $snow3h;

        $points[] = [
            'ts' => readForecastDt($item),
            'temp' => readForecastTemp($item),
            'icon' => (string)(readForecastIcon($item) ?? ''),
            'desc' => readForecastDescription($item),
            'precip3hMm' => $precipMm,
            'rain3hMm' => $rain3h,
            'snow3hMm' => $snow3h,
            'pop' => readForecastPop($item),
        ];
    }

    // Build hourly ticks starting from the next aligned boundary.
    $stepSeconds = $HOURLY_STEP_MINUTES * 60;
    $nextTickLocal = (int)(floor($nowLocal / $stepSeconds) * $stepSeconds + $stepSeconds);
    for ($i = 0; $i < $HOURLY_COUNT; $i++) {
        $targetLocal = $nextTickLocal + ($i * $stepSeconds);
        $targetUtc = $targetLocal - $tzOffset;

        $prev = null;
        $next = null;
        foreach ($points as $p) {
            // Choose the last point before (or at) target time.
            if ($p['ts'] <= $targetUtc) {
                $prev = $p;
                continue;
            }
            $next = $p;
            break;
        }

        // If we didn't find a previous point, fall back to the first.
        if ($prev === null) {
            $prev = $points[0] ?? null;
        }
        // If we didn't find a next point, fall back to the last.
        if ($next === null) {
            $next = $points[count($points) - 1] ?? null;
        }
        // If forecast points are missing, stop building ticks.
        if ($prev === null || $next === null) {
            break;
        }

        // Interpolate temperature between forecast points to match our custom tick spacing.
        $temp = $next['temp'];
        // If the two points have different timestamps, interpolate between them.
        if ($next['ts'] !== $prev['ts']) {
            $alpha = ($targetUtc - $prev['ts']) / ($next['ts'] - $prev['ts']);
            $alpha = max(0.0, min(1.0, $alpha));
            $temp = $prev['temp'] + ($next['temp'] - $prev['temp']) * $alpha;
        }

        $precipMmThisTick = ((float)$next['precip3hMm']) * ((float)$stepSeconds / 10800.0);
        $precipInThisTick = $precipMmThisTick * $mmToIn;
        $popPct = (int)round(readForecastPop($next) * 100.0);
        $rainMmThisTick = ((float)$next['rain3hMm']) * ((float)$stepSeconds / 10800.0);
        $snowMmThisTick = ((float)$next['snow3hMm']) * ((float)$stepSeconds / 10800.0);
        $ptype = precipType($rainMmThisTick, $snowMmThisTick, $next['icon'], $popPct);

        $hourly[] = [
            'label' => gmdate('g:ia', $targetLocal),
            'temp' => round($temp),
            // Use the upcoming forecast icon as the best representation for that hour.
            'icon' => $next['icon'],
            'precipIn' => $precipInThisTick,
            'popPct' => $popPct,
            'ptype' => $ptype,
        ];
    }
}

// Debug-only output
// If debug is enabled, dump computed values and exit.
if ($DEBUG) {
    // Debug view (raw computed timestamps / upcoming slots)
    echo "<pre style=\"color:#fff;background:#000;padding:12px;font:16px/1.4 monospace\">";
    echo "NOW_UTC     : " . gmdate('Y-m-d H:i:s', $nowUtc) . "\n";
    echo "TZ_OFFSET   : {$tzOffset} seconds\n";
    echo "NOW_LOCAL   : " . gmdate('Y-m-d H:i:s', $nowLocal) . "\n\n";
    // If current weather is present, include it in debug output.
    if (isset($current['weather'][0])) {
        echo "CURRENT     : " . $current['weather'][0]['main'] . " / " . $current['weather'][0]['description'] . " (" . $current['weather'][0]['icon'] . ")\n";
    }

    echo "\nNEXT FORECAST SLOTS (first 8):\n";
    $i = 0;
    foreach ($forecast['list'] as $it) {
        $tsLocal = readForecastDt($it) + $tzOffset;
        $desc = readForecastDescription($it);
        $ic = (string)(readForecastIcon($it) ?? '');
        echo "- " . gmdate('D g:ia', $tsLocal) . " | {$desc} ({$ic})\n";
        $i++;
        if ($i >= 8) break;
    }
    echo "</pre>";
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            width: <?= (int)$TARGET_W ?>px;
            height: <?= (int)$TARGET_H ?>px;
            overflow: hidden;
            font-family: Arial, sans-serif;
            background: <?= $PALETTE['bg'] ?>;
        }

        #scale-wrap {
            width: <?= (int)$BASE_W ?>px;
            height: <?= (int)$BASE_H ?>px;
            transform: scale(<?= fmtScale($SCALE) ?>);
            transform-origin: top left;
        }

        #weather {
            width: 100%;
            height: 100%;
            padding: 18px;
            background: <?= $PALETTE['bg'] ?>;
            color: <?= $PALETTE['fg'] ?>;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .current {
            display: flex;
            align-items: center;
            margin-bottom: 18px;
        }

        .icon {
            width: 176px;
            height: 176px;
            margin-right: 16px;
        }

        .icon-main--mono {
            filter: <?= $MONO ? 'none' : 'grayscale(1) brightness(10) contrast(2)' ?>;
        }

        .icon-main--color {
            filter: none;
        }

        .temp {
            font-size: 108px;
            font-weight: bold;
            margin-right: 24px;
            line-height: 1;
            color: <?= $PALETTE['fg'] ?>;
        }

        .details {
            font-size: 26px;
            line-height: 1.55;
            color: <?= $PALETTE['fg'] ?>;
            font-weight: 700;
        }

        .details>div:first-child {
            font-weight: 800;
        }

        .detail-line {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-icon {
            width: 22px;
            height: 22px;
            flex: 0 0 22px;
            color: <?= $PALETTE['fg'] ?>;
        }

        .forecast {
            display: flex;
            gap: 10px;
            justify-content: space-between;
        }

        .day {
            flex: 1;
            text-align: center;
            background: transparent;
            padding: 0;
        }

        .day-name {
            font-size: calc(20px * <?= fmtScale($SLOT_SCALE) ?>);
            font-weight: 800;
            margin-bottom: 2px;
            color: <?= $PALETTE['fg'] ?>;
        }

        .day-icon {
            width: calc(140px * <?= fmtScale($SLOT_SCALE) ?>);
            height: calc(140px * <?= fmtScale($SLOT_SCALE) ?>);
            margin: 2px auto;
        }

        .day-temp {
            font-size: calc(26px * <?= fmtScale($SLOT_SCALE) ?>);
            font-weight: 800;
            color: <?= $PALETTE['fg'] ?>;
        }

        .day-precip {
            font-size: calc(18px * <?= fmtScale($SLOT_SCALE) ?>);
            font-weight: 800;
            margin-top: calc(6px * <?= fmtScale($SLOT_SCALE) ?>);
            color: <?= $PALETTE['fg'] ?>;
        }

        .precip-inline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .precip-inline svg {
            width: 18px;
            height: 18px;
        }

        .corner-label {
            position: absolute;
            top: 10px;
            left: 12px;
            font-size: 18px;
            font-weight: 800;
            color: <?= $PALETTE['fg'] ?>;
            opacity: 0.9;
        }
    </style>
</head>

<body>
    <div id="scale-wrap">
        <div id="weather">
            <?php if ($SHOW_LABEL): ?>
                <div class="corner-label"><?= htmlspecialchars($current['name'] ?? '') ?></div>
            <?php endif; ?>
            <!-- (Don't delete I am commenting it out in case I want it back later) <div class="location"><?= htmlspecialchars($current['name']) ?></div> -->
            <div class="current">
                <div class="<?= $mainIconClass ?>"><?= weatherSvg($mainIcon, 176) ?></div>
                <div class="temp"><?= round($current['main']['temp']) ?>°</div>
                <div class="details">
                    <div><?= htmlspecialchars($current['weather'][0]['description']) ?></div>
                    <div class="detail-line">
                        <svg class="detail-icon detail-icon--feels" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 14.76V5a2 2 0 10-4 0v9.76a4 4 0 104 0z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Feels like <?= round($current['main']['feels_like']) ?>°</span>
                    </div>
                    <div class="detail-line">
                        <svg class="detail-icon detail-icon--humidity" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2s6 7 6 12a6 6 0 11-12 0c0-5 6-12 6-12z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Humidity <?= $current['main']['humidity'] ?>%</span>
                    </div>
                    <div class="detail-line">
                        <svg class="detail-icon detail-icon--wind" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M3 8h10a3 3 0 103-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M3 12h14a3 3 0 11-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M3 16h8a2 2 0 110 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Wind <?= round($current['wind']['speed']) ?> mph</span>
                    </div>
                    <div class="detail-line">
                        <span class="precip-inline">
                            <?= precipIconSvg($todayPrecipType, 18) ?>
                            <span><?= fmtIn($todayPrecipIn) ?>in <?= fmtPop($todayPopPct) ?>% (now <?= fmtPop($nowPopPct) ?>%)</span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="forecast">
                <?php if ($SHOW_HOURLY): ?>
                    <?php foreach ($hourly as $slot): ?>
                        <div class="day">
                            <div class="day-name"><?= $slot['label'] ?></div>
                            <div class="day-icon"><?= weatherSvg($slot['icon'], 140) ?></div>
                            <div class="day-temp"><?= $slot['temp'] ?>°</div>
                            <div class="day-precip"><?= fmtIn($slot['precipIn']) ?>in <?= fmtPop($slot['popPct']) ?>%</div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($days as $day => $info): ?>
                        <div class="day">
                            <div class="day-name"><?= $day ?></div>
                            <div class="day-icon"><?= weatherSvg($info['icon'], 140) ?></div>
                            <div class="day-temp"><?= $info['temp'] ?>°</div>
                            <div class="day-precip"><?= fmtIn(((float)$info['precipMm']) * (1 / 25.4)) ?>in <?= fmtPop((int)round(((float)$info['popMax']) * 100.0)) ?>%</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>

</html>