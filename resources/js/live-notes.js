import { initMentions } from './mentions';
import { initFormatToolbars } from './format-toolbar';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

let statusTimer = null;

function setStatus(node, message, isError = false) {
    if (!node) {
        return;
    }
    if (statusTimer) {
        window.clearTimeout(statusTimer);
        statusTimer = null;
    }
    node.hidden = !message;
    node.textContent = message || '';
    node.classList.toggle('is-error', Boolean(isError));
    if (message && !isError) {
        statusTimer = window.setTimeout(() => {
            node.hidden = true;
            node.textContent = '';
            statusTimer = null;
        }, 4000);
    }
}

function noteActionsHtml(comment) {
    if (!comment.can_edit && !comment.can_delete) {
        return '';
    }

    const editBtn = comment.can_edit
        ? '<button class="btn btn-sm btn-pg-line" type="button" data-note-edit>Edit</button>'
        : '';
    const deleteBtn = comment.can_delete
        ? '<button class="btn btn-sm btn-pg-line" type="button" data-note-delete>Delete</button>'
        : '';

    return `<span class="pg-note-actions" data-note-actions>${editBtn}${deleteBtn}</span>`;
}

function noteArticleHtml(comment) {
    const internalBadge = comment.is_internal
        ? '<span class="pg-note-visibility-badge" data-note-internal-badge>Internal</span>'
        : '';
    const role = comment.author_role
        ? `<span class="pg-note-role" data-note-role><i data-lucide="user-round" aria-hidden="true"></i>${escapeHtml(comment.author_role)}</span>`
        : '';
    const edited = comment.edited
        ? `<span class="pg-note-edited" data-note-edited>· edited ${escapeHtml(comment.updated_at)}</span>`
        : '';
    const tone = comment.is_internal ? 'is-internal' : 'is-public';

    return `
        <article
            class="pg-note ${tone}"
            data-note-id="${comment.id}"
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
                        ${noteActionsHtml(comment)}
                    </div>
                    <div class="pg-note-time-row">
                        <time class="pg-note-time" data-note-time>${escapeHtml(comment.created_at)}</time>
                        ${edited}
                    </div>
                </div>
            </header>
            <div class="pg-rich-note" data-note-html>${comment.html}</div>
            <div class="pg-note-editor" data-note-editor hidden></div>
        </article>
    `;
}

function visibilityControls(canInternal, selected) {
    if (!canInternal) {
        return `<input type="hidden" name="visibility" value="requester_visible">`;
    }
    return `
        <div class="pg-note-visibility" role="group" aria-label="Note visibility">
            <label class="pg-note-visibility-option">
                <input type="radio" name="visibility" value="internal" ${selected === 'internal' ? 'checked' : ''}>
                <span class="pg-note-visibility-copy">
                    <strong>Internal ICT note</strong>
                    <small>Visible to ICT team and authorized staff only</small>
                </span>
            </label>
            <label class="pg-note-visibility-option">
                <input type="radio" name="visibility" value="requester_visible" ${selected === 'requester_visible' ? 'checked' : ''}>
                <span class="pg-note-visibility-copy">
                    <strong>Visible to requester</strong>
                    <small>Visible to the requester (e.g. user, client)</small>
                </span>
            </label>
        </div>
    `;
}

function syncNotesCount(root, list) {
    const countNode = root.querySelector('[data-notes-count]');
    if (!countNode || !list) {
        return;
    }
    const count = list.querySelectorAll('[data-note-id]').length;
    countNode.textContent = `${count} Note${count === 1 ? '' : 's'}`;
}

