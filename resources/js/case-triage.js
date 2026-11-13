import { applyCaseActivity, refreshCaseActivity } from './case-activity';
import { partnersSummaryHtml } from './partners-summary';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function postJson(url, body = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok || !payload?.ok) {
        throw new Error(payload?.message || 'Request failed.');
    }

    return payload;
}

async function postForm(form) {
    const response = await fetch(form.action, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: new FormData(form),
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok || !payload?.ok) {
        throw new Error(payload?.message || 'Request failed.');
    }

    return payload;
}

function storageKey(ticketId) {
    return `plantgrid.caseTriage.${ticketId}`;
}

function readStore(ticketId) {
    try {
        const raw = sessionStorage.getItem(storageKey(ticketId));
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function writeStore(ticketId, data) {
    try {
        sessionStorage.setItem(storageKey(ticketId), JSON.stringify(data));
    } catch {
        // Ignore quota / private mode failures.
    }
}

function personCard(person, { name = 'owner_id', checked = false, type = 'radio' } = {}) {
    if (!person) {
        return '';
    }

    const availability = person.online
        ? '<span class="pg-triage-pill is-online">Online</span>'
        : '<span class="pg-triage-pill">Offline</span>';

    return `
        <label class="pg-triage-person">
            <input type="${type}" name="${name}" value="${escapeHtml(person.id)}" ${checked ? 'checked' : ''}>
            <span class="pg-triage-person-body">
                <span class="pg-triage-person-top">
                    <strong>${escapeHtml(person.name)}</strong>
                    ${availability}
                </span>
                <span class="pg-triage-person-meta">
                    ${escapeHtml(person.role || '')}${person.department ? ` · ${escapeHtml(person.department)}` : ''}
                    ${person.open_load != null ? ` · ${escapeHtml(person.open_load)} open` : ''}
                </span>
                <span class="pg-triage-person-reason">${escapeHtml(person.reason || (person.reasons || []).slice(0, 2).join(' · '))}</span>
            </span>
        </label>
    `;
}

function noteArticleHtml(comment) {
    const internalBadge = comment.is_internal
        ? '<span class="pg-note-visibility-badge" data-note-internal-badge>Internal</span>'
        : '';
    const role = comment.author_role
        ? `<span class="pg-note-role" data-note-role><i data-lucide="user-round" aria-hidden="true"></i>${escapeHtml(comment.author_role)}</span>`
        : '';
    const tone = comment.is_internal ? 'is-internal' : 'is-public';

    return `
        <article
            class="pg-note ${tone}"
            data-note-id="${escapeHtml(comment.id)}"
            data-note-body="${escapeHtml(JSON.stringify(comment.comment))}"
            data-note-visibility="${escapeHtml(comment.visibility)}"
            data-note-update="${escapeHtml(comment.update_url)}"
            data-note-delete-url="${escapeHtml(comment.delete_url || '')}"
            data-can-edit="${comment.can_edit ? '1' : '0'}"
            data-can-delete="${comment.can_delete ? '1' : '0'}"
        >
            <header class="pg-note-head">
                <span class="pg-note-avatar" aria-hidden="true">${escapeHtml(comment.author_initials || 'U')}</span>
                <div class="pg-note-meta">
                    <div class="pg-note-meta-top">
                        <strong data-note-author>${escapeHtml(comment.author)}</strong>
                        ${role}
                        ${internalBadge}
                    </div>
                    <div class="pg-note-time-row">
                        <time class="pg-note-time" data-note-time>${escapeHtml(comment.created_at)}</time>
                    </div>
                </div>
            </header>
            <div class="pg-rich-note" data-note-html>${comment.html}</div>
            <div class="pg-note-editor" data-note-editor hidden></div>
        </article>
    `;
}

function updateCaseOwner(owner, assignedTo = null) {
    const ownerNode = document.querySelector('[data-case-owner]');
    if (ownerNode) {
        if (owner?.url && owner?.name) {
            ownerNode.innerHTML = `<a href="${escapeHtml(owner.url)}">${escapeHtml(owner.name)}</a>`;
        } else {
            ownerNode.textContent = owner?.name || 'Unassigned';
        }
    }

    const assigned = document.getElementById('assigned_to');
    const id = assignedTo ?? owner?.id;
    if (assigned && id) {
        assigned.value = String(id);
    }
}

function partnerRemoveUrl(list, partnerId) {
    const template = list?.dataset.partnerRemoveUrlTemplate || '';
    return template.replace('__PARTNER__', String(partnerId));
}

function updateCasePartners(partners = []) {
    const summary = document.querySelector('[data-case-partners]');
    if (summary) {
        summary.innerHTML = partnersSummaryHtml(partners);
        summary.classList.add('pg-partners-summary');
        summary.classList.remove('is-open');
    }

    const list = document.querySelector('[data-case-partner-list]');
    if (list) {
        const canRemove = list.dataset.canRemovePartners === '1';
        const ownerId = list.dataset.caseOwnerId || '';
        list.innerHTML = partners.length
            ? partners.map((partner) => {
                const isOwner = ownerId && String(partner.id) === String(ownerId);
                const removeBtn = canRemove && !isOwner
                    ? `<button type="button" data-remove-partner data-partner-id="${escapeHtml(partner.id)}" data-partner-name="${escapeHtml(partner.name)}" aria-label="Remove ${escapeHtml(partner.name)}" title="Remove">×</button>`
                    : '';
                const ownerTag = isOwner ? '<em>owner</em>' : '';
                return `
                <li data-partner-id="${escapeHtml(partner.id)}">
                    <span class="pg-partner-chip ${isOwner ? 'is-owner' : ''}" title="${escapeHtml(partner.name)} · ${escapeHtml(partner.source || 'manual')}">
                        <a href="${escapeHtml(partner.url || '#')}">${escapeHtml(partner.name)}</a>
                        ${ownerTag}
                        ${removeBtn}
                    </span>
                </li>`;
            }).join('')
            : '<li class="text-muted pg-partner-empty" data-partners-empty>No partners yet.</li>';
    }

    const select = document.getElementById('partner_user_id');
    if (select) {
        const taken = new Set(partners.map((partner) => String(partner.id)));
        [...select.options].forEach((option) => {
            if (!option.value) {
                return;
            }
            option.hidden = taken.has(option.value);
            if (taken.has(option.value) && select.value === option.value) {
                select.value = '';
            }
        });
    }
}

async function deleteJson(url) {
    const response = await fetch(url, {
        method: 'DELETE',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    let payload = null;
    try {
        payload = await response.json();
    } catch {
        payload = null;
    }

    if (!response.ok || !payload?.ok) {
        throw new Error(payload?.message || 'Request failed.');
    }

    return payload;
}

function updateCaseStatus(ticket) {
    if (!ticket) {
        return;
    }

    const badge = document.querySelector('[data-case-status-badge]');
    if (badge && ticket.status_badge) {
        const duplicate = badge.querySelector('.pg-badge-muted');
        badge.innerHTML = ticket.status_badge + (duplicate ? ` ${duplicate.outerHTML}` : '');
    }

    if (ticket.resolution_notes) {
        const resolutionField = document.getElementById('resolution_notes');
        if (resolutionField && !resolutionField.value.trim()) {
            resolutionField.value = ticket.resolution_notes;
        }
    }
}

function prependHistory(history) {
    if (!history) {
        return;
    }
    const list = document.querySelector('[data-case-history]');
    if (!list) {
        return;
    }
    const item = document.createElement('li');
    item.innerHTML = `
        ${history.status_badge || ''}
        <div class="pg-rich-note">${history.notes_html || ''}</div>
        <div class="text-muted">${escapeHtml(history.created_at)} · ${escapeHtml(history.changer || 'System')}</div>
    `;
    list.prepend(item);
}

function prependNote(comment) {
    if (!comment) {
        return;
    }
    const panel = document.querySelector('[data-live-notes]');
    const list = document.querySelector('[data-notes-list]');
    if (!list) {
        return;
    }
    list.querySelector('[data-notes-empty]')?.remove();
    list.insertAdjacentHTML('afterbegin', noteArticleHtml(comment));
    const countNode = panel?.querySelector('[data-notes-count]');
    if (countNode) {
        const count = list.querySelectorAll('[data-note-id]').length;
        countNode.textContent = `${count} Note${count === 1 ? '' : 's'}`;
    }
    document.dispatchEvent(new CustomEvent('plantgrid:note-added', { detail: comment }));
}

function flashStatus(node, message, { error = false } = {}) {
    if (!node) {
        return;
    }
    node.hidden = false;
    node.textContent = message;
    node.classList.toggle('is-error', error);
}

function renderSuggestion(root, suggestion) {
    const body = root.querySelector('[data-triage-body]');
    if (!body || !suggestion) {
        return;
    }

    const nextSteps = (suggestion.next_steps || [])
        .map((step) => `<li>${escapeHtml(step)}</li>`)
        .join('');

    const partners = (suggestion.partners || [])
        .map((partner) => personCard(partner, { name: 'partner_ids', checked: true, type: 'checkbox' }))
        .join('') || '<p class="text-muted small mb-0">No partner suggestions.</p>';

    body.hidden = false;
    body.innerHTML = `
        <div class="pg-triage-source">
            Source: <strong>${escapeHtml(suggestion.source === 'ai' ? 'AI + expertise model' : 'Expertise + availability model')}</strong>
        </div>
        <div class="pg-triage-block">
            <h3>Suggested owner</h3>
            ${suggestion.owner
                ? personCard(suggestion.owner, { name: 'owner_id', checked: true, type: 'radio' })
                : '<p class="text-muted small mb-0">No owner candidate found.</p>'}
        </div>
        <div class="pg-triage-block">
            <h3>Suggested partners</h3>
            <div class="pg-triage-people">${partners}</div>
        </div>
        <div class="pg-triage-block">
            <h3>Next steps / resolution path</h3>
            <ol class="pg-triage-steps">${nextSteps}</ol>
            <label class="form-label" for="triage-resolution">Resolution outline</label>
            <textarea class="form-control" id="triage-resolution" data-triage-resolution rows="3">${escapeHtml(suggestion.resolution_outline || '')}</textarea>
        </div>
        <div class="pg-triage-block">
            <label class="form-label" for="triage-note">Internal note for owner &amp; partners</label>
            <textarea class="form-control" id="triage-note" data-triage-note rows="7">${escapeHtml(suggestion.internal_note || '')}</textarea>
        </div>
        <div class="pg-triage-actions">
            <button class="btn btn-pg" type="button" data-triage-apply>Apply to case</button>
            <button class="btn btn-sm btn-pg-line" type="button" data-triage-fill>Fill update form only</button>
            <button class="btn btn-sm btn-pg-line" type="button" data-triage-refresh>Refresh suggestion</button>
        </div>
    `;
}

function currentDraft(root, suggestion) {
    return {
        open: root.classList.contains('is-open'),
        suggestion,
        owner_id: root.querySelector('input[name="owner_id"]:checked')?.value || suggestion?.owner?.id || null,
        partner_ids: [...root.querySelectorAll('input[name="partner_ids"]:checked')].map((input) => Number(input.value)),
        internal_note: root.querySelector('[data-triage-note]')?.value || suggestion?.internal_note || '',
        resolution_outline: root.querySelector('[data-triage-resolution]')?.value || suggestion?.resolution_outline || '',
    };
}

function fillUpdateForm(suggestion, root) {
    const resolution = root.querySelector('[data-triage-resolution]')?.value || suggestion.resolution_outline || '';
    const ownerId = root.querySelector('input[name="owner_id"]:checked')?.value
        || suggestion.owner?.id
        || '';

    const resolutionField = document.getElementById('resolution_notes');
    const assigned = document.getElementById('assigned_to');
    const transfer = document.getElementById('transfer_to');
    const statusSelect = document.getElementById('status');

    if (resolutionField && resolution) {
        resolutionField.value = resolution;
        resolutionField.dispatchEvent(new Event('input', { bubbles: true }));
    }
    if (assigned && ownerId) {
        assigned.value = String(ownerId);
    }
    if (transfer && ownerId) {
        transfer.value = String(ownerId);
    }
    if (statusSelect && suggestion.suggested_status) {
        const option = [...statusSelect.options].find((item) => item.value === suggestion.suggested_status);
        if (option) {
            statusSelect.value = suggestion.suggested_status;
            statusSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    const partnerSelect = document.getElementById('partner_user_id');
    const firstPartner = root.querySelector('input[name="partner_ids"]:checked')?.value
        || suggestion.partners?.[0]?.id;
    if (partnerSelect && firstPartner) {
        partnerSelect.value = String(firstPartner);
    }
}

function statusLabel(value) {
    return String(value || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function hideAiPanels(root) {
    root.querySelectorAll('[data-ai-panel]').forEach((panel) => {
        panel.hidden = true;
        panel.innerHTML = '';
    });
}

function renderSectionSuggestion(root, focus, suggestion) {
    hideAiPanels(root);
    const panel = root.querySelector(`[data-ai-panel="${focus}"]`)
        || root.querySelector('[data-ai-panel="full"]');
    if (!panel || !suggestion) {
        return;
    }

    let html = `<div class="pg-ai-inline-head">AI suggestion · ${escapeHtml(suggestion.source === 'ai' ? 'model' : 'heuristic')}</div>`;

    if (focus === 'owner' || focus === 'full') {
        html += `
            <div class="pg-triage-block">
                <h3>Suggested owner</h3>
                ${suggestion.owner
                    ? personCard(suggestion.owner, { name: 'owner_id', checked: true, type: 'radio' })
                    : '<p class="text-muted small mb-0">No owner candidate found.</p>'}
            </div>
        `;
    }

    if (focus === 'partners' || focus === 'full') {
        const partners = (suggestion.partners || [])
            .map((partner) => personCard(partner, { name: 'partner_ids', checked: true, type: 'checkbox' }))
            .join('') || '<p class="text-muted small mb-0">No partner suggestions.</p>';
        html += `
            <div class="pg-triage-block">
                <h3>Suggested partners</h3>
                <div class="pg-triage-people">${partners}</div>
            </div>
        `;
    }

    if (focus === 'status' || focus === 'full') {
        const steps = (suggestion.next_steps || []).map((step) => `<li>${escapeHtml(step)}</li>`).join('');
        html += `
            <div class="pg-triage-block">
                <h3>Suggested status</h3>
                <p class="mb-1"><strong>${escapeHtml(statusLabel(suggestion.suggested_status || 'in_progress'))}</strong></p>
                <ol class="pg-triage-steps">${steps}</ol>
            </div>
        `;
    }

    if (focus === 'resolution' || focus === 'full') {
        html += `
            <div class="pg-triage-block">
                <h3>Suggested resolution</h3>
                <textarea class="form-control" data-triage-resolution rows="4">${escapeHtml(suggestion.resolution_outline || '')}</textarea>
            </div>
        `;
    }

    if (focus === 'full') {
        html += `
            <div class="pg-triage-block">
                <label class="form-label" for="triage-note">Internal note for owner &amp; partners</label>
                <textarea class="form-control" id="triage-note" data-triage-note rows="5">${escapeHtml(suggestion.internal_note || '')}</textarea>
            </div>
        `;
    }

    html += `
        <div class="pg-triage-actions">
            <button class="btn btn-sm btn-pg" type="button" data-triage-apply data-ai-focus="${escapeHtml(focus)}">Apply suggestion</button>
            <button class="btn btn-sm btn-pg-line" type="button" data-triage-fill data-ai-focus="${escapeHtml(focus)}">Fill form only</button>
            <button class="btn btn-sm btn-pg-line" type="button" data-ai-dismiss>Dismiss</button>
        </div>
    `;

    panel.hidden = false;
    panel.innerHTML = html;
}

function initLiveCaseForms() {
    document.querySelectorAll('[data-live-case-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const status = form.parentElement?.querySelector('[data-live-case-status]');
            const button = form.querySelector('[type="submit"]');
            if (button) {
                button.disabled = true;
            }
            flashStatus(status, 'Saving…');
            try {
                const payload = await postForm(form);
                if (payload.owner || payload.assigned_to) {
                    updateCaseOwner(payload.owner, payload.assigned_to);
                    const list = document.querySelector('[data-case-partner-list]');
                    if (list && payload.assigned_to) {
                        list.dataset.caseOwnerId = String(payload.assigned_to);
                    }
                }
                if (payload.status_badge || payload.status) {
                    updateCaseStatus({
                        status: payload.status,
                        status_badge: payload.status_badge,
                    });
                }
                if (payload.partners) {
                    updateCasePartners(payload.partners);
                } else if (payload.partner) {
                    const existing = [...document.querySelectorAll('[data-case-partner-list] [data-partner-id]')].map((node) => ({
                        id: Number(node.dataset.partnerId),
                        name: node.querySelector('a')?.textContent?.trim() || '',
                        url: node.querySelector('a')?.getAttribute('href') || '#',
                        source: node.querySelector('.text-muted')?.textContent?.replace('·', '').trim() || 'manual',
                    }));
                    if (!existing.some((item) => item.id === payload.partner.id)) {
                        existing.push(payload.partner);
                    }
                    updateCasePartners(existing);
                }
                applyCaseActivity(payload);
                flashStatus(status, payload.message || 'Saved.');
                form.dispatchEvent(new CustomEvent('plantgrid:draft-clear', { bubbles: true }));
                document.dispatchEvent(new CustomEvent('plantgrid:draft-clear', { detail: { form } }));
                if (form.dataset.liveCaseForm === 'partners') {
                    form.reset();
                }
            } catch (error) {
                flashStatus(status, error.message || 'Could not save.', { error: true });
            } finally {
                if (button) {
                    button.disabled = false;
                }
            }
        });
    });

    document.querySelector('[data-case-partner-list]')?.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-remove-partner]');
        if (!button) {
            return;
        }

        const list = button.closest('[data-case-partner-list]');
        const status = list?.parentElement?.querySelector('[data-live-case-status]');
        const partnerId = button.dataset.partnerId;
        const partnerName = button.dataset.partnerName || 'this partner';
        const url = partnerRemoveUrl(list, partnerId);
        if (!url || !partnerId) {
            return;
        }

        if (!window.confirm(`Remove ${partnerName} from this case?`)) {
            return;
        }

        button.disabled = true;
        flashStatus(status, `Removing ${partnerName}…`);
        try {
            const payload = await deleteJson(url);
            updateCasePartners(payload.partners || []);
            applyCaseActivity(payload);
            flashStatus(status, payload.message || 'Partner removed.');
        } catch (error) {
            flashStatus(status, error.message || 'Could not remove partner.', { error: true });
            button.disabled = false;
        }
    });
}

export function initCaseTriage(refreshIcons = () => {}) {
    initLiveCaseForms();

    document.querySelectorAll('[data-case-workflow][data-triage-url], [data-case-triage][data-triage-url]').forEach((root) => {
        const ticketId = root.dataset.ticketId || 'case';
        const status = root.querySelector('[data-triage-status]');
        let latest = null;
        let activeFocus = 'full';
        let suggestPromise = null;

        const setStatus = (message, { error = false, hidden = false } = {}) => {
            if (!status) {
                return;
            }
            status.hidden = hidden || !message;
            status.textContent = message || '';
            status.classList.toggle('is-error', error);
        };

        const ensureSuggestion = async () => {
            if (latest) {
                return latest;
            }
            if (!suggestPromise) {
                suggestPromise = postJson(root.dataset.triageUrl)
                    .then((payload) => {
                        latest = payload.suggestion;
                        writeStore(ticketId, { suggestion: latest, open: true, focus: activeFocus });
                        return latest;
                    })
                    .finally(() => {
                        suggestPromise = null;
                    });
            }
            return suggestPromise;
        };

        const runSuggest = async (focus, button) => {
            activeFocus = focus || 'full';
            if (button) {
                button.disabled = true;
                button.dataset.originalLabel = button.textContent;
                button.textContent = 'Suggesting…';
            }
            setStatus('Analysing expertise, availability, and case context…');
            try {
                // "Suggest all" refreshes; section buttons reuse the last plan when available.
                if (activeFocus === 'full') {
                    latest = null;
                }
                const suggestion = await ensureSuggestion();
                renderSectionSuggestion(root, activeFocus, suggestion);
                setStatus('Suggestion ready. Apply it, fill the form, or dismiss.');
                refreshIcons();
            } catch (error) {
                setStatus(error.message || 'Suggestion failed.', { error: true });
            } finally {
                if (button) {
                    button.disabled = false;
                    button.textContent = button.dataset.originalLabel || button.textContent;
                }
            }
        };

        root.addEventListener('click', async (event) => {
            const suggestButton = event.target.closest('[data-ai-suggest]');
            if (suggestButton) {
                event.preventDefault();
                void runSuggest(suggestButton.dataset.aiSuggest || 'full', suggestButton);
                return;
            }

            if (event.target.closest('[data-ai-dismiss]')) {
                hideAiPanels(root);
                setStatus('', { hidden: true });
                return;
            }

            const fillButton = event.target.closest('[data-triage-fill]');
            if (fillButton && latest) {
                const focus = fillButton.dataset.aiFocus || activeFocus;
                if (focus === 'status' || focus === 'full') {
                    fillUpdateForm(latest, root);
                }
                if (focus === 'resolution' || focus === 'full') {
                    const field = document.getElementById('resolution_notes');
                    const text = root.querySelector('[data-triage-resolution]')?.value || latest.resolution_outline || '';
                    if (field && text) {
                        field.value = text;
                        field.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
                if (focus === 'owner' || focus === 'full') {
                    const ownerId = root.querySelector('input[name="owner_id"]:checked')?.value || latest.owner?.id;
                    const transfer = document.getElementById('transfer_to');
                    const assigned = document.getElementById('assigned_to');
                    if (ownerId && transfer) {
                        transfer.value = String(ownerId);
                    }
                    if (ownerId && assigned) {
                        assigned.value = String(ownerId);
                    }
                }
                if (focus === 'partners' || focus === 'full') {
                    const partnerId = root.querySelector('input[name="partner_ids"]:checked')?.value
                        || latest.partners?.[0]?.id;
                    const partnerSelect = document.getElementById('partner_user_id');
                    if (partnerSelect && partnerId) {
                        partnerSelect.value = String(partnerId);
                    }
                }
                setStatus('Form fields filled from the suggestion. Review and save.');
                return;
            }

            const applyButton = event.target.closest('[data-triage-apply]');
            if (!applyButton || !latest) {
                return;
            }

            const focus = applyButton.dataset.aiFocus || activeFocus;

            if (focus === 'status' || focus === 'resolution') {
                fillUpdateForm(latest, root);
                if (focus === 'resolution' || focus === 'full') {
                    const field = document.getElementById('resolution_notes');
                    const text = root.querySelector('[data-triage-resolution]')?.value || latest.resolution_outline || '';
                    if (field && text) {
                        field.value = text;
                    }
                }
                setStatus('Status / resolution filled. Review notes, then click Save update.');
                return;
            }

            const ownerId = (focus === 'owner' || focus === 'full')
                ? (root.querySelector('input[name="owner_id"]:checked')?.value || latest.owner?.id || null)
                : null;
            const partnerIds = (focus === 'partners' || focus === 'full')
                ? [...root.querySelectorAll('input[name="partner_ids"]:checked')].map((input) => Number(input.value))
                : [];
            const internalNote = focus === 'full'
                ? (root.querySelector('[data-triage-note]')?.value || latest.internal_note || '')
                : '';
            const resolutionOutline = root.querySelector('[data-triage-resolution]')?.value || latest.resolution_outline || '';

            if (!ownerId && partnerIds.length === 0 && !internalNote.trim()) {
                setStatus('Choose an owner or partner before applying.', { error: true });
                return;
            }

            applyButton.disabled = true;
            setStatus('Applying suggestion…');
            try {
                const payload = await postJson(root.dataset.triageApplyUrl, {
                    owner_id: ownerId ? Number(ownerId) : null,
                    partner_ids: partnerIds,
                    internal_note: internalNote,
                    resolution_outline: resolutionOutline,
                });

                if (payload.ticket) {
                    const list = document.querySelector('[data-case-partner-list]');
                    if (list && payload.ticket.assigned_to) {
                        list.dataset.caseOwnerId = String(payload.ticket.assigned_to);
                    }
                    updateCaseOwner(payload.ticket.owner, payload.ticket.assigned_to);
                    updateCasePartners(payload.ticket.partners || []);
                    updateCaseStatus(payload.ticket);
                }
                prependNote(payload.comment);
                prependHistory(payload.history);
                if (payload.activity) {
                    applyCaseActivity(payload);
                } else {
                    refreshCaseActivity();
                }

                if (focus === 'full') {
                    fillUpdateForm(latest, root);
                }

                setStatus(payload.message || 'Suggestion applied.');
            } catch (error) {
                setStatus(error.message || 'Could not apply suggestion.', { error: true });
            } finally {
                applyButton.disabled = false;
            }
        });
    });
}
