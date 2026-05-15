/**
 * Agent-core — shared chat + plan + execute state machine for admin IDE
 * and front-end pencil. Vanilla JS, no dependencies.
 *
 * Usage:
 *   const agent = createAgentSession({ restBase, nonce });
 *   agent.addEventListener('message',     e => render(e.detail));
 *   agent.addEventListener('plan',        e => renderPlan(e.detail));
 *   agent.addEventListener('step-result', e => renderStep(e.detail));
 *   agent.addEventListener('done',        e => finish(e.detail));
 *   agent.send(userMessage, { open_files, current_page_slug });
 *   agent.approve(modifiedPlan); // execute the (possibly trimmed) plan
 */

const ACTION_VERBS = /\b(?:build|create|update|edit|fix|add|remove|change|delete|rename|refactor|generate|write|insert|replace)\b/i;

export function createAgentSession({ restBase, nonce }) {
    const bus = new EventTarget();
    let state = { plan: null, planId: null, busy: false };

    function emit(name, detail) {
        bus.dispatchEvent(new CustomEvent(name, { detail }));
    }

    async function send(message, context = {}) {
        if (state.busy) return;
        state.busy = true;
        emit('message', { role: 'user', text: message });

        // Action verbs → plan path. Otherwise → free-form chat.
        if (ACTION_VERBS.test(message)) {
            try {
                const r = await fetch(restBase + 'agent/plan', {
                    method: 'POST',
                    headers: jsonHeaders(),
                    body: JSON.stringify({ message, ...context }),
                });
                const data = await r.json();
                if (!r.ok) {
                    emit('message', { role: 'agent', text: 'Plan error: ' + (data.error || r.status) });
                } else {
                    state.plan = data.plan;
                    state.planId = data.plan_id;
                    emit('plan', { plan: data.plan, planId: data.plan_id });
                }
            } catch (err) {
                emit('message', { role: 'agent', text: 'Plan error: ' + err.message });
            }
        } else {
            // Free-form chat — reuse the existing /ai/chat endpoint.
            try {
                const r = await fetch(restBase + 'ai/chat', {
                    method: 'POST',
                    headers: jsonHeaders(),
                    body: JSON.stringify({ message }),
                });
                const data = await r.json();
                if (!r.ok) {
                    emit('message', { role: 'agent', text: 'Error: ' + (data.error || r.status) });
                } else {
                    emit('message', { role: 'agent', text: stripJsonFences(data.reply || '') });
                }
            } catch (err) {
                emit('message', { role: 'agent', text: 'Error: ' + err.message });
            }
        }
        state.busy = false;
    }

    async function approve(modifiedPlan) {
        if (state.busy || !state.planId) return;
        state.busy = true;
        try {
            const r = await fetch(restBase + 'agent/execute', {
                method: 'POST',
                headers: jsonHeaders(),
                body: JSON.stringify({ plan_id: state.planId, plan: modifiedPlan || state.plan }),
            });
            const data = await r.json();
            if (!r.ok) {
                emit('message', { role: 'agent', text: 'Execute error: ' + (data.error || r.status) });
            } else {
                (data.results || []).forEach(rs => emit('step-result', rs));
                emit('done', { halted: data.halted, halt_reason: data.halt_reason });
            }
        } catch (err) {
            emit('message', { role: 'agent', text: 'Execute error: ' + err.message });
        }
        state.busy = false;
        state.plan = null;
        state.planId = null;
    }

    function jsonHeaders() {
        return { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce };
    }

    function stripJsonFences(text) {
        return text.replace(/```json[\s\S]*?```/g, '').trim();
    }

    return Object.assign(bus, { send, approve });
}
