<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requirePageLogin();
getDatabaseConnection();
$csrfToken = getCsrfToken();
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">
  <title>Daily — Your Planner</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

  <nav class="app-topbar" aria-label="Primary navigation">
    <div class="app-shell topbar-inner">
      <a href="index.php" class="brand text-decoration-none" aria-label="Daily home">
        <span class="brand-mark" aria-hidden="true">◐</span>
        <span>Daily</span>
      </a>

      <ul class="nav nav-pills view-tabs" aria-label="Planner view">
        <li class="nav-item"><button type="button" class="nav-link active" data-view="daily">Daily</button></li>
        <li class="nav-item"><button type="button" class="nav-link" data-view="weekly">Weekly</button></li>
      </ul>

      <div class="topbar-actions">
        <button type="button" class="icon-button" id="btnThemeToggle" aria-label="Switch to dark theme"
          title="Toggle theme">
          <i class="bi bi-moon-stars" aria-hidden="true"></i>
        </button>
        <button type="button" class="btn btn-primary-app btn-add-activity" id="btnAddActivity">
          <i class="bi bi-plus-lg" aria-hidden="true"></i>
          <span class="btn-add-label">Add activity</span>
        </button>
        <div class="dropdown">
          <button class="profile-button dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <span class="profile-avatar"
              aria-hidden="true"><?= htmlspecialchars(strtoupper(substr($username, 0, 1))) ?></span>
            <span class="profile-name d-none d-md-inline"><?= htmlspecialchars($username) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><button type="button" class="dropdown-item" id="btnOpenChangePassword"><i
                  class="bi bi-key me-2"></i>Change password</button></li>
            <li>
              <hr class="dropdown-divider">
            </li>
            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Log out</a></li>
          </ul>
        </div>
      </div>
    </div>
  </nav>

  <main class="app-shell app-main">
    <section class="day-overview" aria-labelledby="dateLabel">
      <div class="date-heading-wrap">
        <span class="date-eyebrow" id="dateDayLabel">—</span>
        <h1 class="date-heading" id="dateLabel">—</h1>
        <p class="date-context" id="dateContext">Plan the day at a calm, useful pace.</p>
      </div>

      <div class="date-nav" aria-label="Date navigation">
        <button class="icon-button" id="btnPrevDate" type="button" aria-label="Previous date">
          <i class="bi bi-chevron-left" aria-hidden="true"></i>
        </button>

        <div class="calendar-picker-wrap">
          <button type="button" class="calendar-picker-control" id="btnOpenDatePicker" aria-label="Choose a date"
            title="Choose a date">
            <i class="bi bi-calendar3" aria-hidden="true"></i>
            <span class="calendar-picker-text">Choose date</span>
          </button>

          <input type="date" id="datePicker" class="calendar-picker-input" aria-label="Choose a date" tabindex="-1">
        </div>

        <button class="btn btn-secondary-app" id="btnToday" type="button">
          Today
        </button>

        <button class="icon-button" id="btnNextDate" type="button" aria-label="Next date">
          <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </button>
      </div>
    </section>

    <div id="dailyView">
      <section class="daily-progress-card" aria-label="Daily progress">
        <div class="progress-copy">
          <span class="section-kicker">Today’s progress</span>
          <strong class="progress-summary" id="progressSummary">No activities yet</strong>
          <span class="next-activity" id="nextActivity">Add an activity to start shaping your day.</span>
        </div>
        <div class="progress-visual">
          <span class="progress-pct" id="progressPct">0%</span>
          <div class="progress" role="progressbar" aria-label="Completed activities" aria-valuemin="0"
            aria-valuemax="100" aria-valuenow="0">
            <div class="progress-bar" id="progressBar" style="width:0%"></div>
          </div>
        </div>
      </section>

      <div class="daily-layout">
        <section class="schedule-panel" aria-labelledby="scheduleHeading">
          <div class="section-heading-row">
            <div>
              <span class="section-kicker">Schedule</span>
              <h2 id="scheduleHeading">Your timeline</h2>
            </div>
            <span class="schedule-count" id="scheduleCount">0 activities</span>
          </div>

          <div class="activity-timeline" id="activityList"></div>
          <div class="empty-state d-none" id="dailyEmptyState">
            <div class="empty-icon"><i class="bi bi-calendar2-heart"></i></div>
            <h3>Nothing scheduled yet</h3>
            <p>Build a realistic day one activity at a time.</p>
            <button type="button" class="btn btn-primary-app" data-empty-add>
              <i class="bi bi-plus-lg"></i> Add your first activity
            </button>
          </div>
        </section>

        <aside class="daily-sidebar" aria-label="Daily focus">
          <div class="daily-focus-card">
            <div class="daily-focus-header">
              <div>
                <span class="section-kicker">Daily focus</span>
                <h2>What matters today</h2>
              </div>
              <i class="bi bi-stars" aria-hidden="true"></i>
            </div>

            <section class="focus-section" aria-labelledby="prioritiesHeading">
              <div class="focus-section-heading">
                <h3 id="prioritiesHeading"><i class="bi bi-star-fill"></i> Top priorities</h3>
                <span>Choose three</span>
              </div>
              <div id="prioritiesList"></div>
            </section>

            <section class="focus-section" aria-labelledby="todosHeading">
              <div class="focus-section-heading">
                <h3 id="todosHeading"><i class="bi bi-check2-square"></i> Tasks</h3>
                <span id="todoCount">0 open</span>
              </div>
              <div id="todosList"></div>
              <div class="todo-add-row">
                <input type="text" class="form-control" id="newTodoInput" placeholder="Add a small task…"
                  aria-label="New task">
                <button class="icon-button" id="btnAddTodo" type="button" aria-label="Add task"><i
                    class="bi bi-plus-lg"></i></button>
              </div>
            </section>

            <section class="focus-section focus-section-notes" aria-labelledby="notesHeading">
              <div class="focus-section-heading">
                <h3 id="notesHeading"><i class="bi bi-journal-text"></i> Notes</h3>
                <span class="autosave-label"><i class="bi bi-cloud-check"></i> Auto-saved</span>
              </div>
              <textarea class="form-control" id="notesTextarea"
                placeholder="Capture thoughts, reminders, or context for today…"></textarea>
            </section>
          </div>
        </aside>
      </div>
    </div>

    <div id="weeklyView" class="d-none">
      <section class="weekly-panel" aria-labelledby="weeklyHeading">
        <div class="section-heading-row weekly-heading-row">
          <div>
            <span class="section-kicker">Week at a glance</span>
            <h2 id="weeklyHeading">Seven calm days</h2>
          </div>
          <p>Choose a day to open its full timeline.</p>
        </div>
        <div class="week-grid" id="weekGrid"></div>
      </section>
    </div>
  </main>

  <button type="button" class="mobile-add-button" id="btnMobileAddActivity" aria-label="Add activity">
    <i class="bi bi-plus-lg"></i>
  </button>

  <div class="modal fade" id="activityModal" tabindex="-1" aria-labelledby="activityModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <span class="modal-kicker">Schedule details</span>
            <h2 class="modal-title" id="activityModalTitle"><i class="bi bi-calendar-plus"></i> Add activity</h2>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="activityForm" novalidate>
            <div class="mb-4">
              <label class="form-label" for="fieldTitle">Activity title</label>
              <input type="text" class="form-control form-control-lg activity-title-input" id="fieldTitle" required
                placeholder="e.g. Morning workout">
            </div>

            <div class="row g-3">
              <div class="col-12" id="specificDateWrap">
                <label class="form-label" for="fieldSpecificDate">Date</label>
                <input type="date" class="form-control" id="fieldSpecificDate">
              </div>

              <div class="col-6">
                <label class="form-label" for="fieldStartTime">Start time</label>
                <input type="time" class="form-control" id="fieldStartTime" required>
              </div>
              <div class="col-6">
                <label class="form-label" for="fieldEndTime">End time</label>
                <input type="time" class="form-control" id="fieldEndTime" required>
              </div>

              <div class="col-12">
                <div class="duration-helper">
                  <span id="activityDurationLabel">Choose a start and end time</span>
                  <div class="quick-duration-buttons" aria-label="Quick duration">
                    <button type="button" data-duration="30">30 min</button>
                    <button type="button" data-duration="60">1 hour</button>
                    <button type="button" data-duration="120">2 hours</button>
                  </div>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label" for="fieldCategory">Category</label>
                <div class="category-select-wrap">
                  <span class="category-preview-dot" id="categoryPreviewDot" aria-hidden="true"></span>
                  <select class="form-select" id="fieldCategory"></select>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label d-block mb-2">Repeats</label>
                <div class="repeat-toggle" role="group" aria-label="Repeat schedule">
                  <input type="radio" class="btn-check" name="repeat" id="repeatOnce" value="once" checked>
                  <label class="btn" for="repeatOnce">Once</label>
                  <input type="radio" class="btn-check" name="repeat" id="repeatDaily" value="daily">
                  <label class="btn" for="repeatDaily">Daily</label>
                  <input type="radio" class="btn-check" name="repeat" id="repeatWeekdays" value="weekdays">
                  <label class="btn" for="repeatWeekdays">Weekdays</label>
                  <input type="radio" class="btn-check" name="repeat" id="repeatWeekends" value="weekends">
                  <label class="btn" for="repeatWeekends">Weekends</label>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label" for="fieldDescription">Description <span
                    class="optional-label">Optional</span></label>
                <textarea class="form-control" id="fieldDescription" rows="3"
                  placeholder="Add useful context without overloading the day."></textarea>
              </div>
            </div>
            <input type="hidden" id="fieldActivityId">
          </form>
        </div>
        <div class="modal-footer activity-modal-footer">
          <button type="button" class="btn btn-danger-ghost d-none" id="btnDeleteActivity">
            <i class="bi bi-trash"></i> Delete
          </button>
          <div class="modal-footer-actions">
            <button type="button" class="btn btn-secondary-app" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary-app" id="btnSaveActivity">
              <i class="bi bi-check-lg"></i> Save activity
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordTitle"
    aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <span class="modal-kicker">Account security</span>
            <h2 class="modal-title" id="changePasswordTitle"><i class="bi bi-key"></i> Change password</h2>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-danger py-2 small d-none" id="changePasswordError"></div>
          <div class="mb-3">
            <label class="form-label" for="fieldCurrentPassword">Current password</label>
            <input type="password" class="form-control" id="fieldCurrentPassword">
          </div>
          <div class="mb-3">
            <label class="form-label" for="fieldNewPassword">New password</label>
            <input type="password" class="form-control" id="fieldNewPassword" minlength="8">
          </div>
          <div class="mb-1">
            <label class="form-label" for="fieldNewPasswordConfirm">Confirm new password</label>
            <input type="password" class="form-control" id="fieldNewPasswordConfirm" minlength="8">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary-app" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary-app" id="btnSaveNewPassword">
            <i class="bi bi-check-lg"></i> Update password
          </button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/app.js"></script>
</body>

</html>