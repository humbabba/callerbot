<?php

/**
 * Tool declarations.
 */
function getToolDeclarations(): array {
    return [
        [
            'name' => 'get_weather',
            'description' => 'Get the current weather for a given city. Returns temperature, conditions, wind speed, and humidity.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'The city name, e.g. "Cleveland" or "Tokyo"',
                    ],
                ],
                'required' => ['city'],
            ],
        ],
        [
            'name' => 'get_forecast',
            'description' => 'Get a multi-day weather forecast for a given city. Returns daily high/low temperatures, conditions, precipitation chance, and wind speed.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'The city name, e.g. "Cleveland" or "Tokyo"',
                    ],
                    'days' => [
                        'type' => 'integer',
                        'description' => 'Number of days to forecast (1-7, default 5)',
                    ],
                ],
                'required' => ['city'],
            ],
        ],
        [
            'name' => 'sunrise_sunset',
            'description' => 'Get sunrise and sunset times for a given city.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'The city name, e.g. "New York" or "London"',
                    ],
                ],
                'required' => ['city'],
            ],
        ],
        [
            'name' => 'country_intel',
            'description' => 'Get intelligence briefing on a country: population, capital, languages, currency, region, and more. Use this for questions like "what currency does X use" or general country facts.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'country' => [
                        'type' => 'string',
                        'description' => 'The country name, e.g. "Japan" or "Brazil"',
                    ],
                ],
                'required' => ['country'],
            ],
        ],
        [
            'name' => 'word_lookup',
            'description' => 'Look up the definition, pronunciation, and etymology of a word.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'word' => [
                        'type' => 'string',
                        'description' => 'The word to define',
                    ],
                ],
                'required' => ['word'],
            ],
        ],
        [
            'name' => 'currency_convert',
            'description' => 'Convert a numeric amount from one currency to another using live exchange rates. Use when the user wants to convert money between currencies. Infer the currency codes from conversation context if not stated explicitly (e.g. if discussing Japan, assume JPY). Do NOT use for general questions about what currency a country uses — use country_intel for that.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'amount' => [
                        'type' => 'number',
                        'description' => 'The amount to convert',
                    ],
                    'from' => [
                        'type' => 'string',
                        'description' => 'Source currency code, e.g. "USD", "EUR", "GBP"',
                    ],
                    'to' => [
                        'type' => 'string',
                        'description' => 'Target currency code, e.g. "JPY", "CAD", "BRL"',
                    ],
                ],
                'required' => ['amount', 'from', 'to'],
            ],
        ],
        [
            'name' => 'travel_advisory',
            'description' => 'Get the current travel advisory and safety risk level for a country. Returns a risk score (1-5) and advisory message.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'country' => [
                        'type' => 'string',
                        'description' => 'The country name, e.g. "Thailand" or "Colombia"',
                    ],
                ],
                'required' => ['country'],
            ],
        ],
        [
            'name' => 'tourist_guide',
            'description' => 'Get a travel guide summary for a destination (city, region, or country). Returns an overview from Wikivoyage with travel tips and highlights. Use for questions like "what to do in X", "top attractions in X", "travel guide for X".',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'destination' => [
                        'type' => 'string',
                        'description' => 'The destination to look up, e.g. "Barcelona", "Bali", "New Zealand"',
                    ],
                ],
                'required' => ['destination'],
            ],
        ],
        [
            'name' => 'local_time',
            'description' => 'Get the current local time and timezone for a city.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'The city name, e.g. "Tokyo" or "Paris"',
                    ],
                ],
                'required' => ['city'],
            ],
        ],
        [
            'name' => 'public_holidays',
            'description' => 'Get upcoming public holidays for a given country.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'country_code' => [
                        'type' => 'string',
                        'description' => 'ISO 3166-1 alpha-2 country code, e.g. "US", "GB", "JP", "DE"',
                    ],
                    'year' => [
                        'type' => 'integer',
                        'description' => 'Year to look up (defaults to current year)',
                    ],
                ],
                'required' => ['country_code'],
            ],
        ],
    ];
}

/**
 * Tool declarations in OpenAI/Groq format.
 */
function getOpenAIToolDeclarations(): array {
    $tools = [];
    foreach (getToolDeclarations() as $fn) {
        $tools[] = [
            'type' => 'function',
            'function' => $fn,
        ];
    }
    return $tools;
}

