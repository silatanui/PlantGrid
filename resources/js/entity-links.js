/**
 * Linkify PlantGrid entity tokens and tags inside already-escaped HTML.
 * Safe to run after escapeHtml(); does not interpret raw HTML from the model.
 */

function escapeAttr(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

function mentionAnchor(type, url, label, token) {
    if (!url) {
        return escapeAttr(label);
    }
    return `<a class="pg-mention pg-mention-${escapeAttr(type)}" href="${escapeAttr(url)}" data-pg-token="${escapeAttr(token)}" contenteditable="false">${label}</a>`;
}

function mentionChip(type, label, token) {
    return `<span class="pg-mention pg-mention-${escapeAttr(type)}" data-pg-token="${escapeAttr(token)}" contenteditable="false">${label}</span>`;
}

function urlFor(templates, type, id) {
    const template = templates?.[type];
    if (!template || !id) {
        return null;
    }
    return String(template).replaceAll('__ID__', String(id));
}

function parseEntityIndex(raw) {
    if (!raw) {
        return {};
    }
    try {
        return typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch {
        return {};
    }
}

function parseUrlTemplates(raw) {
    if (!raw) {
        return {};
    }
    try {
        return typeof raw === 'string' ? JSON.parse(raw) : raw;
    } catch {
        return {};
    }
}

function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Replace plain full names with people chips, without touching existing HTML tags.
 */
function linkifyPeopleByName(text, entities, urls) {
    const byName = entities.people_by_name || {};
    const entries = Object.entries(byName)
        .filter(([key, info]) => key && info?.id && String(key).includes(' '))
        .sort((a, b) => b[0].length - a[0].length);

    if (!entries.length) {
        return text;
    }

    const slots = [];
    const protectedText = String(text).replace(/<[^>]+>/g, (tag) => {
        slots.push(tag);
        return `\u0000${slots.length - 1}\u0000`;
    });

    let out = protectedText;
    entries.forEach(([, info]) => {
        const label = info.label || '';
        if (!label) {
            return;
        }
        const pattern = new RegExp(`(?<![\\w@])${escapeRegExp(label)}(?![\\w])`, 'giu');
        out = out.replace(pattern, () => {
            const url = urlFor(urls, 'people', info.id);
            const token = `@[${label}](people:${info.id})`;
            return mentionAnchor('people', url, `@${label}`, token);
        });
    });

    return out.replace(/\u0000(\d+)\u0000/g, (_, index) => slots[Number(index)] ?? '');
}

/**
 * @param {string} escapedHtml already HTML-escaped plain text (may include <br> later)
 * @param {{entities?: object, urls?: object}|HTMLElement|null} source
 */
export function linkifyEntities(escapedHtml, source = null) {
    let entities = {};
    let urls = {};
    if (source instanceof HTMLElement) {
        entities = parseEntityIndex(source.dataset.aiEntities || document.body.dataset.aiEntities || '{}');
        urls = parseUrlTemplates(source.dataset.aiUrls || document.body.dataset.aiUrls || '{}');
    } else if (source && typeof source === 'object') {
        entities = parseEntityIndex(source.entities || {});
        urls = parseUrlTemplates(source.urls || {});
    } else {
        const root = document.querySelector('[data-ai-root]');
        entities = parseEntityIndex(root?.dataset.aiEntities || document.body.dataset.aiEntities || '{}');
        urls = parseUrlTemplates(root?.dataset.aiUrls || document.body.dataset.aiUrls || '{}');
    }

    let text = String(escapedHtml ?? '');

    // Structured tokens: @[Label](type:id)
    text = text.replace(
        /@\[(.*?)\]\((people|user|case|asset|device|server|location|department):(\d+)\)/gu,
        (_full, label, typeRaw, idRaw) => {
            const type = typeRaw === 'user' ? 'people' : typeRaw;
            const id = String(idRaw);
            if (type === 'department') {
                return mentionChip('department', `@${label}`, `@[${label}](department:${id})`);
            }
            const url = urlFor(urls, type, id);
            const prefix = type === 'people' ? '@' : '';
            const token = `@[${label}](${type}:${id})`;
            return mentionAnchor(type, url, `${prefix}${label}`, token);
        }
    );

    text = text.replace(/@\[everyone\]\(everyone\)/giu, () => mentionChip('everyone', '@everyone', '@everyone'));

    // Bare @handles (and @everyone)
    text = text.replace(/(^|[\s(>])@([a-z0-9._-]+)/giu, (full, prefix, handleRaw) => {
        const handle = String(handleRaw).toLowerCase();
        if (handle === 'everyone') {
            return `${prefix}${mentionChip('everyone', '@everyone', '@everyone')}`;
        }
        const personId = entities.people?.[handle];
        if (personId) {
            const label = entities.people_labels?.[handle] || handleRaw;
            const url = urlFor(urls, 'people', personId);
            const token = `@[${label}](people:${personId})`;
            return `${prefix}${mentionAnchor('people', url, `@${label}`, token)}`;
        }
        const dept = entities.departments?.[handle];
        if (dept) {
            return `${prefix}${mentionChip('department', `@${dept.code || handleRaw}`, `@[${dept.name || handle}](department:${dept.id})`)}`;
        }
        return full;
    });

    // Bare full names ("Faith Cheruiyot") when the model skips @handles
    text = linkifyPeopleByName(text, entities, urls);

    // Case numbers PG-YYYY-NNNN
    text = text.replace(/(?<![\w\/])#?(PG-\d{4}-\d+)\b/gu, (full, numberRaw) => {
        const number = String(numberRaw).toUpperCase();
        const id = entities.cases?.[number];
        if (!id) {
            return full;
        }
        return mentionAnchor('case', urlFor(urls, 'case', id), number, number);
    });

    // Asset tags PG-XXX-NNNN (exclude case pattern already handled)
    text = text.replace(/(?<![\w\/])#?(PG-[A-Z]+-\d+)\b/gu, (full, tagRaw) => {
        const tag = String(tagRaw).toUpperCase();
        if (/^PG-\d{4}-\d+$/.test(tag)) {
            return full;
        }
        const id = entities.assets?.[tag];
        if (!id) {
            return full;
        }
        return mentionAnchor('asset', urlFor(urls, 'asset', id), tag, tag);
    });

    // Hostnames PLT-...
    text = text.replace(/(?<![\w\/])#?(PLT-[A-Z0-9-]+)\b/gu, (full, hostRaw) => {
        const host = String(hostRaw).toUpperCase();
        const deviceId = entities.devices?.[host];
        if (deviceId) {
            return mentionAnchor('device', urlFor(urls, 'device', deviceId), host, host);
        }
        const serverId = entities.servers?.[host];
        if (serverId) {
            return mentionAnchor('server', urlFor(urls, 'server', serverId), host, host);
        }
        return full;
    });

    // Location codes like PKG-01 (conservative)
    text = text.replace(/(?<![\w\/#-])#?([A-Z]{2,6}-[A-Z0-9]{2,6})\b/gu, (full, codeRaw) => {
        const code = String(codeRaw).toUpperCase();
        if (code.startsWith('PG-') || code.startsWith('PLT-')) {
            return full;
        }
        const loc = entities.locations?.[code];
        if (!loc) {
            return full;
        }
        const token = `@[${loc.name}](location:${loc.id})`;
        return mentionAnchor('location', urlFor(urls, 'location', loc.id), `${loc.name} (${code})`, token);
    });

    // Documentation paths (/docs/{slug})
    text = text.replace(/(?<![\w])\/docs\/([a-z0-9._/-]+)/giu, (full, slug) => {
        const template = urls.docs || '';
        if (!template) {
            return full;
        }
        const href = template.replaceAll('__SLUG__', slug);
        return `<a class="pg-mention pg-mention-docs" href="${escapeAttr(href)}">${full}</a>`;
    });

    return text;
}
