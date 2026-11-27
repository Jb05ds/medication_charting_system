<script setup>
import axios from 'axios';
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const route = useRoute();
const router = useRouter();
const appTimezone = document.querySelector('meta[name="app-timezone"]')?.content || 'UTC';
const patients = ref([]);
const medicationDoses = ref([]);
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
const toastMessage = ref('');
const showEmergencyNotice = ref(false);
let toastTimer;

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

const dateLabel = computed(() =>
    new Intl.DateTimeFormat(undefined, {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        timeZone: appTimezone,
    }).format(new Date()),
);

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
    isLoading.value = true;
    pageError.value = '';

    const params = selectedPatientId.value
        ? { patient_id: selectedPatientId.value }
        : {};

    try {
        const [chartResponse, tasksResponse] = await Promise.all([
            axios.get('/api/chart', { params }),
            axios.get('/api/tasks', { params }),
        ]);
        medicationDoses.value = chartResponse.data.data;
        careTasks.value = tasksResponse.data.data;
    } catch (error) {
        pageError.value = errorMessage(error);
    } finally {
        isLoading.value = false;
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

watch(() => route.query.patient, () => {
    if (patients.value.length) {
        loadDailyItems();
    }
});

onMounted(initialize);

function beginAction(item) {
    activeItem.value = item;
    actionError.value = '';
    reason.value = '';
    nurseName.value = '';
    isReviewing.value = false;
    actionStatus.value = 'given';
}

function closeAction() {
    if (isSaving.value) {
        return;
    }
    activeItem.value = null;
    actionError.value = '';
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
            <button class="emergency-button" type="button" @click="showEmergencyNotice = true">
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
                    </div>
                    <div class="heading-controls">
                        <div class="tabs" role="tablist" aria-label="To-do categories">
                            <button
                                v-for="tab in [
                                    { id: 'all', label: 'All' },
                                    { id: 'medication', label: 'Medications' },
                                    { id: 'task', label: 'Tasks' },
                                ]"
                                :key="tab.id"
                                type="button"
                                role="tab"
                                :aria-selected="selectedTab === tab.id"
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

                <p class="sample-day">Today: {{ dateLabel }}</p>

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

        <div v-if="activeItem" class="modal-backdrop" @click.self="closeAction">
            <section class="action-modal" role="dialog" aria-modal="true" aria-labelledby="action-title">
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

        <div v-if="showEmergencyNotice" class="modal-backdrop" @click.self="showEmergencyNotice = false">
            <section class="action-modal emergency-modal" role="dialog" aria-modal="true" aria-labelledby="emergency-title">
                <h2 id="emergency-title">Emergency demo</h2>
                <p>No alert was sent. This button contacts no one.</p>
                <div class="modal-actions">
                    <button class="action-button" type="button" @click="showEmergencyNotice = false">Close</button>
                </div>
            </section>
        </div>

        <div v-if="toastMessage" class="save-toast" role="status">{{ toastMessage }}</div>
    </div>
</template>