/**
 * Execute a function call and return the result.
 */
function executeFunction(string $name, array $args): string {
    return match ($name) {
        'get_weather'               => fn_get_weather($args),
        'get_forecast'              => fn_get_forecast($args),
        'sunrise_sunset'            => fn_sunrise_sunset($args),
        'country_intel'             => fn_country_intel($args),
        'word_lookup'               => fn_word_lookup($args),
        'currency_convert'          => fn_currency_convert($args),
        'travel_advisory'           => fn_travel_advisory($args),
        'tourist_guide'             => fn_tourist_guide($args),
        'local_time'                => fn_local_time($args),
        'public_holidays'           => fn_public_holidays($args),
        default                     => json_encode(['error' => "Unknown function: {$name}"]),
    };
}

// ─── WEATHER HELPERS ─────────────────────────────────────────────────────────

function geocode_city(string $city): ?array {
    $geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
        'name'  => $city,
        'count' => 1,
    ]);
    $geoData = json_decode(curl_fetch($geoUrl), true);

    if (empty($geoData['results'])) {
        return null;
    }

    $location = $geoData['results'][0];
    return [
        'lat'      => $location['latitude'],
        'lon'      => $location['longitude'],
        'name'     => $location['name'] . ', ' . ($location['country'] ?? ''),
        'timezone' => $location['timezone'] ?? null,
        'country_code' => $location['country_code'] ?? null,
    ];
}

function weather_code_description(int $code): string {
    static $descriptions = [
        0 => 'Clear sky', 1 => 'Mainly clear', 2 => 'Partly cloudy', 3 => 'Overcast',
        45 => 'Foggy', 48 => 'Depositing rime fog',
        51 => 'Light drizzle', 53 => 'Moderate drizzle', 55 => 'Dense drizzle',
        61 => 'Slight rain', 63 => 'Moderate rain', 65 => 'Heavy rain',
        71 => 'Slight snow', 73 => 'Moderate snow', 75 => 'Heavy snow',
        80 => 'Slight rain showers', 81 => 'Moderate rain showers', 82 => 'Violent rain showers',
        95 => 'Thunderstorm', 96 => 'Thunderstorm with slight hail', 99 => 'Thunderstorm with heavy hail',
    ];
    return $descriptions[$code] ?? 'Unknown';
}

// ─── CURRENT WEATHER ─────────────────────────────────────────────────────────

function fn_get_weather(array $args): string {
    $city = $args['city'] ?? '';
    $geo = geocode_city($city);

    if (!$geo) {
        return json_encode(['error' => "Could not find city: {$city}"]);
    }

    $wxUrl = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude'        => $geo['lat'],
        'longitude'       => $geo['lon'],
        'current'         => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
        'temperature_unit'=> 'fahrenheit',
        'wind_speed_unit' => 'mph',
    ]);
    $wxData = json_decode(curl_fetch($wxUrl), true);
    $current = $wxData['current'] ?? [];

    return json_encode([
        'location'    => $geo['name'],
        'temperature' => ($current['temperature_2m'] ?? '?') . '°F',
        'conditions'  => weather_code_description($current['weather_code'] ?? -1),
        'humidity'    => ($current['relative_humidity_2m'] ?? '?') . '%',
        'wind'        => ($current['wind_speed_10m'] ?? '?') . ' mph',
    ]);
}

// ─── FORECAST ────────────────────────────────────────────────────────────────

function fn_get_forecast(array $args): string {
    $city = $args['city'] ?? '';
    $days = max(1, min($args['days'] ?? 5, 7));
    $geo = geocode_city($city);

    if (!$geo) {
        return json_encode(['error' => "Could not find city: {$city}"]);
    }

    $wxUrl = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude'         => $geo['lat'],
        'longitude'        => $geo['lon'],
        'daily'            => 'temperature_2m_max,temperature_2m_min,weather_code,precipitation_probability_max,wind_speed_10m_max',
        'temperature_unit' => 'fahrenheit',
        'wind_speed_unit'  => 'mph',
        'forecast_days'    => $days,
    ]);
    $wxData = json_decode(curl_fetch($wxUrl), true);
    $daily = $wxData['daily'] ?? [];

    $forecast = [];
    $dates = $daily['time'] ?? [];
    for ($i = 0; $i < count($dates); $i++) {
        $forecast[] = [
            'date'       => $dates[$i],
            'high'       => ($daily['temperature_2m_max'][$i] ?? '?') . '°F',
            'low'        => ($daily['temperature_2m_min'][$i] ?? '?') . '°F',
            'conditions' => weather_code_description($daily['weather_code'][$i] ?? -1),
            'precip'     => ($daily['precipitation_probability_max'][$i] ?? '?') . '%',
            'wind'       => ($daily['wind_speed_10m_max'][$i] ?? '?') . ' mph',
        ];
    }

    return json_encode([
        'location' => $geo['name'],
        'days'     => $days,
        'forecast' => $forecast,
    ]);
}

