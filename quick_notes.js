(function () {
    'use strict';

    const API = 'quick_notes_api.php';
    const $ = (id) => document.getElementById(id);
    const clamp = (v, a, b) => Math.min(b, Math.max(a, v));

    const editor = $('qn-editor');
    const scroll = $('qn-scroll');

    const DEFAULT_TEXT = '#e8e8e8';
    const TEXT_COLORS = ['#e8e8e8', '#ff6b6b', '#ff9f43', '#ffd93d', '#4cd964', '#00d2c6',
                         '#3b9dff', '#a463f2', '#ff7eb6', '#9aa0a6', '#ffffff', '#000000'];
    const HIGHLIGHTS = ['#ffd93d', '#b9f6ca', '#a7d8ff', '#ffb4b4', '#e1bee7', '#ffe0b2',
                        '#5c4b00', '#1f4d2e', '#1f3f5f', '#5a2a2a', '#4a2a5f', 'none'];

    const EXEC_ACTS = new Set([
        'undo', 'redo', 'bold', 'italic', 'underline', 'strikeThrough',
        'justifyLeft', 'justifyCenter', 'justifyRight', 'insertUnorderedList', 'insertOrderedList',
    ]);

    const state = { notes: [], current: null, dirty: false, rev: 0 };
    let saveTimer = null;
    let saveChain = Promise.resolve();
    let statsTimer = null;
    let toolRaf = false;
    let savedRange = null;
    let selImg = null;
    let lastSizePx = 16;

    /* =====================================================
       Small helpers
       ===================================================== */
    async function api(action, params = {}, method = 'POST') {
        const body = new URLSearchParams({ action, ...params });
        const opts = { method, credentials: 'same-origin' };
        let url = API;
        if (method === 'GET') url += '?' + body.toString();
        else opts.body = body;
        let res, data;
        try {
            res = await fetch(url, opts);
            data = await res.json();
        } catch (err) {
            return { error: 'Could not reach the server' };
        }
        if (res.status === 401) { window.location.href = 'login.php'; return { error: 'Signed out' }; }
        if (!res.ok && !data.error) data.error = 'Request failed';
        return data;
    }

    let toastTimer;
    function toast(msg) {
        const t = $('qn-toast');
        t.textContent = msg;
        t.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => t.classList.remove('show'), 3400);
    }

    const escHtml = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

    function confirmDialog({ title, message, confirmLabel }) {
        return new Promise((resolve) => {
            const modal = $('qn-modal');
            const ok = $('qn-confirm-ok');
            const cancel = $('qn-confirm-cancel');
            $('qn-confirm-title').textContent = title;
            $('qn-confirm-msg').textContent = message;
            ok.textContent = confirmLabel;
            const done = (v) => {
                ok.onclick = null;
                cancel.onclick = null;
                modal.onpointerdown = null;
                modal.classList.remove('open');
                modal.setAttribute('aria-hidden', 'true');
                resolve(v);
            };
            ok.onclick = () => done(true);
            cancel.onclick = () => done(false);
            modal.onpointerdown = (e) => { if (e.target === modal) done(false); };
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            cancel.focus();
        });
    }

    /* =====================================================
       Sanitizer (notes are re-loaded as HTML, so only allow known-safe markup)
       ===================================================== */
    const ALLOWED_TAGS = new Set(['P', 'DIV', 'SPAN', 'BR', 'H1', 'H2', 'H3', 'H4', 'B', 'STRONG', 'I', 'EM', 'U', 'S',
        'STRIKE', 'DEL', 'FONT', 'SUB', 'SUP', 'UL', 'OL', 'LI', 'A', 'IMG', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TD', 'TH',
        'HR', 'BLOCKQUOTE', 'PRE', 'CODE', 'INPUT']);
    const DROP_TAGS = new Set(['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'LINK', 'META', 'SVG', 'MATH', 'FORM',
        'TEXTAREA', 'SELECT', 'BUTTON', 'NOSCRIPT', 'TEMPLATE']);
    const ALLOWED_STYLE = new Set(['color', 'background-color', 'background', 'font-family', 'font-size', 'font-weight',
        'font-style', 'text-decoration', 'text-decoration-line', 'text-align', 'width', 'height', 'margin-left', 'list-style-type']);

    function cleanStyle(s) {
        return s.split(';').map((d) => d.trim()).filter(Boolean).filter((d) => {
            const i = d.indexOf(':');
            if (i < 0) return false;
            const prop = d.slice(0, i).trim().toLowerCase();
            const val = d.slice(i + 1).toLowerCase();
            return ALLOWED_STYLE.has(prop) && !/url\(|expression|javascript|@import/.test(val);
        }).join(';');
    }

    function cleanNode(src, dst) {
        src.childNodes.forEach((n) => {
            if (n.nodeType === 3) { dst.appendChild(document.createTextNode(n.nodeValue)); return; }
            if (n.nodeType !== 1) return;
            const tag = n.tagName.toUpperCase();
            if (DROP_TAGS.has(tag)) return;
            if (!ALLOWED_TAGS.has(tag)) { cleanNode(n, dst); return; }
            if (tag === 'INPUT' && (n.getAttribute('type') || '').toLowerCase() !== 'checkbox') return;

            const el = document.createElement(tag.toLowerCase());
            const style = n.getAttribute('style');
            if (style) { const cs = cleanStyle(style); if (cs) el.setAttribute('style', cs); }
            const cls = (n.getAttribute('class') || '').split(/\s+/).filter((c) => c === 'qn-check' || c === 'qn-check-text');
            if (cls.length) el.setAttribute('class', cls.join(' '));

            if (tag === 'A') {
                const href = n.getAttribute('href') || '';
                if (/^(https?:|mailto:|#)/i.test(href)) {
                    el.setAttribute('href', href);
                    el.setAttribute('target', '_blank');
                    el.setAttribute('rel', 'noopener noreferrer');
                }
            } else if (tag === 'IMG') {
                const srcAttr = n.getAttribute('src') || '';
                if (!/^(data:image\/(png|jpe?g|gif|webp|svg\+xml);|https?:)/i.test(srcAttr)) return;
                el.setAttribute('src', srcAttr);
                if (n.getAttribute('alt')) el.setAttribute('alt', n.getAttribute('alt'));
            } else if (tag === 'TD' || tag === 'TH') {
                ['colspan', 'rowspan'].forEach((a) => { if (/^\d{1,2}$/.test(n.getAttribute(a) || '')) el.setAttribute(a, n.getAttribute(a)); });
            } else if (tag === 'INPUT') {
                el.setAttribute('type', 'checkbox');
                el.setAttribute('contenteditable', 'false');
                if (n.hasAttribute('checked')) el.setAttribute('checked', '');
            } else if (tag === 'FONT') {
                ['color', 'face', 'size'].forEach((a) => {
                    const v = n.getAttribute(a);
                    if (v && /^[#\w\s,'"-]{1,60}$/.test(v)) el.setAttribute(a, v);
                });
            }
            cleanNode(n, el);
            dst.appendChild(el);
        });
    }

    function sanitize(html) {
        if (!html) return '';
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const box = document.createElement('div');
        cleanNode(doc.body, box);
        return box.innerHTML;
    }

    /* =====================================================
       Selection helpers
       ===================================================== */
    document.addEventListener('selectionchange', () => {
        const s = window.getSelection();
        if (s.rangeCount && editor.contains(s.anchorNode)) {
            savedRange = s.getRangeAt(0).cloneRange();
            queueToolState();
        }
    });

    function restoreSel() {
        if (document.activeElement !== editor) {
            editor.focus({ preventScroll: true });
            if (savedRange) {
                const s = window.getSelection();
                s.removeAllRanges();
                s.addRange(savedRange);
            }
        }
    }

    function closestInEditor(selector) {
        const s = window.getSelection();
        if (!s.rangeCount) return null;
        let n = s.anchorNode;
        if (!n || !editor.contains(n)) return null;
        if (n.nodeType === 3) n = n.parentElement;
        const hit = n && n.closest(selector);
        return hit && editor.contains(hit) && hit !== editor ? hit : null;
    }

    function topBlock(node) {
        if (!node || node === editor || !editor.contains(node)) return null;
        while (node && node.parentNode !== editor) node = node.parentNode;
        return node || null;
    }

    function setCaretAtStart(node) {
        const r = document.createRange();
        r.selectNodeContents(node);
        r.collapse(true);
        const s = window.getSelection();
        s.removeAllRanges();
        s.addRange(r);
    }
    function setCaretAtEnd(node) {
        const r = document.createRange();
        r.selectNodeContents(node);
        r.collapse(false);
        const s = window.getSelection();
        s.removeAllRanges();
        s.addRange(r);
    }

    function caretAtStart(el) {
        const s = window.getSelection();
        if (!s.rangeCount || !s.isCollapsed || !el.contains(s.anchorNode)) return false;
        const r = document.createRange();
        r.selectNodeContents(el);
        r.setEnd(s.anchorNode, s.anchorOffset);
        return r.toString() === '' && !r.cloneContents().querySelector('img');
    }

    /* =====================================================
       Dirty tracking, autosave
       ===================================================== */
    function setStatus(text, kind) {
        const el = $('qn-status');
        el.textContent = text;
        el.className = 'qn-status' + (kind ? ' ' + kind : '');
    }

    function markDirty() {
        if (!state.current) return;
        state.dirty = true;
        state.rev++;
        setStatus('Unsaved changes', 'dirty');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(saveNow, 900);
        scheduleStats();
    }

    function removeClass(el, cls) {
        el.classList.remove(cls);
        if (!el.getAttribute('class')) el.removeAttribute('class');
    }

    function serialize() {
        editor.querySelectorAll('.qn-check input').forEach((i) => {
            if (i.checked) i.setAttribute('checked', ''); else i.removeAttribute('checked');
        });
        const clone = editor.cloneNode(true);
        clone.querySelectorAll('.qn-sel, .qn-flash').forEach((el) => { removeClass(el, 'qn-sel'); removeClass(el, 'qn-flash'); });
        return clone.innerHTML;
    }

    function previewText() {
        return (editor.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 200);
    }

    function saveNow() {
        clearTimeout(saveTimer);
        saveChain = saveChain.then(doSave);
        return saveChain;
    }

    async function doSave() {
        if (!state.current || !state.dirty) return;
        const note = state.current;
        const myRev = state.rev;
        const title = $('qn-title').value.trim();
        const preview = previewText();
        setStatus('Saving…');
        const res = await api('save_note', { note_id: note.id, title, content: serialize(), preview });
        if (res.error) {
            setStatus('Not saved', 'error');
            toast(res.error);
            return;
        }
        const item = state.notes.find((n) => n.id === note.id);
        if (item) { item.title = title; item.preview = preview; item.ts = Date.now(); sortNotes(); renderList(); }
        if (state.current === note && state.rev === myRev) {
            state.dirty = false;
            setStatus('Saved', '');
        }
    }

    function flushSave() {
        return state.dirty ? saveNow() : saveChain;
    }

    window.addEventListener('beforeunload', (e) => {
        if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    /* =====================================================
       Notes list (sidebar)
       ===================================================== */
    const toNote = (r) => ({
        id: Number(r.id),
        title: r.title || '',
        preview: r.preview || '',
        ts: new Date(String(r.updated_at || '').replace(' ', 'T')).getTime() || Date.now(),
    });
    const sortNotes = () => state.notes.sort((a, b) => b.ts - a.ts);

    function timeAgo(ts) {
        const m = Math.max(0, Math.floor((Date.now() - ts) / 60000));
        if (m < 1) return 'Just now';
        if (m < 60) return m + ' min ago';
        const h = Math.floor(m / 60);
        if (h < 24) return h + ' h ago';
        return new Date(ts).toLocaleDateString(undefined, { day: 'numeric', month: 'short' });
    }

    function renderList() {
        const list = $('qn-list');
        const q = $('qn-search').value.trim().toLowerCase();
        list.innerHTML = '';
        const shown = state.notes.filter((n) => !q || n.title.toLowerCase().includes(q) || n.preview.toLowerCase().includes(q));
        if (!shown.length) {
            list.innerHTML = '<p class="qn-list-empty">' + (q ? 'No notes match your search.' : 'No notes yet.') + '</p>';
            return;
        }
        shown.forEach((n) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'qn-item' + (state.current && state.current.id === n.id ? ' active' : '');
            const t = document.createElement('div');
            t.className = 'qn-item-title' + (n.title ? '' : ' untitled');
            t.textContent = n.title || 'Untitled note';
            const p = document.createElement('div');
            p.className = 'qn-item-preview';
            p.textContent = n.preview || 'Empty note';
            const d = document.createElement('div');
            d.className = 'qn-item-time';
            d.textContent = timeAgo(n.ts);
            b.append(t, p, d);
            b.addEventListener('click', () => openNote(n.id));
            list.appendChild(b);
        });
    }

    $('qn-search').addEventListener('input', renderList);
    $('qn-side-toggle').addEventListener('click', () => document.body.classList.toggle('qn-side-off'));
    if (window.innerWidth < 800) document.body.classList.add('qn-side-off');

    /* =====================================================
       Open / create / duplicate / delete notes
       ===================================================== */
    function showNote(res) {
        state.current = { id: Number(res.id) };
        $('qn-title').value = res.title || '';
        editor.innerHTML = sanitize(res.content || '') || '<p><br></p>';
        state.dirty = false;
        state.rev = 0;
        selectImage(null);
        scroll.scrollTop = 0;
        setStatus('Saved', '');
        renderList();
        updateStats();
        try { localStorage.setItem('qn_last', String(state.current.id)); } catch (_) { /* ignore */ }
    }

    async function openNote(id) {
        if (state.current && state.current.id === id) return;
        await flushSave();
        const res = await api('get_note', { note_id: id }, 'GET');
        if (res.error) return toast(res.error);
        showNote(res);
        if (window.innerWidth < 800) document.body.classList.add('qn-side-off');
    }

    async function newNote() {
        await flushSave();
        const res = await api('create_note', { title: '' }, 'POST');
        if (res.error) return toast(res.error);
        state.notes.unshift(toNote(res));
        showNote(res);
        $('qn-title').focus();
    }

    async function duplicateNote() {
        if (!state.current) return;
        await flushSave();
        const res = await api('duplicate_note', { note_id: state.current.id });
        if (res.error) return toast(res.error);
        state.notes.unshift(toNote(res));
        sortNotes();
        showNote(res);
    }

    async function deleteNote() {
        if (!state.current) return;
        const id = state.current.id;
        const item = state.notes.find((n) => n.id === id);
        const ok = await confirmDialog({
            title: `Delete \u201C${(item && item.title) || 'Untitled note'}\u201D?`,
            message: 'This permanently deletes the note and everything in it.',
            confirmLabel: 'Delete note',
        });
        if (!ok) return;
        const res = await api('delete_note', { note_id: id });
        if (res.error) return toast(res.error);
        state.dirty = false;
        clearTimeout(saveTimer);
        state.notes = state.notes.filter((n) => n.id !== id);
        state.current = null;
        if (state.notes.length) {
            const r = await api('get_note', { note_id: state.notes[0].id }, 'GET');
            if (!r.error) { showNote(r); return; }
        }
        await newNote();
    }

    $('qn-new').addEventListener('click', newNote);
    $('qn-save').addEventListener('click', async () => { await saveNow(); if (!state.dirty) setStatus('Saved', ''); });
    $('qn-title').addEventListener('input', () => {
        const item = state.current && state.notes.find((n) => n.id === state.current.id);
        if (item) { item.title = $('qn-title').value; renderList(); }
        markDirty();
    });
    $('qn-title').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); editor.focus(); }
    });

    /* =====================================================
       Ribbon tabs, popovers
       ===================================================== */
    document.querySelectorAll('.qn-tab').forEach((tab) => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.qn-tab').forEach((t) => t.classList.toggle('active', t === tab));
            document.querySelectorAll('.qn-panel').forEach((p) => { p.hidden = p.dataset.panel !== tab.dataset.tab; });
        });
    });

    const popHooks = {
        'qn-pop-link': () => {
            const a = closestInEditor('a');
            $('qn-link-url').value = a ? a.getAttribute('href') || '' : '';
            setTimeout(() => $('qn-link-url').focus(), 0);
        },
        'qn-pop-table': () => setTimeout(() => $('qn-rows').select(), 0),
        'qn-pop-goto': () => { gotoActive = 0; $('qn-goto-input').value = ''; renderGoto(); setTimeout(() => $('qn-goto-input').focus(), 0); },
    };
    let openPopId = null;

    function closePops() {
        document.querySelectorAll('.qn-pop').forEach((p) => { p.hidden = true; });
        openPopId = null;
    }

    function openPop(id, anchor, dir) {
        closePops();
        const pop = $(id);
        pop.hidden = false;
        openPopId = id;
        if (popHooks[id]) popHooks[id]();
        const a = anchor.getBoundingClientRect();
        const w = pop.offsetWidth;
        const h = pop.offsetHeight;
        const left = clamp(a.left, 8, window.innerWidth - w - 8);
        const top = clamp(dir === 'up' ? a.top - h - 8 : a.bottom + 6, 8, window.innerHeight - h - 8);
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
    }

    document.addEventListener('mousedown', (e) => {
        if (!e.target.closest('.qn-pop') && !e.target.closest('[data-pop]')) closePops();
    });

    // keep the text selection alive when clicking toolbar / popover buttons
    $('qn-root').addEventListener('mousedown', (e) => {
        if (e.target.closest('.qn-tb, .qn-ft, .qn-pop button, .qn-imgbar button')) e.preventDefault();
    });

    $('qn-root').addEventListener('click', (e) => {
        const popBtn = e.target.closest('[data-pop]');
        if (popBtn) {
            const id = popBtn.dataset.pop;
            if (openPopId === id) closePops();
            else openPop(id, popBtn, popBtn.dataset.dir || 'down');
            return;
        }
        const act = e.target.closest('[data-act]');
        if (act && !act.disabled) { runAction(act.dataset.act); return; }
    });

    /* =====================================================
       Commands
       ===================================================== */
    function afterEdit() {
        editor.focus({ preventScroll: true });
        markDirty();
        queueToolState();
        positionImgUI();
    }

    function cmd(name, value = null) {
        restoreSel();
        document.execCommand('styleWithCSS', false, true);
        document.execCommand(name, false, value);
        afterEdit();
    }

    function runAction(act) {
        if (EXEC_ACTS.has(act)) { cmd(act); return; }
        switch (act) {
            case 'clear':
                restoreSel();
                document.execCommand('removeFormat');
                setBlock('p');
                break;
            case 'checklist': insertChecklist(); break;
            case 'heading-cycle': {
                restoreSel();
                const h = closestInEditor('h1,h2,h3');
                const level = h ? Number(h.tagName[1]) : 0;
                setBlock(['p', 'h1', 'h2', 'h3'][(level + 1) % 4]);
                break;
            }
            case 'image': $('qn-file').click(); break;
            case 'hr': restoreSel(); document.execCommand('insertHTML', false, '<hr><p><br></p>'); afterEdit(); break;
            case 'quote': setBlock('blockquote'); break;
            case 'datetime': cmd('insertText', new Date().toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' })); break;
            case 'table-row': tableAddRow(); break;
            case 'table-col': tableAddCol(); break;
            case 'table-del': tableDelete(); break;
            default: break;
        }
    }

    function unwrap(el) {
        const parent = el.parentNode;
        while (el.firstChild) parent.insertBefore(el.firstChild, el);
        el.remove();
    }

    function setBlock(tag) {
        restoreSel();
        const bq = closestInEditor('blockquote');
        if (tag === 'blockquote') {
            if (bq) unwrap(bq);
            else document.execCommand('formatBlock', false, '<blockquote>');
        } else {
            if (bq) unwrap(bq);
            document.execCommand('formatBlock', false, '<' + tag + '>');
        }
        normalizeTop();
        afterEdit();
    }

    // wrap loose text / inline nodes that ended up directly inside the editor
    function normalizeTop() {
        const inline = /^(SPAN|A|B|I|U|S|STRONG|EM|FONT|SUB|SUP|CODE|IMG)$/;
        [...editor.childNodes].forEach((n) => {
            const loose = (n.nodeType === 3 && n.nodeValue.trim() !== '') || (n.nodeType === 1 && inline.test(n.tagName));
            if (!loose) return;
            const p = document.createElement('p');
            n.replaceWith(p);
            p.appendChild(n);
        });
        if (!editor.firstChild) editor.innerHTML = '<p><br></p>';
    }

    /* ---------- font family / size, colors ---------- */
    $('qn-style').addEventListener('change', (e) => setBlock(e.target.value));
    $('qn-font').addEventListener('change', (e) => cmd('fontName', e.target.value));
    $('qn-size').addEventListener('change', (e) => applyFontSize(Number(e.target.value)));

    function applyFontSize(px) {
        lastSizePx = px;
        restoreSel();
        document.execCommand('styleWithCSS', false, false);
        document.execCommand('fontSize', false, '7');
        document.execCommand('styleWithCSS', false, true);
        fixFontTags();
        afterEdit();
    }

    function fixFontTags() {
        editor.querySelectorAll('font[size="7"]').forEach((f) => {
            const span = document.createElement('span');
            span.style.fontSize = lastSizePx + 'px';
            while (f.firstChild) span.appendChild(f.firstChild);
            span.querySelectorAll('[style*="font-size"]').forEach((d) => d.style.removeProperty('font-size'));
            f.replaceWith(span);
        });
    }

    function buildSwatches(containerId, colors, onPick, isHighlight) {
        const box = $(containerId);
        box.innerHTML = '';
        colors.forEach((c) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'qn-sw' + (c === 'none' ? ' none' : '');
            if (c !== 'none') b.style.setProperty('--c', c);
            b.title = c === 'none' ? 'No highlight' : (c === DEFAULT_TEXT && !isHighlight ? 'Default' : c);
            b.addEventListener('click', () => onPick(c));
            box.appendChild(b);
        });
    }

    function applyTextColor(c) {
        closePops();
        $('qn-fc-btn').style.setProperty('--bar', c);
        cmd('foreColor', c);
    }
    function applyHighlight(c) {
        closePops();
        $('qn-hl-btn').style.setProperty('--bar', c === 'none' ? 'transparent' : c);
        cmd('hiliteColor', c === 'none' ? 'transparent' : c);
    }
    buildSwatches('qn-fc-swatches', TEXT_COLORS, applyTextColor, false);
    buildSwatches('qn-hl-swatches', HIGHLIGHTS, applyHighlight, true);
    $('qn-fc-custom').addEventListener('change', (e) => applyTextColor(e.target.value));
    $('qn-hl-custom').addEventListener('change', (e) => applyHighlight(e.target.value));
    $('qn-fc-btn').style.setProperty('--bar', DEFAULT_TEXT);
    $('qn-hl-btn').style.setProperty('--bar', '#ffd93d');

    /* ---------- links ---------- */
    function applyLink() {
        let url = $('qn-link-url').value.trim();
        if (!url) return;
        if (!/^(https?:|mailto:)/i.test(url)) {
            url = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(url) ? 'mailto:' + url : 'https://' + url;
        }
        closePops();
        restoreSel();
        const s = window.getSelection();
        if (s.isCollapsed) document.execCommand('insertHTML', false, `<a href="${escHtml(url)}">${escHtml(url)}</a>`);
        else document.execCommand('createLink', false, url);
        afterEdit();
    }
    $('qn-link-apply').addEventListener('click', applyLink);
    $('qn-link-url').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); applyLink(); } });
    $('qn-link-remove').addEventListener('click', () => { closePops(); cmd('unlink'); });

    editor.addEventListener('click', (e) => {
        const a = e.target.closest('a');
        if (a && (e.ctrlKey || e.metaKey)) window.open(a.href, '_blank', 'noopener');
    });

    /* ---------- tables ---------- */
    $('qn-table-apply').addEventListener('click', () => {
        const rows = clamp(parseInt($('qn-rows').value, 10) || 3, 1, 20);
        const cols = clamp(parseInt($('qn-cols').value, 10) || 3, 1, 10);
        const row = '<tr>' + '<td><br></td>'.repeat(cols) + '</tr>';
        closePops();
        restoreSel();
        document.execCommand('insertHTML', false, '<table><tbody>' + row.repeat(rows) + '</tbody></table><p><br></p>');
        afterEdit();
    });

    function tableAddRow() {
        const cell = closestInEditor('td,th');
        if (!cell) return null;
        const tr = cell.parentElement;
        const nr = tr.cloneNode(false);
        [...tr.children].forEach(() => { const td = document.createElement('td'); td.innerHTML = '<br>'; nr.appendChild(td); });
        tr.after(nr);
        markDirty();
        return nr;
    }
    function tableAddCol() {
        const cell = closestInEditor('td,th');
        if (!cell) return;
        const idx = cell.cellIndex;
        cell.closest('table').querySelectorAll('tr').forEach((row) => { row.insertCell(idx + 1).innerHTML = '<br>'; });
        markDirty();
    }
    function tableDelete() {
        const table = closestInEditor('table');
        if (!table) return;
        table.remove();
        normalizeTop();
        markDirty();
        queueToolState();
    }
    function moveCell(cell, back) {
        const cells = [...cell.closest('table').querySelectorAll('td,th')];
        let next = cells[cells.indexOf(cell) + (back ? -1 : 1)];
        if (!next && !back) {
            const nr = tableAddRow();
            next = nr && nr.firstElementChild;
        }
        if (next) setCaretAtEnd(next);
    }

    /* =====================================================
       Checklist lines
       ===================================================== */
    function makeCheckLine(html, checked) {
        const line = document.createElement('div');
        line.className = 'qn-check';
        const box = document.createElement('input');
        box.type = 'checkbox';
        box.setAttribute('contenteditable', 'false');
        if (checked) box.checked = true;
        const span = document.createElement('span');
        span.className = 'qn-check-text';
        span.innerHTML = html || '<br>';
        line.append(box, span);
        return line;
    }

    function insertChecklist() {
        restoreSel();
        const existing = closestInEditor('.qn-check');
        if (existing) { uncheckLine(existing); return; }
        const block = topBlock(window.getSelection().anchorNode);
        const line = makeCheckLine('', false);
        if (!block) {
            editor.appendChild(line);
        } else if (/^(P|DIV|H1|H2|H3|BLOCKQUOTE)$/.test(block.tagName)) {
            const html = block.innerHTML.trim();
            if (html && html !== '<br>') line.querySelector('.qn-check-text').innerHTML = html;
            block.replaceWith(line);
        } else {
            block.after(line);
        }
        setCaretAtEnd(line.querySelector('.qn-check-text'));
        afterEdit();
    }

    function uncheckLine(line) {
        const p = document.createElement('p');
        p.innerHTML = line.querySelector('.qn-check-text').innerHTML || '<br>';
        line.replaceWith(p);
        setCaretAtStart(p);
        afterEdit();
    }

    function checkEnter(line) {
        const span = line.querySelector('.qn-check-text');
        if (!span.textContent.trim() && !span.querySelector('img')) {
            const p = document.createElement('p');
            p.innerHTML = '<br>';
            line.replaceWith(p);
            setCaretAtStart(p);
            markDirty();
            return;
        }
        const next = makeCheckLine('', false);
        const s = window.getSelection();
        if (s.rangeCount && span.contains(s.anchorNode)) {
            const r = s.getRangeAt(0);
            r.deleteContents();
            const tail = document.createRange();
            tail.setStart(r.endContainer, r.endOffset);
            tail.setEnd(span, span.childNodes.length);
            const frag = tail.extractContents();
            const tspan = next.querySelector('.qn-check-text');
            tspan.innerHTML = '';
            tspan.appendChild(frag);
            if (!tspan.textContent && !tspan.querySelector('img')) tspan.innerHTML = '<br>';
            if (!span.textContent && !span.querySelector('br') && !span.querySelector('img')) span.innerHTML = '<br>';
        }
        line.after(next);
        setCaretAtStart(next.querySelector('.qn-check-text'));
        markDirty();
    }

    editor.addEventListener('change', (e) => {
        if (e.target.matches && e.target.matches('.qn-check input')) markDirty();
    });

    /* =====================================================
       Editor events
       ===================================================== */
    editor.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            const line = closestInEditor('.qn-check');
            if (line) { e.preventDefault(); checkEnter(line); return; }
        }
        if (e.key === 'Backspace') {
            const line = closestInEditor('.qn-check');
            if (line && caretAtStart(line.querySelector('.qn-check-text'))) { e.preventDefault(); uncheckLine(line); return; }
        }
        if (e.key === 'Tab') {
            e.preventDefault();
            const cell = closestInEditor('td,th');
            if (cell) { moveCell(cell, e.shiftKey); return; }
            if (closestInEditor('li')) document.execCommand(e.shiftKey ? 'outdent' : 'indent');
            else if (!e.shiftKey) document.execCommand('insertText', false, '\u00a0\u00a0\u00a0\u00a0');
            markDirty();
        }
    });

    editor.addEventListener('input', (e) => {
        fixFontTags();
        if (!editor.firstChild || editor.innerHTML === '<br>') {
            editor.innerHTML = '<p><br></p>';
            setCaretAtStart(editor.firstChild);
        }
        markdownShortcut(e);
        markDirty();
        positionImgUI();
    });

    // typing "# ", "- ", "1. " or "[] " at the start of a line converts it
    function markdownShortcut(e) {
        if (e.inputType !== 'insertText' || e.data !== ' ') return;
        const s = window.getSelection();
        if (!s.rangeCount || !s.isCollapsed) return;
        const block = topBlock(s.anchorNode);
        if (!block || !/^(P|DIV)$/.test(block.tagName) || block.classList.contains('qn-check')) return;
        const t = block.textContent.replace(/\u00a0/g, ' ');
        let m;
        const clearBlock = () => { block.innerHTML = '<br>'; setCaretAtStart(block); };
        if ((m = t.match(/^(#{1,3}) $/))) { clearBlock(); document.execCommand('formatBlock', false, '<h' + m[1].length + '>'); }
        else if (t === '- ' || t === '* ') { clearBlock(); document.execCommand('insertUnorderedList'); }
        else if (t === '1. ') { clearBlock(); document.execCommand('insertOrderedList'); }
        else if (t === '[] ' || t === '[ ] ') {
            const line = makeCheckLine('', false);
            block.replaceWith(line);
            setCaretAtStart(line.querySelector('.qn-check-text'));
        }
    }

    // pasted content is inserted as plain text (keeps colors predictable); images are embedded
    editor.addEventListener('paste', (e) => {
        const files = [...(e.clipboardData.files || [])].filter((f) => f.type.startsWith('image/'));
        if (files.length) { e.preventDefault(); insertImages(files); return; }
        e.preventDefault();
        const text = e.clipboardData.getData('text/plain');
        if (text) document.execCommand('insertText', false, text);
    });

    editor.addEventListener('dragover', (e) => {
        if (e.dataTransfer && [...e.dataTransfer.types].includes('Files')) e.preventDefault();
    });
    editor.addEventListener('drop', (e) => {
        const files = [...(e.dataTransfer.files || [])].filter((f) => f.type.startsWith('image/'));
        if (!files.length) return;
        e.preventDefault();
        let range = null;
        if (document.caretRangeFromPoint) range = document.caretRangeFromPoint(e.clientX, e.clientY);
        else if (document.caretPositionFromPoint) {
            const pos = document.caretPositionFromPoint(e.clientX, e.clientY);
            if (pos) { range = document.createRange(); range.setStart(pos.offsetNode, pos.offset); range.collapse(true); }
        }
        if (range) { savedRange = range; const s = window.getSelection(); s.removeAllRanges(); s.addRange(range); }
        insertImages(files);
    });

    /* =====================================================
       Images: insert (downscaled), select, resize
       ===================================================== */
    const readAsDataURL = (file) => new Promise((res, rej) => {
        const r = new FileReader();
        r.onload = () => res(r.result);
        r.onerror = () => rej(new Error('Could not read the image'));
        r.readAsDataURL(file);
    });
    const loadImage = (url) => new Promise((res, rej) => {
        const img = new Image();
        img.onload = () => res(img);
        img.onerror = () => rej(new Error('That image could not be opened'));
        img.src = url;
    });

    async function imageToDataUrl(file) {
        if (file.size > 12 * 1024 * 1024) throw new Error('Images must be under 12 MB');
        if (file.type === 'image/gif' || file.type === 'image/svg+xml') {
            if (file.size > 2 * 1024 * 1024) throw new Error('GIF and SVG images must be under 2 MB');
            return readAsDataURL(file);
        }
        const url = URL.createObjectURL(file);
        try {
            const img = await loadImage(url);
            const scale = Math.min(1, 1400 / img.naturalWidth);
            const w = Math.max(1, Math.round(img.naturalWidth * scale));
            const h = Math.max(1, Math.round(img.naturalHeight * scale));
            const c = document.createElement('canvas');
            c.width = w;
            c.height = h;
            const ctx = c.getContext('2d');
            const png = file.type === 'image/png';
            if (!png) { ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, w, h); }
            ctx.drawImage(img, 0, 0, w, h);
            return png ? c.toDataURL('image/png') : c.toDataURL('image/jpeg', 0.85);
        } finally {
            URL.revokeObjectURL(url);
        }
    }

    async function insertImages(files) {
        for (const f of files) {
            try {
                const dataUrl = await imageToDataUrl(f);
                restoreSel();
                document.execCommand('insertImage', false, dataUrl);
                const img = [...editor.querySelectorAll('img')].find((i) => i.getAttribute('src') === dataUrl);
                afterEdit();
                if (img) selectImage(img);
            } catch (err) {
                toast(err.message || 'Could not insert that image');
            }
        }
    }

    $('qn-file').addEventListener('change', (e) => {
        const files = [...e.target.files];
        e.target.value = '';
        if (files.length) insertImages(files);
    });

    const imgUi = $('qn-imgui');

    function selectImage(img) {
        if (selImg && selImg !== img) removeClass(selImg, 'qn-sel');
        selImg = img;
        if (img) img.classList.add('qn-sel');
        positionImgUI();
    }

    function positionImgUI() {
        if (!selImg || !editor.contains(selImg)) { imgUi.hidden = true; selImg = null; return; }
        const r = selImg.getBoundingClientRect();
        const c = scroll.getBoundingClientRect();
        imgUi.hidden = false;
        imgUi.style.left = (r.left - c.left + scroll.scrollLeft) + 'px';
        imgUi.style.top = (r.top - c.top + scroll.scrollTop) + 'px';
        imgUi.style.width = r.width + 'px';
        imgUi.style.height = r.height + 'px';
    }

    editor.addEventListener('click', (e) => {
        if (e.target.tagName === 'IMG') selectImage(e.target);
        else selectImage(null);
    });
    editor.addEventListener('load', positionImgUI, true);
    window.addEventListener('resize', positionImgUI);

    imgUi.querySelector('.qn-imgbar').addEventListener('click', (e) => {
        const b = e.target.closest('button');
        if (!b || !selImg) return;
        if (b.dataset.w === 'del') {
            selImg.remove();
            selectImage(null);
        } else {
            selImg.style.width = b.dataset.w + '%';
            selImg.style.height = 'auto';
        }
        markDirty();
        positionImgUI();
    });

    $('qn-imghandle').addEventListener('pointerdown', (e) => {
        if (!selImg) return;
        e.preventDefault();
        const handle = e.currentTarget;
        try { handle.setPointerCapture(e.pointerId); } catch (_) { /* ignore */ }
        const startX = e.clientX;
        const startW = selImg.getBoundingClientRect().width;
        const maxW = editor.clientWidth - 92;
        const move = (ev) => {
            selImg.style.width = clamp(startW + (ev.clientX - startX), 40, Math.max(120, maxW)) + 'px';
            selImg.style.height = 'auto';
            positionImgUI();
        };
        const up = () => {
            handle.removeEventListener('pointermove', move);
            handle.removeEventListener('pointerup', up);
            handle.removeEventListener('pointercancel', up);
            markDirty();
        };
        handle.addEventListener('pointermove', move);
        handle.addEventListener('pointerup', up);
        handle.addEventListener('pointercancel', up);
    });

    /* =====================================================
       Tool state (active buttons, style select, table tools)
       ===================================================== */
    function queueToolState() {
        if (toolRaf) return;
        toolRaf = true;
        requestAnimationFrame(updateToolState);
    }

    function updateToolState() {
        toolRaf = false;
        document.querySelectorAll('[data-act]').forEach((b) => {
            const a = b.dataset.act;
            if (EXEC_ACTS.has(a) && a !== 'undo' && a !== 'redo') {
                let on = false;
                try { on = document.queryCommandState(a); } catch (_) { /* ignore */ }
                b.classList.toggle('active', on);
            }
        });
        const heading = closestInEditor('h1,h2,h3');
        document.querySelectorAll('[data-act="heading-cycle"]').forEach((b) => b.classList.toggle('active', !!heading));
        let value = 'p';
        if (heading) value = heading.tagName.toLowerCase();
        else if (closestInEditor('blockquote')) value = 'blockquote';
        $('qn-style').value = value;

        const inTable = !!closestInEditor('td,th');
        document.querySelectorAll('[data-act^="table-"]').forEach((b) => { b.disabled = !inTable; });
    }

    /* =====================================================
       Stats
       ===================================================== */
    function scheduleStats() {
        clearTimeout(statsTimer);
        statsTimer = setTimeout(updateStats, 150);
    }
    function updateStats() {
        const text = (editor.innerText || '').trim();
        const words = text ? text.split(/\s+/).length : 0;
        $('qn-words').textContent = words + (words === 1 ? ' word' : ' words');
        const chars = text.replace(/\n/g, '').length;
        $('qn-chars').textContent = chars + (chars === 1 ? ' character' : ' characters');
        const empty = !editor.textContent.trim() && !editor.querySelector('img,table,hr,.qn-check,ul,ol');
        editor.classList.toggle('is-empty', empty);
    }

    /* =====================================================
       Go to heading  (Ctrl + /)
       ===================================================== */
    let gotoItems = [];
    let gotoActive = 0;

    function renderGoto() {
        const q = $('qn-goto-input').value.trim().toLowerCase();
        const list = $('qn-goto-list');
        list.innerHTML = '';
        const heads = [...editor.querySelectorAll('h1,h2,h3')];
        gotoItems = heads
            .map((h) => ({ h, text: h.textContent.trim() || '(empty heading)', level: Number(h.tagName[1]) }))
            .filter((i) => i.text.toLowerCase().includes(q));
        if (!heads.length) {
            list.innerHTML = '<p class="qn-pop-empty">No headings yet. Press H in the footer to turn a line into one.</p>';
            return;
        }
        if (!gotoItems.length) {
            list.innerHTML = '<p class="qn-pop-empty">No headings match.</p>';
            return;
        }
        gotoActive = clamp(gotoActive, 0, gotoItems.length - 1);
        gotoItems.forEach((it, i) => {
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'qn-goto-item l' + it.level + (i === gotoActive ? ' active' : '');
            b.textContent = it.text;
            b.addEventListener('click', () => goToHeading(it.h));
            list.appendChild(b);
        });
    }

    function goToHeading(h) {
        closePops();
        const top = h.getBoundingClientRect().top - scroll.getBoundingClientRect().top + scroll.scrollTop - 24;
        scroll.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        h.classList.remove('qn-flash');
        void h.offsetWidth;
        h.classList.add('qn-flash');
        setTimeout(() => removeClass(h, 'qn-flash'), 1400);
        editor.focus({ preventScroll: true });
        setCaretAtEnd(h);
    }

    $('qn-goto-input').addEventListener('input', () => { gotoActive = 0; renderGoto(); });
    $('qn-goto-input').addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!gotoItems.length) return;
            gotoActive = (gotoActive + (e.key === 'ArrowDown' ? 1 : -1) + gotoItems.length) % gotoItems.length;
            renderGoto();
            const act = $('qn-goto-list').querySelector('.active');
            if (act) act.scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (gotoItems[gotoActive]) goToHeading(gotoItems[gotoActive].h);
        }
    });

    /* =====================================================
       File menu: save, duplicate, export, print, delete
       ===================================================== */
    function fileName(ext) {
        const base = ($('qn-title').value || 'note').replace(/[\\/:*?"<>|]+/g, '_').trim().slice(0, 80) || 'note';
        return base + '.' + ext;
    }

    function download(name, mime, text) {
        const blob = new Blob([text], { type: mime + ';charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1500);
    }

    function exportHtml() {
        const title = escHtml($('qn-title').value || 'Untitled note');
        const css = 'body{margin:0;background:#2b2b2b;color:#e8e8e8;font:16px/1.6 Arial,sans-serif}' +
            '.note{max-width:820px;margin:0 auto;padding:40px 24px}h1,h2,h3{color:#fff}img{max-width:100%;height:auto}' +
            'a{color:#5ab0ff}blockquote{border-left:4px solid #00bc00;margin:.6em 0;padding:2px 0 2px 16px;color:#ccc}' +
            'table{width:100%;border-collapse:collapse}td,th{border:1px solid #666;padding:6px 10px;vertical-align:top}' +
            'hr{border:none;border-top:1px solid #666}.qn-check{display:flex;gap:10px}.qn-check-text{flex:1}';
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' + title + '</title><style>' + css +
            '</style></head><body><div class="note"><h1>' + title + '</h1>' + serialize() + '</div></body></html>';
    }

    function exportText() {
        const holder = document.createElement('div');
        holder.innerHTML = serialize();
        const out = [];
        const walk = (node) => {
            node.childNodes.forEach((n) => {
                if (n.nodeType === 3) { out.push(n.nodeValue.replace(/\u00a0/g, ' ')); return; }
                if (n.nodeType !== 1) return;
                const t = n.tagName;
                if (t === 'BR') { out.push('\n'); return; }
                if (t === 'IMG') { out.push('[image]'); return; }
                if (t === 'HR') { out.push('\n----------\n'); return; }
                if (t === 'INPUT') { out.push(n.hasAttribute('checked') ? '[x] ' : '[ ] '); return; }
                if (t === 'TR') { out.push([...n.children].map((c) => c.textContent.trim()).join('\t') + '\n'); return; }
                if (t === 'LI') {
                    const p = n.parentElement;
                    out.push(p && p.tagName === 'OL' ? (Array.prototype.indexOf.call(p.children, n) + 1) + '. ' : '\u2022 ');
                    walk(n);
                    out.push('\n');
                    return;
                }
                walk(n);
                if (/^(P|DIV|H1|H2|H3|H4|BLOCKQUOTE|PRE)$/.test(t)) out.push('\n');
            });
        };
        walk(holder);
        const title = $('qn-title').value.trim();
        return (title ? title + '\n' + '='.repeat(Math.min(title.length, 60)) + '\n\n' : '') +
            out.join('').replace(/\n{3,}/g, '\n\n').trim() + '\n';
    }

    $('qn-pop-file').addEventListener('click', async (e) => {
        const b = e.target.closest('[data-file]');
        if (!b) return;
        closePops();
        switch (b.dataset.file) {
            case 'new': newNote(); break;
            case 'save': await saveNow(); if (!state.dirty) setStatus('Saved', ''); break;
            case 'duplicate': duplicateNote(); break;
            case 'html': download(fileName('html'), 'text/html', exportHtml()); break;
            case 'txt': download(fileName('txt'), 'text/plain', exportText()); break;
            case 'print': window.print(); break;
            case 'delete': deleteNote(); break;
            default: break;
        }
    });

    /* =====================================================
       Global shortcuts
       ===================================================== */
    document.addEventListener('keydown', (e) => {
        const mod = e.ctrlKey || e.metaKey;
        if (mod && e.key.toLowerCase() === 's') {
            e.preventDefault();
            saveNow().then(() => { if (!state.dirty) setStatus('Saved', ''); });
        } else if (mod && e.key === '/') {
            e.preventDefault();
            openPop('qn-pop-goto', $('qn-goto-btn'), 'up');
        } else if (e.key === 'Escape') {
            if ($('qn-modal').classList.contains('open')) { $('qn-confirm-cancel').click(); return; }
            if (openPopId) { closePops(); editor.focus({ preventScroll: true }); return; }
            if (selImg) selectImage(null);
        }
    });

    /* =====================================================
       Init
       ===================================================== */
    async function init() {
        document.execCommand('defaultParagraphSeparator', false, 'p');
        document.execCommand('styleWithCSS', false, true);
        editor.innerHTML = '<p><br></p>';
        updateStats();

        const list = await api('list_notes', {}, 'GET');
        if (!Array.isArray(list)) { toast((list && list.error) || 'Could not load your notes'); return; }
        state.notes = list.map(toNote);
        sortNotes();
        renderList();

        if (!state.notes.length) { await newNote(); editor.focus(); return; }
        let id = state.notes[0].id;
        try {
            const last = Number(localStorage.getItem('qn_last'));
            if (last && state.notes.some((n) => n.id === last)) id = last;
        } catch (_) { /* ignore */ }
        const res = await api('get_note', { note_id: id }, 'GET');
        if (res.error) return toast(res.error);
        showNote(res);
    }

    init();
})();
