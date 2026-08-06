# Daily — Daily Schedule Planner (V1)

A calm, time-blocked daily planner built with **PHP 8**, **SQLite**, **Bootstrap 5**, and vanilla **JavaScript**. This is the **V1 (MVP)** build from the phased roadmap — the smallest version built to answer one question: *will this get used daily?*

## What's in V1

- **Time-blocked schedule**: add activities with a title, start/end time, category, and optional description
- **Repeating activities**: once, daily, weekdays, or weekends — each occurrence completes independently (finishing Monday's workout doesn't mark Tuesday's done)
- **Daily view**: today's schedule with the current activity auto-highlighted, plus a completion progress bar
- **Weekly view**: a 7-day grid overview, click any day to jump into its daily view
- **Planner sidebar**: top 3 daily priorities, a to-do checklist, and a free-text notes field — all scoped per day
- **Categories**: Work, Study, Exercise, Meals, Personal, Family, Rest — fixed set for V1, each with its own color
- **Light/dark theme** toggle
- **Browser notifications** 5 minutes before an activity starts, while the app tab is open (see limitation below)
- **Login required**: same hardened auth as the spending tracker — one-time setup, bcrypt passwords, CSRF-protected API, login rate limiting, session cookie auto-secured over HTTPS

## Deliberately NOT in V1

Per the roadmap, these are V2/V3 — building them now would risk shipping months of work before learning if anyone uses the core loop daily:
- Drag-and-drop reordering, custom categories, habit tracker, journaling, Pomodoro timer
- PDF/image export, Canva-style templates, calendar sync (Google Calendar, etc.)
- "Smart" auto-scheduling/suggestions, natural-language entry
- Native iOS/Android apps

See the roadmap doc for the full phased plan.

## Requirements

- PHP **8.0+** with `pdo_sqlite`
- Any web server (Apache/Nginx) or PHP's built-in server for local use

## Getting started

```bash
cd daily-planner
php -S localhost:8000
```
Open **http://localhost:8000** — you'll land on the intro page; click through to set up your account.

For real hosting (shared hosting or a VPS), follow the same steps as the spending tracker's deployment guide — the folder structure and `.htaccess` protections are identical:
1. Upload the files, make `database/` writable (`chmod 775`)
2. Confirm `.htaccess` is honored (visit `/database/planner.db` — must return 403, not download)
3. Enable HTTPS

## Notification limitation (read this before relying on it)

Reminders use the browser's `Notification` API, fired by JavaScript while the app tab is open. This means:
- ✅ Works well on desktop and Android while the tab/app is open
- ❌ Won't fire if the browser is closed, or (especially) on iOS Safari, which restricts background web notifications

This is a known platform limitation, not a bug — the roadmap's V3 phase covers wrapping this as a native app specifically to get reliable iOS push notifications once V1 proves the core planner gets used.

## Project structure

```
daily-planner/
├── index.php                   Landing page (public)
├── app.php                     The app (requires login)
├── setup.php / login.php / logout.php
├── config/database.php         SQLite connection + schema
├── includes/
│   ├── functions.php           Categories, repeat-schedule date logic, JSON helpers
│   └── auth.php                Session login, CSRF, rate limiting (same pattern as spending tracker)
├── api/
│   ├── activities.php          GET (expanded per-day/week) / POST / PUT (edit + complete) / DELETE
│   ├── planner.php             Priorities / todos / notes (?resource=priorities|todos|notes)
│   └── auth.php                Change password
├── assets/
│   ├── css/style.css           Design system (warm "notebook" theme - distinct from the spending tracker)
│   ├── css/landing.css
│   ├── css/auth.css
│   └── js/app.js               All app logic: views, modals, reminders, planner sidebar
├── scripts/seed.php            Demo data generator
└── database/                   planner.db is created here automatically
```

## How repeating activities work (the core design decision)

An activity is stored **once** as a template (`activities` table) with a `repeat_type` of `once`, `daily`, `weekdays`, or `weekends`. Which actual calendar dates it appears on is computed on read (`activityOccursOn()` in `includes/functions.php`), not stored per-day.

Completion, however, **is** tracked per-day (`activity_completions` table, one row per `activity_id` + date). This is what makes a daily "Morning workout" completable on Monday without affecting Tuesday's occurrence — verified during testing.

## Customization

- **Categories**: edit the `CATEGORIES` constant in `includes/functions.php` (and the fallback list in `assets/js/app.js`'s `populateCategorySelect`) to change names/colors.
- **Reminder window**: change the `5` in `checkUpcomingReminders()` in `app.js` to adjust how far ahead notifications fire.
- **Week start**: weeks start Monday; change `weekBounds()` in `app.js` and `weekRange()` in `functions.php` for Sunday-start instead.