// ─── SUNRISE / SUNSET ───────────────────────────────────────────────────────

function fn_sunrise_sunset(array $args): string {
    $city = $args['city'] ?? '';

    // Geocode
    $geoUrl = 'https://geocoding-api.open-meteo.com/v1/search?' . http_build_query([
        'name'  => $city,
        'count' => 1,
    ]);
    $geoData = json_decode(curl_fetch($geoUrl), true);

    if (empty($geoData['results'])) {
        return json_encode(['error' => "Could not find city: {$city}"]);
    }

    $location = $geoData['results'][0];
    $lat = $location['latitude'];
    $lon = $location['longitude'];
    $resolvedName = $location['name'] . ', ' . ($location['country'] ?? '');

    $url = "https://api.sunrise-sunset.org/json?" . http_build_query([
        'lat'       => $lat,
        'lng'       => $lon,
        'formatted' => 0,
        'date'      => 'today',
    ]);

    $data = json_decode(curl_fetch($url), true);
    $results = $data['results'] ?? [];

    return json_encode([
        'location'       => $resolvedName,
        'sunrise'        => isset($results['sunrise']) ? date('g:i A', strtotime($results['sunrise'])) : '?',
        'sunset'         => isset($results['sunset']) ? date('g:i A', strtotime($results['sunset'])) : '?',
        'solar_noon'     => isset($results['solar_noon']) ? date('g:i A', strtotime($results['solar_noon'])) : '?',
        'day_length'     => isset($results['day_length']) ? gmdate('H\h i\m', $results['day_length']) : '?',
        'civil_twilight' => [
            'begin' => isset($results['civil_twilight_begin']) ? date('g:i A', strtotime($results['civil_twilight_begin'])) : '?',
            'end'   => isset($results['civil_twilight_end']) ? date('g:i A', strtotime($results['civil_twilight_end'])) : '?',
        ],
    ]);
}

// ─── COUNTRY INTEL ──────────────────────────────────────────────────────────

function fn_country_intel(array $args): string {
    $country = $args['country'] ?? '';

    $url = 'https://restcountries.com/v3.1/name/' . rawurlencode($country) . '?fullText=false&fields=name,capital,population,region,subregion,languages,currencies,flags,timezones,borders,area';
    $data = json_decode(curl_fetch($url), true);

    if (empty($data) || isset($data['status'])) {
        return json_encode(['error' => "Country not found: {$country}"]);
    }

    $c = $data[0];

    $currencies = [];
    foreach (($c['currencies'] ?? []) as $code => $info) {
        $currencies[] = ($info['name'] ?? $code) . " ({$code})";
    }

    return json_encode([
        'name'        => $c['name']['common'] ?? $country,
        'official'    => $c['name']['official'] ?? '',
        'capital'     => implode(', ', $c['capital'] ?? []),
        'region'      => ($c['region'] ?? '') . ($c['subregion'] ? ' / ' . $c['subregion'] : ''),
        'population'  => number_format($c['population'] ?? 0),
        'area_km2'    => number_format($c['area'] ?? 0),
        'languages'   => implode(', ', $c['languages'] ?? []),
        'currencies'  => implode(', ', $currencies),
        'timezones'   => implode(', ', $c['timezones'] ?? []),
        'borders'     => implode(', ', $c['borders'] ?? ['None (island or isolated)']),
        'flag'        => $c['flags']['emoji'] ?? '',
    ]);
}

// ─── WORD LOOKUP ────────────────────────────────────────────────────────────

