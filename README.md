# Callerbot

A function-calling demo with a retro CRT terminal aesthetic. Callerbot is a conversational AI assistant built around **travel research** — it decides which tool to call, fetches live data from public APIs, and weaves the results into its response.

Ask a question that fits a tool and Callerbot will call it. Ask something no tool covers and it will either propose one (with a real data source) or respond naturally.

## Tools

| Tool | What it does | Data source |
|------|-------------|-------------|
| `get_weather` | Current weather for a city | [Open-Meteo](https://open-meteo.com/) |
| `get_forecast` | Multi-day forecast | [Open-Meteo](https://open-meteo.com/) |
| `sunrise_sunset` | Sunrise/sunset times | [Sunrise-Sunset.org](https://sunrise-sunset.org/) |
| `country_intel` | Country facts (capital, currency, languages, etc.) | [REST Countries](https://restcountries.com/) |
| `word_lookup` | Dictionary definitions and etymology | [Dictionary API](https://dictionaryapi.dev/) |
| `currency_convert` | Live currency conversion | [Frankfurter](https://www.frankfurter.app/) |
| `travel_advisory` | Travel safety risk level | [Travel-Advisory.info](https://www.travel-advisory.info/) |
| `tourist_guide` | Destination travel guides | [Wikivoyage](https://en.wikivoyage.org/) |
| `local_time` | Current local time and timezone | [Open-Meteo](https://open-meteo.com/) |
| `public_holidays` | Upcoming public holidays | [Nager.at](https://date.nager.at/) |

All data sources are free, public APIs with no authentication required.

## Stack

- **Backend:** PHP (XAMPP — Apache/MySQL)
- **AI:** Groq API (LLaMA 3.3 70B with automatic fallback)
- **Frontend:** Tailwind CSS v4, esbuild, vanilla JS
- **Database:** SQLite (PDO) for query logging
- **Font:** Share Tech Mono (loaded locally)

## Setup

### Prerequisites

- [XAMPP](https://www.apachefriends.org/) (or any Apache/PHP setup with `pdo_sqlite`)
- [Node.js](https://nodejs.org/) (for building CSS/JS)
- A [Groq API key](https://console.groq.com/)

### Install

1. Clone into your web root:
   ```bash
   git clone https://github.com/humbabba/callerbot.git /path/to/htdocs/callerbot
   cd /path/to/htdocs/callerbot
   ```

2. Install Node dependencies:
   ```bash
   npm install
   ```

3. Create the config file:
   ```bash
   cp config/config.example.php config/config.php
   ```
   Edit `config/config.php` and add your Groq API key.

4. Build the frontend assets:
   ```bash
   npm run build
   ```

5. Visit `http://localhost/callerbot/` in your browser.

### Development

```bash
npm run watch
```

Watches for changes to CSS and JS source files and rebuilds automatically.

## Project Structure

```
callerbot/
├── api/
│   ├── chat.php          # SSE streaming chat endpoint
│   └── functions.php     # Tool declarations and API integrations
├── assets/
│   ├── css/input.css     # Tailwind source with @theme config
│   └── js/app.js         # Client-side JS source
├── config/
│   └── config.php        # API keys and model config (gitignored)
├── db/
│   └── database.php      # SQLite logging helpers
├── dist/                 # Built CSS/JS output
├── fonts/                # Share Tech Mono (local)
├── index.php             # Main app
├── log.php               # Query log viewer
└── favicon.svg
```

## Example Conversation

```
> I'm thinking about visiting Thailand
< Thailand is among the most visited countries on Earth...

> Is it safe?
< [travel_advisory] Risk level: Low — Exercise normal precautions...

> What's the weather like in Bangkok?
< [get_weather] Currently 91°F, partly cloudy, humidity 68%...

> How much is $500 in local currency?
< [currency_convert] $500 USD = 17,425 THB...

> Any holidays coming up?
< [public_holidays] Songkran (Thai New Year) — April 13-15...
```

## License

Open-sourced software licensed under the [MIT license](https://opensource.org/license/MIT).
