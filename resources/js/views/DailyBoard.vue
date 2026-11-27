<script setup>
import axios from 'axios';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const appTimezone = document.querySelector('meta[name="app-timezone"]')?.content || 'UTC';
const todayDate = computed(() => currentAppDate());
const selectedDate = computed(() => {
    const requestedDate = route.query.date;

    return typeof requestedDate === 'string'
        && isValidDate(requestedDate)
        && requestedDate <= todayDate.value
        ? requestedDate
        : todayDate.value;
});
const patients = ref([]);
const medicationDoses = ref([]);
const medicationOrders = ref([]);
const careTasks = ref([]);
const isLoading = ref(true);
const isSaving = ref(false);
const pageError = ref('');
const actionError = ref('');
const selectedTab = ref('all');
const selectedStatus = ref('all');
const activeItem = ref(null);
const actionStatus = ref('given');
const reason = ref('');
const nurseName = ref('');
const isReviewing = ref(false);
const showMedicationForm = ref(false);
const isMedicationReview = ref(false);
const isMedicationSaving = ref(false);
const medicationError = ref('');
const medicationDraft = ref(createMedicationDraft());
const toastMessage = ref('');
const showEmergencyNotice = ref(false);
const medicationDialog = ref(null);
const actionDialog = ref(null);
const emergencyDialog = ref(null);
let toastTimer;
let latestDailyRequest = 0;
let modalTrigger = null;

const daysOfWeek = [
    { value: 0, label: 'Sun' },
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
];
const defaultDoseTimes = ['08:00', '12:00', '16:00', '20:00'];

const selectedPatientId = computed(() => {
    const id = Number(route.query.patient);

    return Number.isInteger(id) && patients.value.some((patient) => patient.id === id)
        ? id
        : null;
});

const selectedPatient = computed(() =>
    patients.value.find((patient) => patient.id === selectedPatientId.value) || null,
);

const activePatient = computed(() =>
    patients.value.find((patient) => patient.id === activeItem.value?.patient_id) || null,
);

const items = computed(() => {
    const doses = medicationDoses.value.map((dose) => ({
        ...dose,
        kind: 'medication',
        key: `medication-${dose.medication_order_id}-${dose.scheduled_for}`,
        time: dose.scheduled_for,
    }));
    const tasks = careTasks.value.map((task) => ({
        ...task,
        kind: 'task',
        key: `task-${task.id}`,
        time: task.scheduled_for,
    }));

    return [...doses, ...tasks]
        .filter((item) => selectedTab.value === 'all' || item.kind === selectedTab.value)
        .filter((item) => selectedStatus.value === 'all' || item.status === selectedStatus.value)
        .sort((left, right) => new Date(left.time) - new Date(right.time));
});

const groups = computed(() => {
    const byTime = new Map();

    for (const item of items.value) {
        const time = formatTime(item.time);
        if (!byTime.has(time)) {
            byTime.set(time, []);
        }
        byTime.get(time).push(item);
    }

    return [...byTime.entries()].map(([time, groupedItems]) => ({ time, items: groupedItems }));
});

const counts = computed(() => {
    const allItems = [...medicationDoses.value, ...careTasks.value];

    return {
        overdue: allItems.filter((item) => item.status === 'overdue').length,
        due: allItems.filter((item) => item.status === 'due').length,
        recorded: allItems.filter((item) =>
            ['given', 'held', 'refused', 'done'].includes(item.status),
        ).length,
        open: allItems.filter((item) => ['due', 'overdue'].includes(item.status)).length,
    };
});

const tabCounts = computed(() => ({
    all: medicationDoses.value.length + careTasks.value.length,
    medication: medicationDoses.value.length,
    task: careTasks.value.length,
}));

const isToday = computed(() => selectedDate.value === todayDate.value);
const dateLabel = computed(() => {
    const selectedDay = new Date(`${selectedDate.value}T12:00:00Z`);

    return new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(selectedDay);
});

function formatTime(value) {
    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: appTimezone,
    }).format(new Date(value));
}