function fn_word_lookup(array $args): string {
    $word = $args['word'] ?? '';

    $url = 'https://api.dictionaryapi.dev/api/v2/entries/en/' . rawurlencode($word);
    $data = json_decode(curl_fetch($url), true);

    if (empty($data) || isset($data['title'])) {
        return json_encode(['error' => "Word not found: {$word}"]);
    }

    $entry = $data[0];
    $phonetic = $entry['phonetic'] ?? '';

    // Collect meanings
    $meanings = [];
    foreach (($entry['meanings'] ?? []) as $m) {
        $defs = [];
        foreach (array_slice($m['definitions'] ?? [], 0, 2) as $d) {
            $defs[] = $d['definition'] ?? '';
        }
        $meanings[] = [
            'part_of_speech' => $m['partOfSpeech'] ?? '',
            'definitions'    => $defs,
        ];
    }

    // Etymology
    $origin = $entry['origin'] ?? null;

    $result = [
        'word'      => $entry['word'] ?? $word,
        'phonetic'  => $phonetic,
        'meanings'  => $meanings,
    ];
    if ($origin) {
        $result['origin'] = $origin;
    }

    return json_encode($result);
}

// ─── CURRENCY CONVERT ───────────────────────────────────────────────────────

function fn_currency_convert(array $args): string {
    $amount = $args['amount'] ?? 1;
    $from = strtoupper($args['from'] ?? 'USD');
    $to = strtoupper($args['to'] ?? 'EUR');

    $url = "https://api.frankfurter.dev/v1/latest?" . http_build_query([
        'from'   => $from,
        'to'     => $to,
        'amount' => $amount,
    ]);

    $data = json_decode(curl_fetch($url), true);

    if (empty($data['rates'])) {
        return json_encode(['error' => "Could not convert {$from} to {$to}"]);
    }

    return json_encode([
        'from'      => $from,
        'to'        => $to,
        'amount'    => $amount,
        'converted' => $data['rates'][$to] ?? '?',
        'rate'      => round(($data['rates'][$to] ?? 0) / max($amount, 0.0001), 6),
        'date'      => $data['date'] ?? date('Y-m-d'),
    ]);
}

// ─── PUBLIC HOLIDAYS ────────────────────────────────────────────────────────

function fn_public_holidays(array $args): string {
    $countryCode = strtoupper($args['country_code'] ?? 'US');
    $year = $args['year'] ?? (int)date('Y');

    $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/{$countryCode}";
    $data = json_decode(curl_fetch($url), true);

    if (empty($data) || isset($data['status'])) {
        return json_encode(['error' => "No holidays found for {$countryCode} in {$year}"]);
    }

    // Filter to upcoming holidays if current year
    $today = date('Y-m-d');
    $holidays = [];
    foreach ($data as $h) {
        if ($year == (int)date('Y') && ($h['date'] ?? '') < $today) {
            continue;
        }
        $holidays[] = [
            'date' => $h['date'] ?? '',
            'name' => $h['localName'] ?? $h['name'] ?? '',
            'name_en' => $h['name'] ?? '',
        ];
        if (count($holidays) >= 10) break;
    }

    return json_encode([
        'country' => $countryCode,
        'year'    => $year,
        'count'   => count($holidays),
        'holidays' => $holidays,
    ]);
}

// ─── TRAVEL ADVISORY ────────────────────────────────────────────────────────

function fn_travel_advisory(array $args): string {
    $country = $args['country'] ?? '';

    // Resolve country name to ISO alpha-2 code via restcountries
    $rcUrl = 'https://restcountries.com/v3.1/name/' . rawurlencode($country) . '?fullText=false&fields=cca2,name';
    $rcData = json_decode(curl_fetch($rcUrl), true);

    if (empty($rcData) || isset($rcData['status'])) {
        return json_encode(['error' => "Country not found: {$country}"]);
    }

    $countryCode = $rcData[0]['cca2'] ?? '';
    $countryName = $rcData[0]['name']['common'] ?? $country;

    if (!$countryCode) {
        return json_encode(['error' => "Could not resolve country code for: {$country}"]);
    }

    $url = 'https://www.travel-advisory.info/api?countrycode=' . $countryCode;
    $data = json_decode(curl_fetch($url, skipSslVerify: true), true);
    $info = $data['data'][$countryCode] ?? null;

    if (!$info) {
        return json_encode(['error' => "Travel advisory service unavailable for {$countryName}. Try country_intel for general country safety context."]);
    }

    $score = $info['advisory']['score'] ?? 0;
    $riskLevels = [
        1 => 'Low risk — Exercise normal precautions',
        2 => 'Moderate risk — Exercise increased caution',
        3 => 'High risk — Reconsider travel',
        4 => 'Very high risk — Do not travel',
        5 => 'Extreme risk — Do not travel',
    ];
    $level = max(1, min(5, (int)ceil($score)));

    return json_encode([
        'country'      => $countryName,
        'country_code' => $countryCode,
        'risk_score'   => round($score, 1),
        'risk_level'   => $riskLevels[$level],
        'message'      => $info['advisory']['message'] ?? '',
        'updated'      => $info['advisory']['updated'] ?? '',
        'source'       => $info['advisory']['source'] ?? '',
    ]);
}

