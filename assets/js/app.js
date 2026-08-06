/**
 * Daily planner front-end controller.
 * Keeps the original API/data model while presenting the schedule as a timeline.
 */
(() => {
  const API_ACTIVITIES = 'api/activities.php';
  const API_PLANNER = 'api/planner.php';

  const state = {
    view: 'daily',
    date: new Date(),
    categories: {},
    todayActivities: [],
    notifiedIds: new Set(),
    notesSaveTimer: null,
    priorityTimers: {},
  };

  const $ = (selector, context = document) => context.querySelector(selector);
  const $$ = (selector, context = document) => Array.from(context.querySelectorAll(selector));

  const isoDate = (date) => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const todayStr = () => isoDate(new Date());
  const rawFetch = window.fetch.bind(window);
  const csrfToken = () => $('meta[name="csrf-token"]')?.content || '';

  async function apiFetch(url, options = {}) {
    const method = (options.method || 'GET').toUpperCase();
    if (['POST', 'PUT', 'DELETE'].includes(method)) {
      options.headers = { ...(options.headers || {}), 'X-CSRF-Token': csrfToken() };
    }

    const response = await rawFetch(url, options);
    if (response.status === 401) {
      window.location.href = 'login.php';
      throw new Error('Session expired');
    }
    return response;
  }

  document.addEventListener('DOMContentLoaded', () => {
    applyStoredTheme();
    bindTopbar();
    bindDateNav();
    bindActivityModal();
    bindPlannerSidebar();
    bindChangePassword();

    requestNotificationPermission();
    setInterval(checkUpcomingReminders, 30 * 1000);
    setInterval(() => {
      highlightCurrentActivity();
      if (state.view === 'daily') renderProgress(state.todayActivities);
    }, 30 * 1000);

    render();
  });

  function render() {
    renderDateHeader();

    const daily = state.view === 'daily';
    $('#dailyView').classList.toggle('d-none', !daily);
    $('#weeklyView').classList.toggle('d-none', daily);

    if (daily) loadDailyView();
    else loadWeeklyView();
  }

  function renderDateHeader() {
    const dayLabel = $('#dateDayLabel');
    const dateLabel = $('#dateLabel');
    const context = $('#dateContext');
    const todayButton = $('#btnToday');
    const datePicker = $('#datePicker');

if (datePicker) {
  datePicker.value = isoDate(state.date);
}

    if (state.view === 'weekly') {
      const [start, end] = weekBounds(state.date);
      const sameMonth = start.getMonth() === end.getMonth();
      dayLabel.textContent = 'Week overview';
      dateLabel.textContent = sameMonth
        ? `${start.toLocaleDateString('en-GB', { day: 'numeric' })}–${end.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })}`
        : `${start.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}–${end.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}`;
      context.textContent = 'See the shape of your week, then choose a day to focus on.';
      const [todayWeekStart] = weekBounds(new Date());
      todayButton.classList.toggle('d-none', isoDate(start) === isoDate(todayWeekStart));
      return;
    }

    const selected = state.date;
    const isToday = isoDate(selected) === todayStr();
    dayLabel.textContent = isToday ? 'Today' : selected.toLocaleDateString('en-GB', { weekday: 'long' });
    dateLabel.textContent = selected.toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' });
    context.textContent = isToday
      ? 'Plan the day at a calm, useful pace.'
      : selected < startOfToday()
        ? 'A clear record of how this day was planned.'
        : 'Shape this day before it arrives.';
    todayButton.classList.toggle('d-none', isToday);
  }

  function startOfToday() {
    const date = new Date();
    date.setHours(0, 0, 0, 0);
    return date;
  }

  function weekBounds(date) {
    const start = new Date(date);
    start.setHours(0, 0, 0, 0);
    const dayOfWeek = (start.getDay() + 6) % 7;
    start.setDate(start.getDate() - dayOfWeek);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);
    return [start, end];
  }

  /* --------------------------------------------------------- topbar --- */

  function bindTopbar() {
    $$('.view-tabs .nav-link').forEach((button) => {
      button.addEventListener('click', () => {
        $$('.view-tabs .nav-link').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
        state.view = button.dataset.view;
        render();
      });
    });

    $('#btnThemeToggle').addEventListener('click', toggleTheme);
    $('#btnAddActivity').addEventListener('click', () => openActivityModal(null));
    $('#btnMobileAddActivity').addEventListener('click', () => openActivityModal(null));
    $('[data-empty-add]').addEventListener('click', () => openActivityModal(null));
  }

  function applyStoredTheme() {
    const savedTheme = localStorage.getItem('planner-theme');
    if (savedTheme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    updateThemeButton();
  }

  function toggleTheme() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    if (isDark) {
      document.documentElement.removeAttribute('data-theme');
      localStorage.setItem('planner-theme', 'light');
    } else {
      document.documentElement.setAttribute('data-theme', 'dark');
      localStorage.setItem('planner-theme', 'dark');
    }
    updateThemeButton();
    if (state.view === 'daily' && state.todayActivities.length) {
      renderActivityList(state.todayActivities);
      highlightCurrentActivity();
    }
  }

  function updateThemeButton() {
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const button = $('#btnThemeToggle');
    button.innerHTML = `<i class="bi bi-${isDark ? 'sun' : 'moon-stars'}" aria-hidden="true"></i>`;
    button.setAttribute('aria-label', isDark ? 'Switch to light theme' : 'Switch to dark theme');
  }

  /* ------------------------------------------------------- date nav --- */

  function bindDateNav() {
  const datePicker = $('#datePicker');
  const openDatePickerButton = $('#btnOpenDatePicker');

  $('#btnPrevDate').addEventListener('click', () => {
    shiftDate(-1);
  });

  $('#btnNextDate').addEventListener('click', () => {
    shiftDate(1);
  });

  $('#btnToday').addEventListener('click', () => {
    state.date = startOfToday();
    render();
  });

  if (datePicker && openDatePickerButton) {
    openDatePickerButton.addEventListener('click', () => {
      try {
        if (typeof datePicker.showPicker === 'function') {
          datePicker.showPicker();
        } else {
          datePicker.focus();
          datePicker.click();
        }
      } catch (error) {
        datePicker.focus();
        datePicker.click();
      }
    });

    datePicker.addEventListener('change', () => {
      if (!datePicker.value) {
        return;
      }

      const selectedDate = parseLocalDate(datePicker.value);

      if (!selectedDate) {
        showToast('The selected date is not valid', true);
        return;
      }

      state.date = selectedDate;
      render();
    });
  }
}

  function shiftDate(direction) {
    const selected = new Date(state.date);
    selected.setDate(selected.getDate() + direction * (state.view === 'weekly' ? 7 : 1));
    state.date = selected;
    render();
  }

  /* ----------------------------------------------------- daily view --- */

  async function loadDailyView() {
    const date = isoDate(state.date);

    try {
      const [activityResponse, priorityResponse, todoResponse, noteResponse] = await Promise.all([
        apiFetch(`${API_ACTIVITIES}?date=${date}`),
        apiFetch(`${API_PLANNER}?resource=priorities&date=${date}`),
        apiFetch(`${API_PLANNER}?resource=todos&date=${date}`),
        apiFetch(`${API_PLANNER}?resource=notes&date=${date}`),
      ]);

      const [activityJson, priorityJson, todoJson, noteJson] = await Promise.all([
        activityResponse.json(),
        priorityResponse.json(),
        todoResponse.json(),
        noteResponse.json(),
      ]);

      if (activityJson.success) {
        state.categories = activityJson.categories || {};
        state.todayActivities = activityJson.activities || [];
        renderActivityList(state.todayActivities);
        renderProgress(state.todayActivities);
        highlightCurrentActivity();
      }
      if (priorityJson.success) renderPriorities(priorityJson.priorities || []);
      if (todoJson.success) renderTodos(todoJson.todos || []);
      if (noteJson.success) $('#notesTextarea').value = noteJson.content || '';
    } catch (error) {
      showToast(error.message || 'Could not load this day', true);
    }
  }

  function renderProgress(activities) {
    const total = activities.length;
    const completed = activities.filter((activity) => Boolean(activity.completed)).length;
    const percentage = total === 0 ? 0 : Math.round((completed / total) * 100);

    $('#progressPct').textContent = `${percentage}%`;
    $('#progressBar').style.width = `${percentage}%`;
    $('.daily-progress-card .progress').setAttribute('aria-valuenow', String(percentage));
    $('#progressSummary').textContent = total === 0
      ? 'No activities yet'
      : completed === total
        ? `All ${total} activities completed`
        : `${completed} of ${total} ${pluralize('activity', total)} completed`;

    const nextText = getNextActivityText(activities);
    $('#nextActivity').textContent = nextText;
  }

  function getNextActivityText(activities) {
    if (!activities.length) return 'Add an activity to start shaping your day.';

    const incomplete = activities.filter((activity) => !activity.completed);
    if (!incomplete.length) return 'Your schedule is complete. Leave some space to recharge.';

    if (isoDate(state.date) !== todayStr()) {
      const first = incomplete[0];
      return `First up: ${first.title} at ${formatClock(first.start_time)}.`;
    }

    const nowMinutes = new Date().getHours() * 60 + new Date().getMinutes();
    const current = incomplete.find((activity) => nowMinutes >= timeToMinutes(activity.start_time) && nowMinutes < timeToMinutes(activity.end_time));
    if (current) return `Now: ${current.title} · ${formatRemaining(timeToMinutes(current.end_time) - nowMinutes)} remaining.`;

    const next = incomplete.find((activity) => timeToMinutes(activity.start_time) > nowMinutes);
    if (next) return `Next: ${next.title} at ${formatClock(next.start_time)}.`;

    return `${incomplete.length} unfinished ${pluralize('activity', incomplete.length)} left from earlier today.`;
  }

  function renderActivityList(activities) {
    const list = $('#activityList');
    const emptyState = $('#dailyEmptyState');
    list.innerHTML = '';

    $('#scheduleCount').textContent = `${activities.length} ${pluralize('activity', activities.length)}`;

    if (!activities.length) {
      emptyState.classList.remove('d-none');
      return;
    }
    emptyState.classList.add('d-none');

    activities.forEach((activity) => {
      const color = normalizeColor(activity.color);
      const tint = colorToRgba(color, document.documentElement.getAttribute('data-theme') === 'dark' ? 0.15 : 0.09);
      const duration = formatDuration(timeToMinutes(activity.end_time) - timeToMinutes(activity.start_time));
      const row = document.createElement('div');
      row.className = `timeline-row activity-item${activity.completed ? ' is-completed' : ''}`;
      row.dataset.id = activity.id;
      row.dataset.start = activity.start_time;
      row.dataset.end = activity.end_time;
      row.style.setProperty('--activity-color', color);
      row.style.setProperty('--activity-tint', tint);

      row.innerHTML = `
        <div class="timeline-time" aria-hidden="true">
          <strong>${formatClock(activity.start_time)}</strong>
          <span>${formatClock(activity.end_time)}</span>
        </div>
        <div class="timeline-rail"><span class="timeline-dot"></span></div>
        <div class="activity-card">
          <button type="button" class="activity-check ${activity.completed ? 'checked' : ''}" data-toggle-id="${activity.id}" aria-label="${activity.completed ? 'Mark incomplete' : 'Mark complete'}">
            ${activity.completed ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : ''}
          </button>
          <button type="button" class="activity-main" data-edit-id="${activity.id}" aria-label="Edit ${escapeHtml(activity.title)}">
            <span class="activity-title-row">
              <span class="activity-title">${escapeHtml(activity.title)}</span>
            </span>
            ${activity.description ? `<span class="activity-desc">${escapeHtml(activity.description)}</span>` : ''}
            <span class="activity-meta">
              <span class="category-chip">${escapeHtml(activity.category)}</span>
              <span class="meta-separator">•</span>
              <span>${duration}</span>
              ${activity.repeat_type !== 'once' ? `<span class="meta-separator">•</span><span>${formatRepeat(activity.repeat_type)}</span>` : ''}
            </span>
          </button>
          <div class="activity-actions">
            <span class="current-badge" data-current-badge>Now</span>
            <button type="button" class="activity-edit-button" data-edit-id="${activity.id}" aria-label="Edit ${escapeHtml(activity.title)}">
              <i class="bi bi-three-dots" aria-hidden="true"></i>
            </button>
          </div>
          <div class="current-progress" aria-hidden="true"><span data-current-progress></span></div>
        </div>
      `;
      list.appendChild(row);
    });

    $$('[data-toggle-id]', list).forEach((button) => {
      button.addEventListener('click', (event) => {
        event.stopPropagation();
        toggleCompletion(button.dataset.toggleId);
      });
    });

    $$('[data-edit-id]', list).forEach((button) => {
      button.addEventListener('click', () => {
        const activity = state.todayActivities.find((item) => String(item.id) === String(button.dataset.editId));
        if (activity) openActivityModal(activity);
      });
    });
  }

function parseLocalDate(value) {
  if (!value) {
    return null;
  }

  const parts = value.split('-').map(Number);

  if (parts.length !== 3) {
    return null;
  }

  const [year, month, day] = parts;
  const date = new Date(year, month - 1, day);

  if (
    date.getFullYear() !== year ||
    date.getMonth() !== month - 1 ||
    date.getDate() !== day
  ) {
    return null;
  }

  date.setHours(0, 0, 0, 0);

  return date;
}

  function highlightCurrentActivity() {
    const isToday = state.view === 'daily' && isoDate(state.date) === todayStr();
    const now = new Date();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();

    $$('.activity-item').forEach((element) => {
      const start = timeToMinutes(element.dataset.start);
      const end = timeToMinutes(element.dataset.end);
      const isCurrent = isToday && !element.classList.contains('is-completed') && nowMinutes >= start && nowMinutes < end;
      element.classList.toggle('is-current', isCurrent);

      const badge = element.querySelector('[data-current-badge]');
      const meter = element.querySelector('[data-current-progress]');
      if (!isCurrent) {
        if (badge) badge.textContent = 'Now';
        if (meter) meter.style.width = '0%';
        return;
      }

      const total = Math.max(1, end - start);
      const elapsed = Math.max(0, nowMinutes - start);
      const percentage = Math.min(100, Math.round((elapsed / total) * 100));
      if (badge) badge.textContent = `Now · ${formatRemaining(end - nowMinutes)} left`;
      if (meter) meter.style.width = `${percentage}%`;
    });
  }

  async function toggleCompletion(id) {
    const activity = state.todayActivities.find((item) => String(item.id) === String(id));
    if (!activity) return;

    const completed = !activity.completed;
    try {
      const response = await apiFetch(`${API_ACTIVITIES}?id=${id}&action=complete`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date: isoDate(state.date), completed }),
      });
      const json = await response.json();
      if (!json.success) throw new Error(json.error || 'Could not update activity');

      activity.completed = completed;
      renderActivityList(state.todayActivities);
      renderProgress(state.todayActivities);
      highlightCurrentActivity();
    } catch (error) {
      showToast(error.message, true);
    }
  }

  /* ---------------------------------------------------- weekly view --- */

  async function loadWeeklyView() {
    const [start, end] = weekBounds(state.date);

    try {
      const response = await apiFetch(`${API_ACTIVITIES}?start=${isoDate(start)}&end=${isoDate(end)}`);
      const json = await response.json();
      if (!json.success) throw new Error(json.error || 'Could not load this week');

      state.categories = json.categories || {};
      const byDate = {};
      (json.activities || []).forEach((activity) => {
        byDate[activity.date] ??= [];
        byDate[activity.date].push(activity);
      });

      const grid = $('#weekGrid');
      grid.innerHTML = '';
      const cursor = new Date(start);

      for (let index = 0; index < 7; index += 1) {
        const dayDate = new Date(cursor);
        const date = isoDate(dayDate);
        const activities = (byDate[date] || []).sort((a, b) => a.start_time.localeCompare(b.start_time));
        const visibleActivities = activities.slice(0, 4);
        const remaining = activities.length - visibleActivities.length;
        const card = document.createElement('article');
        card.className = `week-day-card${date === todayStr() ? ' is-today' : ''}`;
        card.tabIndex = 0;
        card.setAttribute('role', 'button');
        card.setAttribute('aria-label', `Open ${dayDate.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' })}`);

        card.innerHTML = `
          <div class="week-day-header">
            <div>
              <span class="week-day-name">${dayDate.toLocaleDateString('en-GB', { weekday: 'short' })}</span>
              <span class="week-day-number">${dayDate.getDate()}</span>
            </div>
            <span class="week-day-count">${activities.length} ${pluralize('activity', activities.length)}</span>
          </div>
          <div class="week-activity-list">
            ${visibleActivities.map((activity) => {
              const color = normalizeColor(activity.color);
              return `
                <div class="week-activity-chip ${activity.completed ? 'is-completed' : ''}" style="--activity-color:${color}">
                  <span class="week-activity-time">${formatClock(activity.start_time)}</span>
                  <span class="week-activity-title">${escapeHtml(activity.title)}</span>
                </div>
              `;
            }).join('') || '<div class="week-empty">Open space</div>'}
            ${remaining > 0 ? `<span class="week-more">+${remaining} more</span>` : ''}
          </div>
        `;

        const openDay = () => {
          state.date = new Date(dayDate);
          state.view = 'daily';
          $$('.view-tabs .nav-link').forEach((button) => button.classList.remove('active'));
          $('.view-tabs .nav-link[data-view="daily"]').classList.add('active');
          render();
          window.scrollTo({ top: 0, behavior: prefersReducedMotion() ? 'auto' : 'smooth' });
        };

        card.addEventListener('click', openDay);
        card.addEventListener('keydown', (event) => {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openDay();
          }
        });
        grid.appendChild(card);
        cursor.setDate(cursor.getDate() + 1);
      }
    } catch (error) {
      showToast(error.message, true);
    }
  }

  /* --------------------------------------------------- activity modal --- */

  function bindActivityModal() {
    $$('input[name="repeat"]').forEach((radio) => radio.addEventListener('change', updateRepeatDateVisibility));
    $('#fieldStartTime').addEventListener('input', updateDurationLabel);
    $('#fieldEndTime').addEventListener('input', updateDurationLabel);
    $('#fieldCategory').addEventListener('change', updateCategoryPreview);
    $$('[data-duration]').forEach((button) => button.addEventListener('click', () => applyQuickDuration(Number(button.dataset.duration))));
    $('#btnSaveActivity').addEventListener('click', saveActivity);
    $('#btnDeleteActivity').addEventListener('click', deleteActivity);
  }

  function populateCategorySelect() {
    const fallback = {
      Work: '#4A6FA5',
      Study: '#7B5EA7',
      Exercise: '#D97706',
      Meals: '#4C9A6A',
      Personal: '#C2547B',
      Family: '#2A9D8F',
      Rest: '#6B7280',
    };
    const categories = Object.keys(state.categories).length ? state.categories : fallback;
    const select = $('#fieldCategory');
    select.innerHTML = Object.entries(categories)
      .map(([name, color]) => `<option value="${escapeHtml(name)}" data-color="${normalizeColor(color)}">${escapeHtml(name)}</option>`)
      .join('');
  }

  function openActivityModal(activity) {
    populateCategorySelect();
    $('#activityForm').reset();
    $('#fieldActivityId').value = '';
    $('#fieldSpecificDate').value = isoDate(state.date);

    if (activity) {
      $('#activityModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit activity';
      $('#fieldActivityId').value = activity.id;
      $('#fieldTitle').value = activity.title;
      $('#fieldStartTime').value = activity.start_time;
      $('#fieldEndTime').value = activity.end_time;
      $('#fieldCategory').value = activity.category;
      $('#fieldDescription').value = activity.description || '';
      const repeatRadio = $(`input[name="repeat"][value="${activity.repeat_type}"]`);
      if (repeatRadio) repeatRadio.checked = true;
      $('#fieldSpecificDate').value = activity.date || isoDate(state.date);
      $('#btnDeleteActivity').classList.remove('d-none');
    } else {
      $('#activityModalTitle').innerHTML = '<i class="bi bi-calendar-plus"></i> Add activity';
      $('#btnDeleteActivity').classList.add('d-none');
      setSuggestedTimes();
    }

    updateRepeatDateVisibility();
    updateDurationLabel();
    updateCategoryPreview();

    const modal = bootstrap.Modal.getOrCreateInstance($('#activityModal'));
    modal.show();
    $('#activityModal').addEventListener('shown.bs.modal', () => $('#fieldTitle').focus(), { once: true });
  }

  function setSuggestedTimes() {
    const now = new Date();
    const startMinutes = Math.ceil((now.getHours() * 60 + now.getMinutes()) / 30) * 30;
    const safeStart = Math.min(startMinutes, 22 * 60 + 30);
    $('#fieldStartTime').value = minutesToTime(safeStart);
    $('#fieldEndTime').value = minutesToTime(Math.min(safeStart + 60, 23 * 60 + 59));
  }

  function updateRepeatDateVisibility() {
    const repeatType = $('input[name="repeat"]:checked')?.value || 'once';
    $('#specificDateWrap').classList.toggle('d-none', repeatType !== 'once');
  }

  function applyQuickDuration(minutes) {
    const start = timeToMinutes($('#fieldStartTime').value);
    if (!Number.isFinite(start)) return;
    const end = Math.min(start + minutes, 23 * 60 + 59);
    $('#fieldEndTime').value = minutesToTime(end);
    updateDurationLabel();
  }

  function updateDurationLabel() {
    const start = timeToMinutes($('#fieldStartTime').value);
    const end = timeToMinutes($('#fieldEndTime').value);
    const label = $('#activityDurationLabel');

    if (!Number.isFinite(start) || !Number.isFinite(end)) {
      label.textContent = 'Choose a start and end time';
    } else if (end <= start) {
      label.textContent = 'End time must be after start time';
    } else {
      label.textContent = `${formatDuration(end - start)} scheduled`;
    }
  }

  function updateCategoryPreview() {
    const select = $('#fieldCategory');
    const selected = select.options[select.selectedIndex];
    const color = selected?.dataset.color || state.categories[select.value] || '#6B7280';
    $('#categoryPreviewDot').style.background = normalizeColor(color);
  }

  async function saveActivity() {
    const id = $('#fieldActivityId').value;
    const payload = {
      title: $('#fieldTitle').value.trim(),
      start_time: $('#fieldStartTime').value,
      end_time: $('#fieldEndTime').value,
      category: $('#fieldCategory').value,
      description: $('#fieldDescription').value.trim(),
      repeat_type: $('input[name="repeat"]:checked')?.value || 'once',
      specific_date: $('#fieldSpecificDate').value,
    };

    if (!payload.title) {
      showToast('Activity title is required', true);
      $('#fieldTitle').focus();
      return;
    }
    if (!payload.start_time || !payload.end_time) {
      showToast('Start and end time are required', true);
      return;
    }
    if (timeToMinutes(payload.end_time) <= timeToMinutes(payload.start_time)) {
      showToast('End time must be after start time', true);
      $('#fieldEndTime').focus();
      return;
    }
    if (payload.repeat_type === 'once' && !payload.specific_date) {
      showToast('Choose a date for this activity', true);
      $('#fieldSpecificDate').focus();
      return;
    }

    const button = $('#btnSaveActivity');
    button.disabled = true;
    try {
      const response = id
        ? await apiFetch(`${API_ACTIVITIES}?id=${id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          })
        : await apiFetch(API_ACTIVITIES, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
          });

      const json = await response.json();
      if (!json.success) throw new Error(json.error || 'Failed to save activity');

      bootstrap.Modal.getInstance($('#activityModal')).hide();
      showToast(id ? 'Activity updated' : 'Activity added', false);
      render();
    } catch (error) {
      showToast(error.message, true);
    } finally {
      button.disabled = false;
    }
  }

  async function deleteActivity() {
    const id = $('#fieldActivityId').value;
    if (!id || !window.confirm('Delete this activity? Repeating occurrences will also be removed.')) return;

    try {
      const response = await apiFetch(`${API_ACTIVITIES}?id=${id}`, { method: 'DELETE' });
      const json = await response.json();
      if (!json.success) throw new Error(json.error || 'Delete failed');
      bootstrap.Modal.getInstance($('#activityModal')).hide();
      showToast('Activity deleted', false);
      render();
    } catch (error) {
      showToast(error.message, true);
    }
  }

  /* ------------------------------------------------- daily focus --- */

  function bindPlannerSidebar() {
    $('#btnAddTodo').addEventListener('click', addTodo);
    $('#newTodoInput').addEventListener('keydown', (event) => {
      if (event.key === 'Enter') addTodo();
    });

    $('#notesTextarea').addEventListener('input', () => {
      setNotesStatus('Saving…', 'cloud-arrow-up');
      clearTimeout(state.notesSaveTimer);
      state.notesSaveTimer = setTimeout(saveNotes, 600);
    });
  }

  function renderPriorities(priorities) {
    const container = $('#prioritiesList');
    container.innerHTML = '';

    priorities.forEach((priority) => {
      const completed = Boolean(Number(priority.completed));
      const row = document.createElement('div');
      row.className = `priority-row${completed ? ' is-completed' : ''}`;
      row.innerHTML = `
        <button type="button" class="activity-check compact ${completed ? 'checked' : ''}" data-priority-toggle="${priority.position}" aria-label="${completed ? 'Mark priority incomplete' : 'Mark priority complete'}">
          ${completed ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : ''}
        </button>
        <span class="priority-num">${priority.position}</span>
        <input type="text" class="form-control" data-priority-position="${priority.position}" value="${escapeHtml(priority.text || '')}" placeholder="Priority ${priority.position}…" aria-label="Priority ${priority.position}">
      `;
      container.appendChild(row);
    });

    $$('[data-priority-position]', container).forEach((input) => {
      input.addEventListener('input', () => {
        clearTimeout(state.priorityTimers[input.dataset.priorityPosition]);
        state.priorityTimers[input.dataset.priorityPosition] = setTimeout(() => savePriority(input), 600);
      });
    });
    $$('[data-priority-toggle]', container).forEach((button) => {
      button.addEventListener('click', () => togglePriority(button.dataset.priorityToggle));
    });
  }

  async function savePriority(input) {
    const position = Number(input.dataset.priorityPosition);
    const row = input.closest('.priority-row');
    const completed = row.classList.contains('is-completed');

    try {
      await apiFetch(`${API_PLANNER}?resource=priorities`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date: isoDate(state.date), position, text: input.value, completed }),
      });
    } catch (error) {
      showToast('Could not save priority', true);
    }
  }

  async function togglePriority(position) {
    const input = $(`[data-priority-position="${position}"]`);
    if (!input) return;
    const row = input.closest('.priority-row');
    const completed = !row.classList.contains('is-completed');
    const button = row.querySelector('[data-priority-toggle]');

    row.classList.toggle('is-completed', completed);
    button.classList.toggle('checked', completed);
    button.innerHTML = completed ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : '';
    button.setAttribute('aria-label', completed ? 'Mark priority incomplete' : 'Mark priority complete');

    try {
      await apiFetch(`${API_PLANNER}?resource=priorities`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date: isoDate(state.date), position: Number(position), text: input.value, completed }),
      });
    } catch (error) {
      showToast('Could not update priority', true);
    }
  }

  function renderTodos(todos) {
    const container = $('#todosList');
    container.innerHTML = '';

    const openCount = todos.filter((todo) => !Number(todo.completed)).length;
    $('#todoCount').textContent = `${openCount} open`;

    if (!todos.length) {
      container.innerHTML = '<div class="week-empty">No tasks yet. Keep the list intentionally small.</div>';
      return;
    }

    todos.forEach((todo) => {
      const completed = Boolean(Number(todo.completed));
      const row = document.createElement('div');
      row.className = `todo-row${completed ? ' is-completed' : ''}`;
      row.innerHTML = `
        <button type="button" class="activity-check compact ${completed ? 'checked' : ''}" data-todo-toggle="${todo.id}" aria-label="${completed ? 'Mark task incomplete' : 'Mark task complete'}">
          ${completed ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : ''}
        </button>
        <span class="todo-text">${escapeHtml(todo.text)}</span>
        <button type="button" class="btn-remove-todo" data-todo-delete="${todo.id}" aria-label="Delete ${escapeHtml(todo.text)}"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
      `;
      container.appendChild(row);
    });

    $$('[data-todo-toggle]', container).forEach((button) => button.addEventListener('click', () => toggleTodo(button.dataset.todoToggle)));
    $$('[data-todo-delete]', container).forEach((button) => button.addEventListener('click', () => deleteTodo(button.dataset.todoDelete)));
  }

  async function addTodo() {
    const input = $('#newTodoInput');
    const text = input.value.trim();
    if (!text) return;

    input.value = '';
    try {
      const response = await apiFetch(`${API_PLANNER}?resource=todos`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date: isoDate(state.date), text }),
      });
      const json = await response.json();
      if (!json.success) throw new Error(json.error || 'Could not add task');
      await reloadTodos();
    } catch (error) {
      input.value = text;
      showToast(error.message, true);
    }
  }

  async function toggleTodo(id) {
    const button = $(`[data-todo-toggle="${id}"]`);
    const row = button?.closest('.todo-row');
    if (!row) return;
    const completed = !row.classList.contains('is-completed');

    try {
      const response = await apiFetch(`${API_PLANNER}?resource=todos&id=${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ completed }),
      });
      const json = await response.json();
      if (!json.success) throw new Error(json.error || 'Could not update task');
      await reloadTodos();
    } catch (error) {
      showToast(error.message, true);
    }
  }

  async function deleteTodo(id) {
    try {
      const response = await apiFetch(`${API_PLANNER}?resource=todos&id=${id}`, { method: 'DELETE' });
      const json = await response.json();
      if (!json.success) throw new Error(json.error || 'Could not delete task');
      await reloadTodos();
    } catch (error) {
      showToast(error.message, true);
    }
  }

  async function reloadTodos() {
    const response = await apiFetch(`${API_PLANNER}?resource=todos&date=${isoDate(state.date)}`);
    const json = await response.json();
    renderTodos(json.todos || []);
  }

  async function saveNotes() {
    try {
      const response = await apiFetch(`${API_PLANNER}?resource=notes`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ date: isoDate(state.date), content: $('#notesTextarea').value }),
      });
      const json = await response.json();
      if (!json.success) throw new Error(json.error || 'Could not save notes');
      setNotesStatus('Auto-saved', 'cloud-check');
    } catch (error) {
      setNotesStatus('Save failed', 'cloud-slash');
    }
  }

  function setNotesStatus(text, icon) {
    const label = $('.autosave-label');
    label.innerHTML = `<i class="bi bi-${icon}" aria-hidden="true"></i> ${escapeHtml(text)}`;
  }

  /* -------------------------------------------------------- reminders --- */

  function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
      document.addEventListener('click', function askOnce() {
        Notification.requestPermission();
      }, { once: true });
    }
  }

  function checkUpcomingReminders() {
    if (!('Notification' in window) || Notification.permission !== 'granted') return;
    if (isoDate(state.date) !== todayStr()) return;

    const now = new Date();
    const nowMinutes = now.getHours() * 60 + now.getMinutes();

    state.todayActivities.forEach((activity) => {
      if (activity.completed) return;
      const key = `${activity.id}|${todayStr()}`;
      if (state.notifiedIds.has(key)) return;

      const difference = timeToMinutes(activity.start_time) - nowMinutes;
      if (difference <= 5 && difference > 0) {
        new Notification(`Starting soon: ${activity.title}`, {
          body: `${formatClock(activity.start_time)}–${formatClock(activity.end_time)} · ${activity.category}`,
          tag: key,
        });
        state.notifiedIds.add(key);
      }
    });
  }

  /* --------------------------------------------------- change password --- */

  function bindChangePassword() {
    $('#btnOpenChangePassword').addEventListener('click', () => {
      $('#fieldCurrentPassword').value = '';
      $('#fieldNewPassword').value = '';
      $('#fieldNewPasswordConfirm').value = '';
      $('#changePasswordError').classList.add('d-none');
      bootstrap.Modal.getOrCreateInstance($('#changePasswordModal')).show();
    });

    $('#btnSaveNewPassword').addEventListener('click', async () => {
      const currentPassword = $('#fieldCurrentPassword').value;
      const newPassword = $('#fieldNewPassword').value;
      const confirmation = $('#fieldNewPasswordConfirm').value;
      const errorElement = $('#changePasswordError');

      if (newPassword.length < 8) {
        errorElement.textContent = 'New password must be at least 8 characters.';
        errorElement.classList.remove('d-none');
        return;
      }
      if (newPassword !== confirmation) {
        errorElement.textContent = 'New passwords do not match.';
        errorElement.classList.remove('d-none');
        return;
      }

      const button = $('#btnSaveNewPassword');
      button.disabled = true;
      try {
        const response = await apiFetch('api/auth.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'change_password', current_password: currentPassword, new_password: newPassword }),
        });
        const json = await response.json();
        if (!json.success) throw new Error(json.error || 'Failed to update password');
        bootstrap.Modal.getInstance($('#changePasswordModal')).hide();
        showToast('Password updated', false);
      } catch (error) {
        errorElement.textContent = error.message;
        errorElement.classList.remove('d-none');
      } finally {
        button.disabled = false;
      }
    });
  }

  /* ------------------------------------------------------------- helpers --- */

  function showToast(message, isError = false) {
    const container = $('#toastContainer');
    const element = document.createElement('div');
    element.className = `toast toast-planner ${isError ? 'is-error' : ''}`;
    element.setAttribute('role', 'status');
    element.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${escapeHtml(message)}</div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    `;
    container.appendChild(element);
    const toast = new bootstrap.Toast(element, { delay: 3000 });
    toast.show();
    element.addEventListener('hidden.bs.toast', () => element.remove());
  }

  function timeToMinutes(value) {
    if (!/^\d{2}:\d{2}$/.test(value || '')) return Number.NaN;
    const [hours, minutes] = value.split(':').map(Number);
    return hours * 60 + minutes;
  }

  function minutesToTime(totalMinutes) {
    const hours = Math.floor(totalMinutes / 60);
    const minutes = totalMinutes % 60;
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
  }

  function formatClock(value) {
    const minutes = timeToMinutes(value);
    if (!Number.isFinite(minutes)) return value || '';
    const date = new Date();
    date.setHours(Math.floor(minutes / 60), minutes % 60, 0, 0);
    return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });
  }

  function formatDuration(minutes) {
    if (!Number.isFinite(minutes) || minutes <= 0) return '0 min';
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    if (!hours) return `${remainder} min`;
    if (!remainder) return `${hours} ${pluralize('hour', hours)}`;
    return `${hours} hr ${remainder} min`;
  }

  function formatRemaining(minutes) {
    if (minutes <= 1) return '1 min';
    return formatDuration(minutes);
  }

  function formatRepeat(type) {
    return ({ daily: 'Every day', weekdays: 'Weekdays', weekends: 'Weekends' })[type] || '';
  }

  function normalizeColor(value) {
    return /^#[0-9a-f]{6}$/i.test(value || '') ? value : '#6B7280';
  }

  function colorToRgba(hex, alpha) {
    const clean = normalizeColor(hex).slice(1);
    const red = parseInt(clean.slice(0, 2), 16);
    const green = parseInt(clean.slice(2, 4), 16);
    const blue = parseInt(clean.slice(4, 6), 16);
    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
  }

  function pluralize(word, count) {
    return count === 1 ? word : `${word}s`;
  }

  function prefersReducedMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value).replace(/[&<>"']/g, (character) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[character]);
  }
})();