function formatDateTime(value) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: appTimezone,
    }).format(new Date(value));
}

function initials(name) {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

function errorMessage(error) {
    const errors = error.response?.data?.errors;
    if (errors) {
        return Object.values(errors).flat().join(' ');
    }

    return error.response?.data?.message || 'Something went wrong. Please try again.';
}

async function loadPatients() {
    const response = await axios.get('/api/patients');
    patients.value = response.data.data;
}

async function loadDailyItems() {
    const requestId = ++latestDailyRequest;
    isLoading.value = true;
    pageError.value = '';
    medicationDoses.value = [];
    medicationOrders.value = [];
    careTasks.value = [];

    const params = {
        date: selectedDate.value,
        ...(selectedPatientId.value ? { patient_id: selectedPatientId.value } : {}),
    };
    const ordersRequest = selectedPatientId.value
        ? axios.get(`/api/patients/${selectedPatientId.value}/orders`)
        : Promise.resolve({ data: { data: [] } });

    try {
        const [chartResponse, tasksResponse, ordersResponse] = await Promise.all([
            axios.get('/api/chart', { params }),
            axios.get('/api/tasks', { params }),
            ordersRequest,
        ]);
        if (requestId !== latestDailyRequest) {
            return;
        }

        medicationDoses.value = chartResponse.data.data;
        careTasks.value = tasksResponse.data.data;
        medicationOrders.value = ordersResponse.data.data;
    } catch (error) {
        if (requestId === latestDailyRequest) {
            pageError.value = errorMessage(error);
        }
    } finally {
        if (requestId === latestDailyRequest) {
            isLoading.value = false;
        }
    }
}

async function initialize() {
    isLoading.value = true;
    pageError.value = '';

    try {
        await loadPatients();
        await loadDailyItems();
    } catch (error) {
        pageError.value = errorMessage(error);
        isLoading.value = false;
    }
}

async function selectPatient(patientId) {
    await router.replace({
        query: {
            ...route.query,
            patient: patientId ? String(patientId) : undefined,
        },
    });
}

async function selectDate(date) {
    if (!isValidDate(date) || date > todayDate.value) {
        return;
    }

    await router.replace({
        query: {
            ...route.query,
            date: date === todayDate.value ? undefined : date,
        },
    });
}

function changeDateBy(days) {
    const date = new Date(`${selectedDate.value}T00:00:00Z`);
    date.setUTCDate(date.getUTCDate() + days);
    void selectDate(date.toISOString().slice(0, 10));
}

function isValidDate(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
    }

    const date = new Date(`${value}T00:00:00Z`);

    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
}

watch(() => [route.query.patient, route.query.date], () => {
    if (patients.value.length) {
        loadDailyItems();
    }
});

onMounted(() => {
    window.addEventListener('keydown', handleModalKeydown);
    initialize();
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleModalKeydown);
    window.clearTimeout(toastTimer);
});

function rememberModalTrigger() {
    modalTrigger = document.activeElement;
}

function focusDialog(dialog) {
    nextTick(() => dialog.value?.focus());
}

function restoreModalTrigger() {
    const trigger = modalTrigger;
    modalTrigger = null;
    nextTick(() => {
        if (trigger instanceof HTMLElement && trigger.isConnected) {
            trigger.focus();
        }
    });
}

function handleModalKeydown(event) {
    if (event.key === 'Escape') {
        if (showMedicationForm.value) {
            closeMedicationForm();
        } else if (activeItem.value) {
            closeAction();
        } else if (showEmergencyNotice.value) {
            closeEmergencyNotice();
        }
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const dialog = showMedicationForm.value
        ? medicationDialog.value
        : activeItem.value
            ? actionDialog.value
            : showEmergencyNotice.value
                ? emergencyDialog.value
                : null;
    if (!dialog) {
        return;
    }

    const focusableElements = [...dialog.querySelectorAll(
        'button:not(:disabled), input:not(:disabled):not([type="hidden"]), select:not(:disabled), textarea:not(:disabled), [href], [tabindex]:not([tabindex="-1"])',
    )];
    if (!focusableElements.length) {
        event.preventDefault();
        dialog.focus();
        return;
    }

    const first = focusableElements[0];
    const last = focusableElements[focusableElements.length - 1];
    const currentIndex = focusableElements.indexOf(document.activeElement);

    if (event.shiftKey && currentIndex <= 0) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && currentIndex === focusableElements.length - 1) {
        event.preventDefault();
        first.focus();
    } else if (!event.shiftKey && currentIndex < 0) {
        event.preventDefault();
        first.focus();
    }
}