// ─── TOURIST GUIDE (WIKIVOYAGE) ─────────────────────────────────────────────

function fn_tourist_guide(array $args): string {
    $destination = $args['destination'] ?? '';
    $slug = str_replace(' ', '_', $destination);

    // Try direct page summary first
    $url = 'https://en.wikivoyage.org/api/rest_v1/page/summary/' . rawurlencode($slug);
    $data = json_decode(curl_fetch($url), true);

    // If not found, try search
    if (empty($data['extract']) || (isset($data['type']) && str_contains($data['type'], 'not_found'))) {
        $searchUrl = 'https://en.wikivoyage.org/w/api.php?' . http_build_query([
            'action'   => 'query',
            'list'     => 'search',
            'srsearch' => $destination,
            'srlimit'  => 1,
            'format'   => 'json',
        ]);
        $searchData = json_decode(curl_fetch($searchUrl), true);
        $title = $searchData['query']['search'][0]['title'] ?? null;

        if (!$title) {
            return json_encode(['error' => "No travel guide found for: {$destination}"]);
        }

        $data = json_decode(curl_fetch(
            'https://en.wikivoyage.org/api/rest_v1/page/summary/' . rawurlencode(str_replace(' ', '_', $title))
        ), true);
    }

    // Get a longer extract via the MediaWiki API for more useful content
    $title = $data['title'] ?? $destination;
    $extractUrl = 'https://en.wikivoyage.org/w/api.php?' . http_build_query([
        'action'      => 'query',
        'titles'      => $title,
        'prop'        => 'extracts',
        'exintro'     => false,
        'explaintext' => true,
        'exsectionformat' => 'plain',
        'exchars'     => 2000,
        'format'      => 'json',
    ]);
    $extractData = json_decode(curl_fetch($extractUrl), true);
    $pages = $extractData['query']['pages'] ?? [];
    $page = reset($pages);
    $fullExtract = $page['extract'] ?? $data['extract'] ?? '';

    return json_encode([
        'destination' => $data['title'] ?? $destination,
        'summary'     => $data['extract'] ?? '',
        'guide'       => $fullExtract,
        'url'         => $data['content_urls']['desktop']['page'] ?? '',
    ]);
}

// ─── LOCAL TIME ─────────────────────────────────────────────────────────────

function fn_local_time(array $args): string {
    $city = $args['city'] ?? '';
    $geo = geocode_city($city);

    if (!$geo) {
        return json_encode(['error' => "Could not find city: {$city}"]);
    }

    $timezone = $geo['timezone'];
    if (!$timezone) {
        return json_encode(['error' => "Could not determine timezone for: {$city}"]);
    }

    $tz = new DateTimeZone($timezone);
    $dt = new DateTime('now', $tz);
    $offset = $dt->format('P');

    return json_encode([
        'location'     => $geo['name'],
        'timezone'     => $timezone,
        'local_time'   => $dt->format('g:i A'),
        'date'         => $dt->format('l, F j, Y'),
        'utc_offset'   => $offset,
    ]);
}

// ─── UTILITY ────────────────────────────────────────────────────────────────

function curl_fetch(string $url, bool $skipSslVerify = false): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_HTTPHEADER     => ['User-Agent: Callerbot/1.0'],
    ]);
    if ($skipSslVerify) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }
    $result = curl_exec($ch);
    curl_close($ch);
    return $result ?: '';
}
