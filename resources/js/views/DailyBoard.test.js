import { createApp, nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import axios from 'axios';
import DailyBoard from './DailyBoard.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

const patient = {
    id: 1,
    name: 'Demo Resident',
    room: '101',
    allergies: 'Latex',
};

const order = {
    id: 20,
    drug_name: 'Fictional medicine',
    dose: '1 tablet',
    route: 'Oral',
    start_date: '2026-11-20',
    end_date: null,
    days_of_week: [1, 2, 3, 4, 5],
    times: ['08:00', '20:00'],
};

let wrapper;
let orders;

async function flushPromises() {
    await Promise.resolve();
    await nextTick();
    await new Promise((resolve) => setTimeout(resolve, 0));
    await nextTick();
}

function clickButton(text, scope = wrapper) {
    const button = [...scope.querySelectorAll('button')]
        .find((candidate) => candidate.textContent.trim() === text);

    if (!button) {
        throw new Error(`Button not found: ${text}`);
    }

    button.click();
}

function setValue(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

async function mountBoard() {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: [{ path: '/', component: DailyBoard }],
    });
    await router.push('/?patient=1');
    await router.isReady();

    wrapper = document.createElement('div');
    document.body.appendChild(wrapper);
    const app = createApp(DailyBoard);
    app.use(router);
    app.mount(wrapper);
    wrapper.__vueApp = app;
    await flushPromises();

    return wrapper;
}

describe('DailyBoard', () => {
    beforeEach(() => {
        orders = [order];
        axios.get.mockImplementation((url) => {
            if (url === '/api/patients') {
                return Promise.resolve({ data: { data: [patient] } });
            }
            if (url === '/api/patients/1/orders') {
                return Promise.resolve({ data: { data: orders } });
            }
            if (url === '/api/chart') {
                return Promise.resolve({
                    data: {
                        data: [{
                            medication_order_id: 20,
                            patient_id: 1,
                            patient_name: patient.name,
                            room: patient.room,
                            drug_name: order.drug_name,
                            dose: order.dose,
                            route: order.route,
                            scheduled_for: '2026-11-27T08:00:00+00:00',
                            status: 'overdue',
                        }],
                    },
                });
            }
            if (url === '/api/tasks') {
                return Promise.resolve({
                    data: {
                        data: [{
                            id: 30,
                            patient_id: 1,
                            patient_name: patient.name,
                            room: patient.room,
                            title: 'Walk outside',
                            scheduled_for: '2026-11-27T09:00:00+00:00',
                            status: 'done',
                            completed_by: 'Demo Nurse',
                            completed_at: '2026-11-27T09:15:00+00:00',
                        }],
                    },
                });
            }

            throw new Error(`Unexpected API request: ${url}`);
        });
        axios.post.mockResolvedValue({ data: {} });
    });

    afterEach(() => {
        wrapper?.__vueApp?.unmount();
        wrapper?.remove();
        wrapper = undefined;
    });

    it('loads the resident plan and keeps summary counts independent of list filters', async () => {
        const board = await mountBoard();

        expect(board.textContent).toContain('Demo Resident:');
        expect(board.textContent).toContain('Allergies: Latex');
        expect(board.textContent).toContain('Fictional medicine');
        const summary = board.querySelector('[aria-label="Daily summary"]');
        expect(summary.textContent).toContain('1Overdue');
        expect(summary.textContent).toContain('0Due');
        expect(summary.textContent).toContain('1Recorded');

        setValue(board.querySelector('.status-control select'), 'overdue');
        await flushPromises();

        expect(board.textContent).toContain('Fictional medicine');
        expect(board.textContent).not.toContain('Walk outside');
        expect(summary.textContent).toContain('1Overdue');
        expect(summary.textContent).toContain('0Due');
        expect(summary.textContent).toContain('1Recorded');
    });

    it('reviews and saves a medication order with its selected schedule', async () => {
        orders = [];
        const board = await mountBoard();
        axios.post.mockImplementation(async (_url, payload) => {
            orders = [{
                ...order,
                drug_name: payload.drug_name,
                dose: payload.dose,
                days_of_week: payload.days_of_week,
                times: payload.times,
            }];

            return { data: { data: orders[0] } };
        });

        clickButton('+ Add medication', board);
        await flushPromises();
        const textInputs = board.querySelectorAll('.medication-form input[type="text"]');
        setValue(textInputs[0], 'Fictional new medicine');
        setValue(textInputs[1], '2 tablets');
        setValue(textInputs[2], 'Demo Nurse');
        clickButton('Weekdays', board.querySelector('.day-shortcuts'));
        clickButton('2×', board.querySelector('.frequency-options'));
        await flushPromises();
        setValue(board.querySelectorAll('.medication-form input[type="time"]')[1], '20:00');
        board.querySelector('.medication-form')
            .dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await flushPromises();

        expect(board.textContent).toContain('Review medication');
        expect(board.textContent).toContain('Weekdays · 2 times daily (08:00, 20:00)');
        expect(board.textContent).toContain('Allergies: Latex');

        clickButton('Confirm and save', board.querySelector('.medication-modal'));
        await flushPromises();

        expect(axios.post).toHaveBeenCalledWith('/api/patients/1/orders', expect.objectContaining({
            drug_name: 'Fictional new medicine',
            dose: '2 tablets',
            route: 'Oral',
            days_of_week: [1, 2, 3, 4, 5],
            times: ['08:00', '20:00'],
            added_by: 'Demo Nurse',
        }));
        expect(board.textContent).toContain('Medication added.');
        expect(board.textContent).toContain('Fictional new medicine');
    });

    it('blocks duplicate dose times before opening the review step', async () => {
        const board = await mountBoard();

        clickButton('+ Add medication', board);
        await flushPromises();
        const textInputs = board.querySelectorAll('.medication-form input[type="text"]');
        setValue(textInputs[0], 'Fictional new medicine');
        setValue(textInputs[1], '1 tablet');
        setValue(textInputs[2], 'Demo Nurse');
        clickButton('2×', board.querySelector('.frequency-options'));
        await flushPromises();
        setValue(board.querySelectorAll('.medication-form input[type="time"]')[1], '08:00');
        board.querySelector('.medication-form')
            .dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await flushPromises();

        expect(board.textContent).toContain('Choose a different time for each daily dose.');
        expect(board.textContent).not.toContain('Review medication');
        expect(axios.post).not.toHaveBeenCalled();
    });
});
