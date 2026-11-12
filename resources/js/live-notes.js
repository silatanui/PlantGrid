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

function setStatus(node, message, isError = false) {
    if (!node) {
        return;
    }
    node.hidden = !message;
    node.textContent = message || '';
    node.classList.toggle('is-error', Boolean(isError));
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

    return `<span class="ms-auto d-inline-flex flex-wrap gap-1" data-note-actions>${editBtn}${deleteBtn}</span>`;
}

function noteArticleHtml(comment) {
    const internalBadge = comment.is_internal
        ? '<span class="pg-badge pg-badge-muted" data-note-internal-badge>Internal</span>'
        : '';
    const edited = comment.edited
        ? `<span class="text-muted small" data-note-edited>· edited ${escapeHtml(comment.updated_at)}</span>`
        : '';

    return `
        <article
            class="pg-note"
            data-note-id="${comment.id}"
            data-note-body="${escapeHtml(JSON.stringify(comment.comment))}"
            data-note-visibility="${escapeHtml(comment.visibility)}"
            data-note-update="${escapeHtml(comment.update_url)}"
            data-note-delete-url="${escapeHtml(comment.delete_url || '')}"
            data-can-edit="${comment.can_edit ? '1' : '0'}"
            data-can-delete="${comment.can_delete ? '1' : '0'}"
        >
            <div class="d-flex flex-wrap gap-2 align-items-center mb-1">
                <strong>${escapeHtml(comment.author)}</strong>
                ${internalBadge}
                <span class="text-muted small" data-note-time>${escapeHtml(comment.created_at)}</span>
                ${edited}
                ${noteActionsHtml(comment)}
            </div>
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
                <span>Internal ICT note</span>
            </label>
            <label class="pg-note-visibility-option">
                <input type="radio" name="visibility" value="requester_visible" ${selected === 'requester_visible' ? 'checked' : ''}>
                <span>Visible to requester</span>
            </label>
        </div>
    `;
}

export function initLiveNotes() {
    const root = document.querySelector('[data-live-notes]');
    if (!root) {
        return;
    }

    const list = root.querySelector('[data-notes-list]');
    const form = root.querySelector('[data-notes-form]');
    const status = root.querySelector('[data-notes-status]');
    const canInternal = root.dataset.canInternal === '1';
    let openEditor = null;

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

    const applyComment = (article, comment) => {
        article.dataset.noteBody = JSON.stringify(comment.comment);
        article.dataset.noteVisibility = comment.visibility;
        article.dataset.noteUpdate = comment.update_url;
        article.dataset.noteDeleteUrl = comment.delete_url || '';
        article.dataset.canEdit = comment.can_edit ? '1' : '0';
        article.dataset.canDelete = comment.can_delete ? '1' : '0';

        const html = article.querySelector('[data-note-html]');
        if (html) {
            html.innerHTML = comment.html;
            html.hidden = false;
        }

        let badge = article.querySelector('[data-note-internal-badge]');
        if (comment.is_internal && !badge) {
            const head = article.querySelector('.d-flex');
            badge = document.createElement('span');
            badge.className = 'pg-badge pg-badge-muted';
            badge.dataset.noteInternalBadge = '';
            badge.textContent = 'Internal';
            head?.querySelector('strong')?.after(badge);
        }
        if (!comment.is_internal && badge) {
            badge.remove();
        }

        let edited = article.querySelector('[data-note-edited]');
        if (comment.edited) {
            if (!edited) {
                edited = document.createElement('span');
                edited.className = 'text-muted small';
                edited.dataset.noteEdited = '';
                article.querySelector('[data-note-time]')?.after(edited);
            }
            edited.textContent = `· edited ${comment.updated_at}`;
        }

        const head = article.querySelector('.d-flex');
        let actions = article.querySelector('[data-note-actions]');
        if (comment.can_edit || comment.can_delete) {
            if (!actions) {
                actions = document.createElement('span');
                actions.className = 'ms-auto d-inline-flex flex-wrap gap-1';
                actions.dataset.noteActions = '';
                head?.appendChild(actions);
            }
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
            <textarea class="form-control mb-1" id="note-edit-${article.dataset.noteId}" rows="4" data-mentions data-ai-assist data-format-toolbar required></textarea>
            ${visibilityControls(canInternal, visibility)}
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-pg" type="button" data-note-save>Save changes</button>
                <button class="btn btn-sm btn-pg-line" type="button" data-note-cancel>Cancel</button>
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
                empty.className = 'text-muted';
                empty.dataset.notesEmpty = '';
                empty.textContent = 'No notes yet.';
                list.appendChild(empty);
            }
            setStatus(status, 'Note deleted.');
        } catch (error) {
            setStatus(status, error.message || 'Could not delete the note.', true);
        }
    };

    list?.addEventListener('click', (event) => {
        const editButton = event.target.closest('[data-note-edit]');
        if (editButton) {
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

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const submit = form.querySelector('[data-notes-submit]');
        const textarea = form.querySelector('#comment');
        const formData = new FormData(form);
        if (submit) {
            submit.disabled = true;
            submit.textContent = 'Posting…';
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
            form.reset();
            if (canInternal) {
                const internal = form.querySelector('input[name="visibility"][value="internal"]');
                if (internal) {
                    internal.checked = true;
                }
            }
            if (textarea) {
                textarea.value = '';
            }
            setStatus(status, 'Note posted. Only you can edit it; you or an admin can delete it.');
        } catch (error) {
            setStatus(status, error.message || 'Could not post the note.', true);
        } finally {
            if (submit) {
                submit.disabled = false;
                submit.textContent = 'Post note';
            }
        }
    });
}
