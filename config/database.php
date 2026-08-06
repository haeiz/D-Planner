<?php
/**
 * Database connection (SQLite via PDO).
 * The .db file is created automatically on first run inside /database.
 */

function getDatabaseConnection(): PDO
{
    $dbDir = __DIR__ . '/../database';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0775, true);
    }

    $dbPath = $dbDir . '/planner.db';

    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    initializeSchema($pdo);

    return $pdo;
}

function initializeSchema(PDO $pdo): void
{
    // Activity templates. A repeating activity (daily/weekdays/weekends) is
    // ONE row here - which days/dates it actually shows up on is computed
    // at read time, not stored per-occurrence. Completion, however, IS
    // per-occurrence (see activity_completions below), since a repeating
    // activity is completed independently each day.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activities (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            title         TEXT NOT NULL,
            description   TEXT,
            start_time    TEXT NOT NULL,   -- 'HH:MM' 24h
            end_time      TEXT NOT NULL,   -- 'HH:MM' 24h
            category      TEXT NOT NULL DEFAULT 'Personal',
            repeat_type   TEXT NOT NULL DEFAULT 'once' CHECK(repeat_type IN ('once','daily','weekdays','weekends')),
            specific_date TEXT,            -- 'YYYY-MM-DD', used when repeat_type = 'once'
            active        INTEGER NOT NULL DEFAULT 1,
            created_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activities_date ON activities(specific_date)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activities_repeat ON activities(repeat_type)");

    // One row per (activity, date) that has been marked done.
    // Absence of a row = not completed. This is what lets a single
    // "daily" activity template be completed on Monday but not Tuesday.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_completions (
            id              INTEGER PRIMARY KEY AUTOINCREMENT,
            activity_id     INTEGER NOT NULL REFERENCES activities(id) ON DELETE CASCADE,
            completion_date TEXT NOT NULL,  -- 'YYYY-MM-DD'
            completed_at    TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(activity_id, completion_date)
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_completions_date ON activity_completions(completion_date)");

    // Top 3 daily priorities (position 1-3, per date)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS priorities (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            date         TEXT NOT NULL,
            position     INTEGER NOT NULL CHECK(position BETWEEN 1 AND 3),
            text         TEXT NOT NULL DEFAULT '',
            completed    INTEGER NOT NULL DEFAULT 0,
            UNIQUE(date, position)
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_priorities_date ON priorities(date)");

    // Simple to-do checklist, per date
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS todos (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            date         TEXT NOT NULL,
            text         TEXT NOT NULL,
            completed    INTEGER NOT NULL DEFAULT 0,
            position     INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_todos_date ON todos(date)");

    // One free-text note per day
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notes (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            date         TEXT NOT NULL UNIQUE,
            content      TEXT NOT NULL DEFAULT '',
            updated_at   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Auth (same pattern as the spending tracker - single/small-user app)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id                INTEGER PRIMARY KEY AUTOINCREMENT,
            username          TEXT NOT NULL UNIQUE,
            password_hash     TEXT NOT NULL,
            failed_attempts   INTEGER NOT NULL DEFAULT 0,
            locked_until      TEXT,
            created_at        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at        TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
}
