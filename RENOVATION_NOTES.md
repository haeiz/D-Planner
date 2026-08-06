# Daily Planner — Renovation Notes

## Updated files

- `app.php` — rebuilt the planner shell, daily header, timeline, Daily Focus sidebar, weekly view, and activity modal.
- `assets/css/style.css` — introduced semantic light/dark design tokens, responsive layouts, timeline styling, form states, and accessibility improvements.
- `assets/js/app.js` — added timeline rendering, duration calculations, current-activity progress, richer progress summaries, quick durations, mobile weekly navigation, and improved error handling.
- `index.php` — replaced the original landing-page preview with a realistic miniature of the renovated planner.
- `assets/css/landing.css` — redesigned the landing page for desktop and mobile.
- `assets/css/auth.css` — aligned login/setup screens with the new semantic design system.

## Preserved functionality

The renovation keeps the existing PHP/SQLite API and database schema unchanged. Existing activities, repeating schedules, per-day completion records, priorities, tasks, notes, authentication, CSRF protection, notifications, and password changes continue to use the original endpoints.

## Main interface changes

- Centred application shell with a maximum width of 1400px.
- Sticky, translucent navigation with a compact Daily/Weekly switch.
- Stronger day heading with completed-count and next-activity context.
- Daily activities displayed as a time-based vertical timeline.
- Current activity displays remaining time and live progress.
- Completed activities remain readable instead of fading the whole card.
- Priorities, tasks, and notes combined into one sticky Daily Focus panel.
- Weekly desktop cards use restrained category accents.
- Weekly view becomes a vertical agenda on mobile.
- Activity modal includes duration feedback and 30-minute, 1-hour, and 2-hour shortcuts.
- Semantic theme variables cover cards, forms, menus, modals, and dark mode.
- Essential controls remain visible on touch devices and include accessible labels.

## Validation completed

- PHP syntax checked across all PHP files.
- JavaScript syntax checked with Node.
- CSS parsed successfully with no stylesheet parse errors.
- HTML IDs, JavaScript ID selectors, and label targets were cross-checked.
- The existing SQLite database file was not modified.

## Local test

```bash
cd daily-planner
php -S localhost:8000
```

Open `http://localhost:8000` and test the daily view, weekly view, activity modal, light/dark theme, priorities, tasks, and notes at desktop and mobile widths.