function beginAction(item) {
    rememberModalTrigger();
    activeItem.value = item;
    actionError.value = '';
    reason.value = '';
    nurseName.value = '';
    isReviewing.value = false;
    actionStatus.value = 'given';
    focusDialog(actionDialog);
}

function closeAction() {
    if (isSaving.value) {
        return;
    }
    activeItem.value = null;
    actionError.value = '';
    restoreModalTrigger();
}

async function saveAction() {
    if (!activeItem.value || isSaving.value) {
        return;
    }

    if (!nurseName.value.trim()) {
        actionError.value = 'Enter your name before saving.';
        return;
    }
    if (activeItem.value.kind === 'medication'
        && ['held', 'refused'].includes(actionStatus.value)
        && !reason.value.trim()) {
        actionError.value = 'A reason is required when a dose is held or refused.';
        return;
    }

    isSaving.value = true;
    actionError.value = '';

    try {
        if (activeItem.value.kind === 'medication') {
            await axios.post(`/api/orders/${activeItem.value.medication_order_id}/administrations`, {
                scheduled_for: activeItem.value.scheduled_for,
                status: actionStatus.value,
                reason: reason.value.trim() || null,
                charted_by: nurseName.value.trim(),
            });
        } else {
            await axios.post(`/api/tasks/${activeItem.value.id}/complete`, {
                completed_by: nurseName.value.trim(),
            });
        }

        const wasMedication = activeItem.value.kind === 'medication';
        activeItem.value = null;
        showToast(wasMedication ? 'Dose charted.' : 'Task completed.');
        await loadDailyItems();
    } catch (error) {
        actionError.value = errorMessage(error);
        if (error.response?.status === 409) {
            await loadDailyItems();
        }
    } finally {
        isSaving.value = false;
    }
}

function showToast(message) {
    toastMessage.value = message;
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        toastMessage.value = '';
    }, 3500);
}

function createMedicationDraft() {
    return {
        drug_name: '',
        dose: '',
        route: 'Oral',
        start_date: currentAppDate(),
        end_date: '',
        days_of_week: [1, 2, 3, 4, 5],
        times: ['08:00'],
        added_by: '',
    };
}

function currentAppDate() {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: appTimezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const dateParts = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

    return `${dateParts.year}-${dateParts.month}-${dateParts.day}`;
}

function openMedicationForm() {
    rememberModalTrigger();
    medicationDraft.value = createMedicationDraft();
    medicationError.value = '';
    isMedicationReview.value = false;
    showMedicationForm.value = true;
    focusDialog(medicationDialog);
}

function closeMedicationForm() {
    if (isMedicationSaving.value) {
        return;
    }
    showMedicationForm.value = false;
    medicationError.value = '';
    restoreModalTrigger();
}

function openEmergencyNotice() {
    rememberModalTrigger();
    showEmergencyNotice.value = true;
    focusDialog(emergencyDialog);
}

function closeEmergencyNotice() {
    showEmergencyNotice.value = false;
    restoreModalTrigger();
}

function setMedicationFrequency(count) {
    const currentTimes = medicationDraft.value.times;
    medicationDraft.value.times = Array.from(
        { length: count },
        (_, index) => currentTimes[index] || defaultDoseTimes[index],
    );
}

function selectDays(days) {
    medicationDraft.value.days_of_week = [...days];
}

