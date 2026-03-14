# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Callerbot — a web app with a terminal/hacker aesthetic (green-on-black). Hosted via XAMPP.

## Environment

- Platform: Windows 11 with XAMPP (Apache/PHP/MySQL stack)
- Local URL: http://localhost/callerbot/
- Tailwind CSS v4 (configured via `@theme` in `assets/css/input.css`, NOT a tailwind.config.js)
- JS bundled with esbuild
- Fonts are loaded locally from `fonts/`

## Build

- `npm run build` — compile CSS and bundle JS to `dist/`
- `npm run watch` — same, with file watching for development
- Always rebuild after changing Tailwind classes in PHP/HTML or editing source CSS/JS

## Architecture

- `index.php` — single-page entry point, references `dist/css/style.css` and `dist/js/app.js`
- `assets/css/input.css` — Tailwind source with `@theme` (custom colors, fonts), `@layer base/components`, and custom keyframes
- `assets/js/app.js` — client-side JavaScript source
- `dist/` — built output (do not edit directly)
- `fonts/` — local font files (Share Tech Mono)