export function initLiveNotes(refreshIcons = () => {}) {
    const root = document.querySelector('[data-live-notes]');
    if (!root) {
        return;
    }

    const list = root.querySelector('[data-notes-list]');
    const form = root.querySelector('[data-notes-form]');
    const openTrigger = root.querySelector('[data-notes-open]');
    const status = root.querySelector('[data-notes-status]');
    const canInternal = root.dataset.canInternal === '1';
    let openEditor = null;

    const setComposerOpen = (open) => {
        if (!form) {
            return;
        }
        form.hidden = !open;
        if (openTrigger) {
            openTrigger.hidden = open;
        }
        root.classList.toggle('is-composing', open);
        if (open) {
            const textarea = form.querySelector('#comment');
            window.requestAnimationFrame(() => {
                textarea?.focus();
                refreshIcons();
            });
        }
    };

    const closeEditor = () => {
        if (!openEditor) {
            return;
        }
        const editor = openEditor.querySelector('[data-note-editor]');
        const html = openEditor.querySelector('[data-note-html]');
        const actions = openEditor.querySelector('[data-note-actions]');
        if (editor) {
            editor.hidden = true;
            editor.innerHTML = '';
        }
        if (html) {
            html.hidden = false;
        }
        if (actions) {
            actions.hidden = false;
        }
        openEditor = null;
    };

    const resetComposer = ({ close = true } = {}) => {
        if (!form) {
            return;
        }
        form.reset();
        const textarea = form.querySelector('#comment');
        if (textarea) {
            textarea.value = '';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
        }
        if (canInternal) {
            const internal = form.querySelector('input[name="visibility"][value="internal"]');
            if (internal) {
                internal.checked = true;
            }
        }
        form.querySelector('[data-attach-clear]')?.click();
        form.dispatchEvent(new CustomEvent('plantgrid:draft-clear', { bubbles: true }));
        document.dispatchEvent(new CustomEvent('plantgrid:draft-clear', { detail: { form } }));
        if (close) {
            setComposerOpen(false);
        }
    };

    const applyComment = (article, comment) => {
        article.dataset.noteBody = JSON.stringify(comment.comment);
        article.dataset.noteVisibility = comment.visibility;
        article.dataset.noteUpdate = comment.update_url;
        article.dataset.noteDeleteUrl = comment.delete_url || '';
        article.dataset.canEdit = comment.can_edit ? '1' : '0';
        article.dataset.canDelete = comment.can_delete ? '1' : '0';
        article.classList.toggle('is-internal', Boolean(comment.is_internal));
        article.classList.toggle('is-public', !comment.is_internal);

        const html = article.querySelector('[data-note-html]');
        if (html) {
            html.innerHTML = comment.html;
            html.hidden = false;
        }

        const author = article.querySelector('[data-note-author]');
        if (author) {
            author.textContent = comment.author || 'User';
        }

        const avatar = article.querySelector('.pg-note-avatar');
        if (avatar && comment.author_initials) {
            avatar.textContent = comment.author_initials;
        }

        let role = article.querySelector('[data-note-role]');
        if (comment.author_role) {
            if (!role) {
                role = document.createElement('span');
                role.className = 'pg-note-role';
                role.dataset.noteRole = '';
                article.querySelector('[data-note-author]')?.after(role);
            }
            role.innerHTML = `<i data-lucide="user-round" aria-hidden="true"></i>${escapeHtml(comment.author_role)}`;
        }

        let badge = article.querySelector('[data-note-internal-badge]');
        if (comment.is_internal && !badge) {
            const head = article.querySelector('.pg-note-meta-top');
            badge = document.createElement('span');
            badge.className = 'pg-note-visibility-badge';
            badge.dataset.noteInternalBadge = '';
            badge.textContent = 'Internal';
            const actions = head?.querySelector('[data-note-actions]');
            if (actions) {
                head.insertBefore(badge, actions);
            } else {
                head?.appendChild(badge);
            }
        }
        if (!comment.is_internal && badge) {
            badge.remove();
        }

        let edited = article.querySelector('[data-note-edited]');
        if (comment.edited) {
            if (!edited) {
                edited = document.createElement('span');
                edited.className = 'pg-note-edited';
                edited.dataset.noteEdited = '';
                article.querySelector('.pg-note-time-row')?.appendChild(edited);
            }
            edited.textContent = `· edited ${comment.updated_at}`;
        }

        const head = article.querySelector('.pg-note-meta-top');
        const actionGroups = [...article.querySelectorAll('[data-note-actions]')];
        const orphanButtons = [...article.querySelectorAll('button[data-note-edit], button[data-note-delete]')]
            .map((button) => button.closest('.pg-note-actions, [data-note-actions], span.ms-auto'))
            .filter(Boolean);
        const allGroups = [...new Set([...actionGroups, ...orphanButtons])];
        let actions = allGroups[0] || null;
        allGroups.slice(1).forEach((group) => group.remove());

        if (comment.can_edit || comment.can_delete) {
            if (!actions) {
                actions = document.createElement('span');
                actions.className = 'pg-note-actions';
                actions.dataset.noteActions = '';
                head?.appendChild(actions);
            }
            actions.dataset.noteActions = '';
            actions.className = 'pg-note-actions';
            actions.innerHTML = '';
            if (comment.can_edit) {
                const editBtn = document.createElement('button');
                editBtn.className = 'btn btn-sm btn-pg-line';
                editBtn.type = 'button';
                editBtn.dataset.noteEdit = '';
                editBtn.textContent = 'Edit';
                actions.appendChild(editBtn);
            }
            if (comment.can_delete) {
                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'btn btn-sm btn-pg-line';
                deleteBtn.type = 'button';
                deleteBtn.dataset.noteDelete = '';
                deleteBtn.textContent = 'Delete';
                actions.appendChild(deleteBtn);
            }
            actions.hidden = false;
        } else if (actions) {
            actions.remove();
        }

        refreshIcons();
    };

    const openEdit = (article) => {
        if (article.dataset.canEdit !== '1') {
            return;
        }
        closeEditor();
        const editor = article.querySelector('[data-note-editor]');
        const html = article.querySelector('[data-note-html]');
        const actions = article.querySelector('[data-note-actions]');
        if (!editor) {
            return;
        }

        let body = '';
        try {
            body = JSON.parse(article.dataset.noteBody || '""');
        } catch {
            body = article.dataset.noteBody || '';
        }
        const visibility = article.dataset.noteVisibility || 'requester_visible';
        editor.innerHTML = `
            <label class="form-label" for="note-edit-${article.dataset.noteId}">Edit note</label>
            <textarea class="form-control pg-note-textarea mb-2" id="note-edit-${article.dataset.noteId}" rows="4" data-mentions data-ai-assist data-format-toolbar required></textarea>
            ${visibilityControls(canInternal, visibility)}
            <div class="pg-note-composer-actions is-inline">
                <button class="btn btn-sm btn-pg-line" type="button" data-note-cancel>Cancel</button>
                <button class="btn btn-sm btn-pg" type="button" data-note-save>Save changes</button>
            </div>
        `;
        editor.hidden = false;
        if (html) {
            html.hidden = true;
        }
        if (actions) {
            actions.hidden = true;
        }
        openEditor = article;
        const textareaSeed = editor.querySelector('textarea');
        if (textareaSeed) {
            textareaSeed.value = body;
        }
        initFormatToolbars(editor);
        initMentions(editor);
        refreshIcons();

        editor.querySelector('[data-note-cancel]')?.addEventListener('click', closeEditor);
        editor.querySelector('[data-note-save]')?.addEventListener('click', async () => {
            const textarea = editor.querySelector('textarea');
            const nextBody = textarea?.value.trim() || '';
            if (nextBody.length < 1) {
                setStatus(status, 'Note text is required.', true);
                return;
            }
            const selected = editor.querySelector('input[name="visibility"]:checked')?.value
                || editor.querySelector('input[name="visibility"]')?.value
                || 'requester_visible';

            const saveBtn = editor.querySelector('[data-note-save]');
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Saving…';
            }
            setStatus(status, 'Saving note…');

            try {
                const response = await fetch(article.dataset.noteUpdate, {
                    method: 'PUT',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        comment: nextBody,
                        visibility: selected,
                    }),
                });
                const payload = await response.json();
                if (!response.ok || !payload?.ok) {
                    throw new Error(payload?.message || 'Could not update the note.');
                }
                applyComment(article, payload.comment);
                closeEditor();
                setStatus(status, 'Note updated.');
            } catch (error) {
                setStatus(status, error.message || 'Could not update the note.', true);
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Save changes';
                }
            }
        });
    };

    const deleteNote = async (article) => {
        if (article.dataset.canDelete !== '1' || !article.dataset.noteDeleteUrl) {
            return;
        }
        if (!window.confirm('Delete this note? This cannot be undone.')) {
            return;
        }

        setStatus(status, 'Deleting note…');
        try {
            const response = await fetch(article.dataset.noteDeleteUrl, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            const payload = await response.json();
            if (!response.ok || !payload?.ok) {
                throw new Error(payload?.message || 'Could not delete the note.');
            }
            if (openEditor === article) {
                openEditor = null;
            }
            article.remove();
            if (list && !list.querySelector('[data-note-id]')) {
                const empty = document.createElement('p');
                empty.className = 'pg-notes-empty';
                empty.dataset.notesEmpty = '';
                empty.textContent = 'No notes yet. Use Add a note to post the first update.';
                list.appendChild(empty);
            }
            syncNotesCount(root, list);
            setStatus(status, 'Note deleted.');
        } catch (error) {
            setStatus(status, error.message || 'Could not delete the note.', true);
        }
    };

    list?.querySelectorAll('[data-note-id]').forEach((article) => {
        const groups = [...article.querySelectorAll('button[data-note-edit], button[data-note-delete]')]
            .map((button) => button.closest('.pg-note-actions, [data-note-actions], span.ms-auto'))
            .filter(Boolean);
        const unique = [...new Set(groups)];
        if (unique.length > 1) {
            const keep = unique.find((group) => group.hasAttribute('data-note-actions')) || unique[0];
            keep.dataset.noteActions = '';
            unique.filter((group) => group !== keep).forEach((group) => group.remove());
        }
    });

    list?.addEventListener('click', (event) => {
        const editButton = event.target.closest('button[data-note-edit]');
        if (editButton) {
            event.preventDefault();
            event.stopPropagation();
            const article = editButton.closest('[data-note-id]');
            if (article) {
                openEdit(article);
            }
            return;
        }

        const deleteButton = event.target.closest('button[data-note-delete]');
        if (deleteButton) {
            event.preventDefault();
            event.stopPropagation();
            const article = deleteButton.closest('[data-note-id]');
            if (article) {
                deleteNote(article);
            }
        }
    });

    openTrigger?.addEventListener('click', () => {
        setComposerOpen(true);
    });

    form?.querySelector('[data-notes-cancel]')?.addEventListener('click', () => {
        resetComposer({ close: true });
        setStatus(status, 'Draft cleared.');
    });

    form?.addEventListener('plantgrid:draft-restored', () => {
        setComposerOpen(true);
    });

    if (form && !form.hidden) {
        setComposerOpen(true);
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('[data-notes-submit]');
        const formData = new FormData(form);
        if (submit) {
            submit.disabled = true;
        }
        setStatus(status, 'Posting note…');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: formData,
            });
            const payload = await response.json();
            if (!response.ok || !payload?.ok) {
                throw new Error(payload?.message || Object.values(payload?.errors || {})?.[0]?.[0] || 'Could not post the note.');
            }

            list?.querySelector('[data-notes-empty]')?.remove();
            const wrapper = document.createElement('div');
            wrapper.innerHTML = noteArticleHtml(payload.comment).trim();
            const article = wrapper.firstElementChild;
            list?.prepend(article);
            resetComposer({ close: true });
            syncNotesCount(root, list);
            refreshIcons();
            setStatus(status, 'Note posted. Only you can edit it; you or an admin can delete it.');
        } catch (error) {
            setStatus(status, error.message || 'Could not post the note.', true);
            setComposerOpen(true);
        } finally {
            if (submit) {
                submit.disabled = false;
            }
        }
    });
}