function reviewMedication() {
    const draft = medicationDraft.value;

    if (draft.end_date && draft.end_date < draft.start_date) {
        medicationError.value = 'The end date must be on or after the start date.';
        return;
    }
    if (draft.days_of_week.length === 0) {
        medicationError.value = 'Choose at least one day of the week.';
        return;
    }
    if (draft.times.some((time) => !time) || new Set(draft.times).size !== draft.times.length) {
        medicationError.value = 'Choose a different time for each daily dose.';
        return;
    }

    medicationError.value = '';
    isMedicationReview.value = true;
}

function formatPlanDays(days) {
    const sortedDays = [...days].sort((left, right) => left - right);

    if (sortedDays.length === 7) {
        return 'Every day';
    }
    if (sortedDays.join(',') === '1,2,3,4,5') {
        return 'Weekdays';
    }

    return sortedDays
        .map((day) => daysOfWeek.find((option) => option.value === day)?.label)
        .filter(Boolean)
        .join(', ');
}

function formatPlanDate(value) {
    if (!value) {
        return 'No end date';
    }

    const date = new Date(`${value}T12:00:00Z`);
    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(date);
}

async function saveMedication() {
    if (!selectedPatientId.value || isMedicationSaving.value) {
        return;
    }

    isMedicationSaving.value = true;
    medicationError.value = '';

    try {
        await axios.post(`/api/patients/${selectedPatientId.value}/orders`, {
            ...medicationDraft.value,
            end_date: medicationDraft.value.end_date || null,
            times: [...medicationDraft.value.times],
            days_of_week: [...medicationDraft.value.days_of_week],
        });
        showMedicationForm.value = false;
        showToast('Medication added.');
        await loadDailyItems();
    } catch (error) {
        medicationError.value = errorMessage(error);
    } finally {
        isMedicationSaving.value = false;
    }
}
</script>

<template>
    <div class="app-shell">
        <div class="demo-banner">
            Training prototype with made-up data. Not for real patient care.
        </div>

        <header class="topbar">
            <a class="brand" href="/" aria-label="Demo Care Home daily board">
                <span class="brand-mark" aria-hidden="true">+</span>
                <span>
                    <small>Facility</small>
                    <strong>Demo Care Home</strong>
                </span>
            </a>
            <button class="emergency-button" type="button" @click="openEmergencyNotice">
                Emergency
            </button>
        </header>

        <div class="workspace">
            <aside class="resident-sidebar" aria-label="Residents">
                <div class="sidebar-title">
                    <h2>Residents</h2>
                    <span class="count-badge">{{ patients.length }}</span>
                </div>
                <button
                    class="resident-card"
                    :class="{ active: selectedPatientId === null }"
                    type="button"
                    @click="selectPatient(null)"
                >
                    <span class="avatar avatar-all">{{ patients.length }}</span>
                    <span class="resident-copy">
                        <strong>All residents</strong>
                    </span>
                </button>
                <button
                    v-for="patient in patients"
                    :key="patient.id"
                    class="resident-card"
                    :class="{ active: selectedPatientId === patient.id }"
                    type="button"
                    @click="selectPatient(patient.id)"
                >
                    <span class="avatar">{{ initials(patient.name) }}</span>
                    <span class="resident-copy">
                        <strong>{{ patient.name }}</strong>
                        <small>Room {{ patient.room }}</small>
                    </span>
                </button>
            </aside>

            <main class="daily-board">
                <div class="mobile-resident-picker">
                    <label class="sr-only" for="resident-select">Choose resident</label>
                    <select
                        id="resident-select"
                        :value="selectedPatientId || ''"
                        @change="selectPatient($event.target.value ? Number($event.target.value) : null)"
                    >
                        <option value="">All residents</option>
                        <option v-for="patient in patients" :key="patient.id" :value="patient.id">
                            {{ patient.name }} · Room {{ patient.room }}
                        </option>
                    </select>
                </div>

                <div class="board-heading">
                    <div class="title-row">
                        <h1>To-do list</h1>
                        <span class="open-badge">{{ counts.open }} open</span>
                        <button
                            v-if="selectedPatient"
                            class="action-button add-medication-button"
                            type="button"
                            @click="openMedicationForm"
                        >
                            + Add medication
                        </button>
                    </div>
                    <div class="heading-controls">
                        <div class="tabs" role="group" aria-label="Filter by item type">
                            <button
                                v-for="tab in [
                                    { id: 'all', label: 'All' },
                                    { id: 'medication', label: 'Medications' },
                                    { id: 'task', label: 'Tasks' },
                                ]"
                                :key="tab.id"
                                type="button"
                                :aria-pressed="selectedTab === tab.id"
                                :class="{ active: selectedTab === tab.id }"
                                @click="selectedTab = tab.id"
                            >
                                {{ tab.label }} <span>{{ tabCounts[tab.id] }}</span>
                            </button>
                        </div>
                        <label class="status-control">
                            <span class="sr-only">Filter by status</span>
                            <select v-model="selectedStatus">
                                <option value="all">All statuses</option>
                                <option value="due">Due</option>
                                <option value="overdue">Overdue</option>
                                <option value="given">Given</option>
                                <option value="held">Held</option>
                                <option value="refused">Refused</option>
                                <option value="done">Done</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div v-if="selectedPatient" class="allergy-notice">
                    <strong>{{ selectedPatient.name }}:</strong>
                    Allergies: {{ selectedPatient.allergies || 'none recorded' }}
                </div>

                <section v-if="selectedPatient" class="medication-plan" aria-labelledby="plan-title">
                    <div class="plan-heading">
                        <h2 id="plan-title">Medication plan</h2>
                        <span class="count-badge">{{ medicationOrders.length }}</span>
                    </div>
                    <div v-if="medicationOrders.length" class="plan-list">
                        <article v-for="order in medicationOrders" :key="order.id" class="plan-card">
                            <span class="plan-icon" aria-hidden="true">⌁</span>
                            <div class="plan-copy">
                                <h3>{{ order.drug_name }}</h3>
                                <p>{{ order.dose }}, {{ order.route }}</p>
                                <div class="plan-meta">
                                    <span>{{ formatPlanDays(order.days_of_week) }}</span>
                                    <span>{{ order.times.length }} time{{ order.times.length === 1 ? '' : 's' }} daily: {{ order.times.join(', ') }}</span>
                                    <span>
                                        {{ formatPlanDate(order.start_date) }} –
                                        {{ order.end_date ? formatPlanDate(order.end_date) : 'no end date' }}
                                    </span>
                                </div>
                            </div>
                        </article>
                    </div>
                    <p v-else class="plan-empty">No medication orders for this resident yet.</p>
                </section>

                <section class="summary" aria-label="Daily summary">
                    <div class="summary-card overdue-summary">
                        <strong>{{ counts.overdue }}</strong>
                        <span>Overdue</span>
                    </div>
                    <div class="summary-card due-summary">
                        <strong>{{ counts.due }}</strong>
                        <span>Due</span>
                    </div>
                    <div class="summary-card recorded-summary">
                        <strong>{{ counts.recorded }}</strong>
                        <span>Recorded</span>
                    </div>
                </section>

                <div class="date-navigation" aria-label="Chart date">
                    <button class="secondary-button" type="button" @click="changeDateBy(-1)">
                        Previous day
                    </button>
                    <label class="sr-only" for="chart-date">Choose chart date</label>
                    <input
                        id="chart-date"
                        type="date"
                        :max="todayDate"
                        :value="selectedDate"
                        @change="selectDate($event.target.value)"
                    >
                    <button
                        class="secondary-button"
                        type="button"
                        :disabled="isToday"
                        @click="changeDateBy(1)"
                    >
                        Next day
                    </button>
                </div>
                <p class="sample-day">{{ isToday ? 'Today' : 'Viewing' }}: {{ dateLabel }}</p>

                <div v-if="isLoading" class="state-message" role="status">
                    Loading today’s list…
                </div>
                <div v-else-if="pageError" class="state-message error-message" role="alert">
                    <strong>Couldn’t load today’s list.</strong>
                    <span>{{ pageError }}</span>
                    <button class="secondary-button" type="button" @click="initialize">Try again</button>
                </div>
                <div v-else-if="!items.length" class="state-message empty-message">
                    <strong>Nothing to show</strong>
                    <span>
                        {{ selectedStatus === 'all'
                            ? 'There are no scheduled items for this selection today.'
                            : `There are no ${selectedStatus} items for this selection.` }}
                    </span>
                </div>
                <div v-else class="timeline">
                    <section v-for="group in groups" :key="group.time" class="time-group">
                        <h2 class="time-heading">{{ group.time }}</h2>
                        <article
                            v-for="item in group.items"
                            :key="item.key"
                            class="item-card"
                            :class="[item.kind, `status-${item.status}`]"
                        >
                            <div class="item-icon" aria-hidden="true">
                                {{ item.kind === 'medication' ? '⌁' : '✓' }}
                            </div>
                            <div class="item-copy">
                                <h3>{{ item.kind === 'medication' ? item.drug_name : item.title }}</h3>
                                <p v-if="item.kind === 'medication'">
                                    {{ item.dose }}, {{ item.route }}
                                </p>
                                <p>{{ item.patient_name }}, Room {{ item.room }}</p>
                                <p v-if="item.status === 'held' || item.status === 'refused'" class="item-reason">
                                    {{ item.status === 'held' ? 'Held' : 'Refused' }}:
                                    {{ item.reason || 'Reason not supplied' }}
                                </p>
                            </div>
                            <span class="status-pill" :class="`pill-${item.status}`">
                                {{ item.status }}
                            </span>
                            <button
                                v-if="['due', 'overdue'].includes(item.status)"
                                class="action-button"
                                type="button"
                                @click="beginAction(item)"
                            >
                                {{ item.kind === 'medication' ? 'Chart' : 'Complete' }}
                            </button>
                        </article>
                    </section>
                </div>
            </main>
        </div>

        <div v-if="showMedicationForm" class="modal-backdrop" @click.self="closeMedicationForm">
            <section
                ref="medicationDialog"
                class="action-modal medication-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="medication-title"
                tabindex="-1"
            >
                <button class="modal-close" type="button" aria-label="Close" @click="closeMedicationForm">×</button>
                <template v-if="!isMedicationReview">
                    <h2 id="medication-title">Add medication</h2>
                    <p class="modal-detail">Resident: {{ selectedPatient?.name }}, Room {{ selectedPatient?.room }}</p>
                    <div class="allergy-notice modal-allergy">
                        <strong>Allergies:</strong> {{ selectedPatient?.allergies || 'none recorded' }}
                    </div>

                    <form class="medication-form" @submit.prevent="reviewMedication">
                        <label class="field-label">
                            Medication name
                            <input v-model.trim="medicationDraft.drug_name" type="text" maxlength="100" required>
                        </label>
                        <div class="form-columns">
                            <label class="field-label">
                                Dose
                                <input v-model.trim="medicationDraft.dose" type="text" maxlength="50" required>
                            </label>
                            <label class="field-label">
                                Route
                                <select v-model="medicationDraft.route" required>
                                    <option>Oral</option>
                                    <option>Topical</option>
                                    <option>Inhaled</option>
                                    <option>Eye drops</option>
                                    <option>Injection</option>
                                </select>
                            </label>
                        </div>
                        <div class="form-columns">
                            <label class="field-label">
                                Start date
                                <input v-model="medicationDraft.start_date" type="date" required>
                            </label>
                            <label class="field-label">
                                End date <span>(optional)</span>
                                <input
                                    v-model="medicationDraft.end_date"
                                    type="date"
                                    :min="medicationDraft.start_date"
                                >
                            </label>
                        </div>

                        <fieldset class="day-picker">
                            <legend>Days</legend>
                            <div class="day-shortcuts">
                                <button type="button" @click="selectDays([0, 1, 2, 3, 4, 5, 6])">
                                    Every day
                                </button>
                                <button type="button" @click="selectDays([1, 2, 3, 4, 5])">
                                    Weekdays
                                </button>
                            </div>
                            <div class="day-options">
                                <label v-for="day in daysOfWeek" :key="day.value">
                                    <input
                                        v-model="medicationDraft.days_of_week"
                                        type="checkbox"
                                        :value="day.value"
                                    >
                                    <span>{{ day.label }}</span>
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="frequency-picker">
                            <legend>How many times a day?</legend>
                            <div class="frequency-options">
                                <button
                                    v-for="count in [1, 2, 3, 4]"
                                    :key="count"
                                    type="button"
                                    :aria-pressed="medicationDraft.times.length === count"
                                    :class="{ active: medicationDraft.times.length === count }"
                                    @click="setMedicationFrequency(count)"
                                >
                                    {{ count }}×
                                </button>
                            </div>
                        </fieldset>
                        <div class="time-fields">
                            <label v-for="(_, index) in medicationDraft.times" :key="index" class="field-label">
                                Dose {{ index + 1 }}
                                <input v-model="medicationDraft.times[index]" type="time" required>
                            </label>
                        </div>

                        <label class="field-label">
                            Added by
                            <input v-model.trim="medicationDraft.added_by" type="text" maxlength="100" autocomplete="name" required>
                        </label>

                        <p v-if="medicationError" class="inline-error" role="alert">{{ medicationError }}</p>
                        <div class="modal-actions">
                            <button class="secondary-button" type="button" @click="closeMedicationForm">Cancel</button>
                            <button class="action-button" type="submit">Review</button>
                        </div>
                    </form>
                </template>
                <template v-else>
                    <h2 id="medication-title">Review medication</h2>
                    <p class="modal-item-title">{{ medicationDraft.drug_name }}</p>
                    <p class="modal-detail">
                        {{ medicationDraft.dose }}, {{ medicationDraft.route }}
                    </p>
                    <p class="modal-detail">
                        {{ formatPlanDays(medicationDraft.days_of_week) }} ·
                        {{ medicationDraft.times.length }} time{{ medicationDraft.times.length === 1 ? '' : 's' }} daily
                        ({{ medicationDraft.times.join(', ') }})
                    </p>
                    <p class="modal-detail">
                        {{ formatPlanDate(medicationDraft.start_date) }} –
                        {{ medicationDraft.end_date ? formatPlanDate(medicationDraft.end_date) : 'no end date' }}
                    </p>
                    <p class="modal-detail">Resident: {{ selectedPatient?.name }}, Room {{ selectedPatient?.room }}</p>
                    <p class="modal-detail">Added by: {{ medicationDraft.added_by }}</p>
                    <div class="allergy-notice modal-allergy">
                        <strong>Allergies:</strong> {{ selectedPatient?.allergies || 'none recorded' }}
                    </div>
                    <p v-if="medicationError" class="inline-error" role="alert">{{ medicationError }}</p>
                    <div class="modal-actions">
                        <button
                            class="secondary-button"
                            type="button"
                            :disabled="isMedicationSaving"
                            @click="isMedicationReview = false"
                        >
                            Back
                        </button>
                        <button
                            class="action-button"
                            type="button"
                            :disabled="isMedicationSaving"
                            @click="saveMedication"
                        >
                            {{ isMedicationSaving ? 'Saving…' : 'Confirm and save' }}
                        </button>
                    </div>
                </template>
            </section>
        </div>

        <div v-if="activeItem" class="modal-backdrop" @click.self="closeAction">
            <section
                ref="actionDialog"
                class="action-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="action-title"
                tabindex="-1"
            >
                <button class="modal-close" type="button" aria-label="Close" @click="closeAction">×</button>
                <template v-if="!isReviewing">
                    <h2 id="action-title">
                        {{ activeItem.kind === 'medication' ? 'Chart dose' : 'Complete task' }}
                    </h2>
                    <p class="modal-item-title">
                        {{ activeItem.kind === 'medication' ? activeItem.drug_name : activeItem.title }}
                    </p>
                    <p v-if="activeItem.kind === 'medication'" class="modal-detail">
                        {{ activeItem.dose }}, {{ activeItem.route }}, {{ formatTime(activeItem.scheduled_for) }}
                    </p>
                    <p class="modal-detail">
                        {{ activeItem.patient_name }}, Room {{ activeItem.room }}
                    </p>
                    <div v-if="activePatient" class="allergy-notice modal-allergy">
                        <strong>Allergies:</strong> {{ activePatient.allergies || 'none recorded' }}
                    </div>

                    <fieldset v-if="activeItem.kind === 'medication'" class="status-options">
                        <legend>Dose status</legend>
                        <label v-for="option in ['given', 'held', 'refused']" :key="option">
                            <input v-model="actionStatus" type="radio" name="dose-status" :value="option">
                            <span>{{ option }}</span>
                        </label>
                    </fieldset>

                    <label v-if="activeItem.kind === 'medication' && actionStatus !== 'given'" class="field-label">
                        Reason <span>(required)</span>
                        <textarea v-model="reason" maxlength="255" rows="2"></textarea>
                    </label>
                    <label class="field-label">
                        Your name
                        <input v-model="nurseName" type="text" maxlength="100" autocomplete="name">
                    </label>
                    <p v-if="actionError" class="inline-error" role="alert">{{ actionError }}</p>
                    <div class="modal-actions">
                        <button class="secondary-button" type="button" @click="closeAction">Cancel</button>
                        <button class="action-button" type="button" @click="isReviewing = true">
                            Review
                        </button>
                    </div>
                </template>
                <template v-else>
                    <h2 id="action-title">Confirm {{ activeItem.kind === 'medication' ? 'dose' : 'task' }}</h2>
                    <p class="modal-item-title">
                        {{ activeItem.kind === 'medication' ? activeItem.drug_name : activeItem.title }}
                    </p>
                    <p class="modal-detail">
                        {{ activeItem.patient_name }}, Room {{ activeItem.room }}
                    </p>
                    <p class="modal-detail">Scheduled: {{ formatDateTime(activeItem.scheduled_for) }}</p>
                    <p v-if="activeItem.kind === 'medication'" class="modal-detail">
                        {{ activeItem.dose }}, {{ activeItem.route }} · {{ actionStatus }}
                    </p>
                    <p v-if="reason.trim()" class="modal-detail">Reason: {{ reason.trim() }}</p>
                    <p class="modal-detail">
                        {{ activeItem.kind === 'medication' ? 'Charted by' : 'Completed by' }}:
                        {{ nurseName.trim() }}
                    </p>
                    <p v-if="actionError" class="inline-error" role="alert">{{ actionError }}</p>
                    <div class="modal-actions">
                        <button class="secondary-button" type="button" :disabled="isSaving" @click="isReviewing = false">
                            Back
                        </button>
                        <button class="action-button" type="button" :disabled="isSaving" @click="saveAction">
                            {{ isSaving ? 'Saving…' : 'Confirm and save' }}
                        </button>
                    </div>
                </template>
            </section>
        </div>

        <div v-if="showEmergencyNotice" class="modal-backdrop" @click.self="closeEmergencyNotice">
            <section
                ref="emergencyDialog"
                class="action-modal emergency-modal"
                role="dialog"
                aria-modal="true"
                aria-labelledby="emergency-title"
                tabindex="-1"
            >
                <h2 id="emergency-title">Emergency demo</h2>
                <p>No alert was sent. This button contacts no one.</p>
                <div class="modal-actions">
                    <button class="action-button" type="button" @click="closeEmergencyNotice">Close</button>
                </div>
            </section>
        </div>

        <div v-if="toastMessage" class="save-toast" role="status">{{ toastMessage }}</div>
    </div>
</template>
