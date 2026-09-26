(function () {
    const API = 'brain_dump_api.php';
    const svgNS = 'http://www.w3.org/2000/svg';

    const state = {
        currentBucket: null,
        nodes: [],
        connections: [],
        scale: 1,
        panX: 40,
        panY: 20,
        addType: 'info',
    };

    const el = (id) => document.getElementById(id);

    async function api(action, params = {}, method = 'POST') {
        if (method === 'GET') {
            const qs = new URLSearchParams({ action, ...params });
            const res = await fetch(`${API}?${qs.toString()}`);
            return res.json();
        }
        const body = new URLSearchParams({ action, ...params });
        const res = await fetch(API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body,
        });
        return res.json();
    }

    function escapeHtml(s) {
        const div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    // ---------------- Bucket grid ----------------

    async function loadBuckets() {
        const buckets = await api('list_buckets', {}, 'GET');
        const grid = el('bd-bucket-grid');
        grid.innerHTML = '';
        if (!Array.isArray(buckets) || buckets.length === 0) {
            grid.innerHTML = '<p class="bd-empty">No buckets yet — start your first brain dump.</p>';
            return;
        }
        buckets.forEach((b) => {
            const card = document.createElement('div');
            card.className = 'bd-bucket-card';
            card.style.setProperty('--accent', b.accent_color || '#00bc00');
            card.innerHTML = `
                <button class="bd-bucket-delete" title="Delete bucket">&times;</button>
                <h3>${escapeHtml(b.name)}</h3>
                <span class="bd-bucket-count">${b.node_count} node${b.node_count == 1 ? '' : 's'}</span>
            `;
            card.addEventListener('click', (e) => {
                if (e.target.closest('.bd-bucket-delete')) return;
                openBucket(b.id, b.name);
            });
            card.querySelector('.bd-bucket-delete').addEventListener('click', async (e) => {
                e.stopPropagation();
                if (!confirm(`Delete bucket "${b.name}" and everything in it?`)) return;
                await api('delete_bucket', { bucket_id: b.id });
                loadBuckets();
            });
            grid.appendChild(card);
        });
    }

    el('bd-new-bucket-btn').addEventListener('click', async () => {
        const name = prompt('Name this bucket (topic):');
        if (!name || !name.trim()) return;
        const palette = ['#00bc00', '#3b9dff', '#ff9f43', '#ff4444', '#a463f2', '#00d2c6'];
        const color = palette[Math.floor(Math.random() * palette.length)];
        await api('create_bucket', { name: name.trim(), accent_color: color });
        loadBuckets();
    });

    el('bd-back-btn').addEventListener('click', () => {
        el('bd-canvas-view').style.display = 'none';
        el('bd-bucket-view').style.display = 'block';
        loadBuckets();
    });

    // ---------------- Canvas / board ----------------

    async function openBucket(id, name) {
        state.currentBucket = id;
        state.scale = 1;
        state.panX = 40;
        state.panY = 20;
        el('bd-bucket-view').style.display = 'none';
        el('bd-canvas-view').style.display = 'flex';
        el('bd-canvas-title').textContent = name;
        applyTransform();
        await loadBoard();
    }

    async function loadBoard() {
        const data = await api('get_board', { bucket_id: state.currentBucket }, 'GET');
        state.nodes = data.nodes || [];
        state.connections = data.connections || [];
        layoutBands();
        renderBoard();
    }

    // -- time banding + scatter placement --

    let bandRanges = {};

    function dayKey(dateStr) {
        return (dateStr || '').slice(0, 10);
    }

    function dayLabel(key) {
        const now = new Date();
        const today = now.toISOString().slice(0, 10);
        const yesterday = new Date(now.getTime() - 86400000).toISOString().slice(0, 10);
        if (key === today) return 'Today';
        if (key === yesterday) return 'Yesterday';
        const d = new Date(key + 'T00:00:00');
        if (isNaN(d)) return key;
        return d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    }

    function seedRandom(seed) {
        const x = Math.sin(seed * 999) * 10000;
        return x - Math.floor(x);
    }

    function layoutBands() {
        const groups = {};
        state.nodes.forEach((n) => {
            const k = dayKey(n.created_at);
            (groups[k] = groups[k] || []).push(n);
        });
        const keys = Object.keys(groups).sort();
        bandRanges = {};
        let top = 20;
        const BAND_PAD = 40;

        keys.forEach((k) => {
            const group = groups[k];
            const rows = Math.max(1, Math.ceil(group.length / 6));
            const height = Math.max(180, rows * 110);
            bandRanges[k] = { top, height, label: dayLabel(k) };

            group.forEach((n, i) => {
                const isUnplaced = Number(n.pos_x) === 0 && Number(n.pos_y) === 0;
                if (isUnplaced) {
                    const col = i % 6;
                    const row = Math.floor(i / 6);
                    const jitterX = (seedRandom(n.id) - 0.5) * 60;
                    const jitterY = (seedRandom(n.id + 1) - 0.5) * 40;
                    n.pos_x = Math.round(60 + col * 190 + jitterX);
                    n.pos_y = Math.round(top + 50 + row * 100 + jitterY);
                    api('update_node_position', { node_id: n.id, pos_x: n.pos_x, pos_y: n.pos_y });
                }
            });
            top += height + BAND_PAD;
        });

        const totalHeight = Math.max(top, 600);
        el('bd-canvas-inner').style.height = totalHeight + 'px';
        el('bd-lines').setAttribute('height', totalHeight);
    }

    function renderBoard() {
        const bandsEl = el('bd-bands');
        bandsEl.innerHTML = '';
        Object.values(bandRanges).forEach((b) => {
            const div = document.createElement('div');
            div.className = 'bd-band';
            div.style.top = b.top + 'px';
            div.style.height = b.height + 'px';
            div.innerHTML = `<span class="bd-band-label">${escapeHtml(b.label)}</span>`;
            bandsEl.appendChild(div);
        });

        const nodesEl = el('bd-nodes');
        nodesEl.innerHTML = '';
        state.nodes.forEach((n) => nodesEl.appendChild(renderNode(n)));

        drawAllLines();
    }

    function renderNode(n) {
        const div = document.createElement('div');
        div.className = `bd-node bd-node-${n.type}`;
        div.dataset.id = n.id;
        div.style.left = n.pos_x + 'px';
        div.style.top = n.pos_y + 'px';
        div.innerHTML = `
            <span class="bd-node-text">${escapeHtml(n.content)}</span>
            <button class="bd-node-delete" title="Delete">&times;</button>
            <span class="bd-node-handle" title="Drag to connect"></span>
        `;

        div.addEventListener('pointerdown', (e) => {
            if (e.target.closest('.bd-node-handle') || e.target.closest('.bd-node-delete')) return;
            if (e.target.closest('.bd-node-text') && e.target.isContentEditable) return;
            startNodeDrag(e, n, div);
        });

        div.querySelector('.bd-node-delete').addEventListener('click', async (e) => {
            e.stopPropagation();
            await api('delete_node', { node_id: n.id });
            state.nodes = state.nodes.filter((x) => x.id !== n.id);
            state.connections = state.connections.filter(
                (c) => c.source_node_id !== n.id && c.target_node_id !== n.id
            );
            renderBoard();
        });

        const textEl = div.querySelector('.bd-node-text');
        textEl.addEventListener('dblclick', (e) => {
            e.stopPropagation();
            textEl.contentEditable = 'true';
            textEl.focus();
            document.execCommand('selectAll', false, null);
        });
        textEl.addEventListener('blur', async () => {
            textEl.contentEditable = 'false';
            const newContent = textEl.textContent.trim();
            if (newContent && newContent !== n.content) {
                n.content = newContent;
                await api('update_node_content', { node_id: n.id, content: newContent });
            } else {
                textEl.textContent = n.content;
            }
        });
        textEl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                textEl.blur();
            }
        });

        div.querySelector('.bd-node-handle').addEventListener('pointerdown', (e) => {
            e.stopPropagation();
            startConnectionDrag(e, n);
        });

        return div;
    }

    // -- dragging nodes --

    function startNodeDrag(e, n, div) {
        e.preventDefault();
        div.setPointerCapture(e.pointerId);
        const startX = e.clientX;
        const startY = e.clientY;
        const originX = n.pos_x;
        const originY = n.pos_y;
        div.classList.add('bd-dragging');

        function onMove(ev) {
            const dx = (ev.clientX - startX) / state.scale;
            const dy = (ev.clientY - startY) / state.scale;
            n.pos_x = originX + dx;
            n.pos_y = originY + dy;
            div.style.left = n.pos_x + 'px';
            div.style.top = n.pos_y + 'px';
            updateLinesForNode(n.id);
        }
        function onUp() {
            div.releasePointerCapture(e.pointerId);
            div.classList.remove('bd-dragging');
            div.removeEventListener('pointermove', onMove);
            div.removeEventListener('pointerup', onUp);
            api('update_node_position', {
                node_id: n.id,
                pos_x: Math.round(n.pos_x),
                pos_y: Math.round(n.pos_y),
            });
        }
        div.addEventListener('pointermove', onMove);
        div.addEventListener('pointerup', onUp);
    }

    // -- connections --

    function nodeCenter(id) {
        const n = state.nodes.find((x) => x.id === id);
        if (!n) return { x: 0, y: 0 };
        return { x: n.pos_x + 75, y: n.pos_y + 22 };
    }

    function drawAllLines() {
        const svg = el('bd-lines');
        svg.innerHTML = '';
        state.connections.forEach((c) => svg.appendChild(createLine(c)));
    }

    function createLine(c) {
        const line = document.createElementNS(svgNS, 'line');
        const a = nodeCenter(c.source_node_id);
        const b = nodeCenter(c.target_node_id);
        line.setAttribute('x1', a.x);
        line.setAttribute('y1', a.y);
        line.setAttribute('x2', b.x);
        line.setAttribute('y2', b.y);
        line.setAttribute('class', 'bd-link');
        line.dataset.id = c.id;
        line.addEventListener('click', async () => {
            if (!confirm('Remove this connection?')) return;
            await api('delete_connection', { connection_id: c.id });
            state.connections = state.connections.filter((x) => x.id !== c.id);
            drawAllLines();
        });
        return line;
    }

    function updateLinesForNode(nodeId) {
        const svg = el('bd-lines');
        state.connections.forEach((c) => {
            if (c.source_node_id !== nodeId && c.target_node_id !== nodeId) return;
            const lineEl = svg.querySelector(`line[data-id="${c.id}"]`);
            if (!lineEl) return;
            const a = nodeCenter(c.source_node_id);
            const b = nodeCenter(c.target_node_id);
            lineEl.setAttribute('x1', a.x);
            lineEl.setAttribute('y1', a.y);
            lineEl.setAttribute('x2', b.x);
            lineEl.setAttribute('y2', b.y);
        });
    }

    function startConnectionDrag(e, sourceNode) {
        const svg = el('bd-lines');
        const tempLine = document.createElementNS(svgNS, 'line');
        tempLine.setAttribute('class', 'bd-link bd-link-temp');
        const start = nodeCenter(sourceNode.id);
        tempLine.setAttribute('x1', start.x);
        tempLine.setAttribute('y1', start.y);
        tempLine.setAttribute('x2', start.x);
        tempLine.setAttribute('y2', start.y);
        svg.appendChild(tempLine);

        function toCanvasCoords(clientX, clientY) {
            const rect = el('bd-canvas-inner').getBoundingClientRect();
            return {
                x: (clientX - rect.left) / state.scale,
                y: (clientY - rect.top) / state.scale,
            };
        }

        function onMove(ev) {
            const p = toCanvasCoords(ev.clientX, ev.clientY);
            tempLine.setAttribute('x2', p.x);
            tempLine.setAttribute('y2', p.y);
        }

        async function onUp(ev) {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('pointerup', onUp);
            tempLine.remove();

            const target = document.elementFromPoint(ev.clientX, ev.clientY);
            const targetNodeEl = target && target.closest('.bd-node');
            if (!targetNodeEl) return;

            const targetId = parseInt(targetNodeEl.dataset.id, 10);
            if (targetId === sourceNode.id) return;

            const exists = state.connections.some(
                (c) =>
                    (c.source_node_id === sourceNode.id && c.target_node_id === targetId) ||
                    (c.source_node_id === targetId && c.target_node_id === sourceNode.id)
            );
            if (exists) return;

            const res = await api('create_connection', {
                bucket_id: state.currentBucket,
                source_node_id: sourceNode.id,
                target_node_id: targetId,
            });
            if (res && res.id) {
                state.connections.push(res);
                drawAllLines();
            }
        }

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
    }

    // -- adding nodes --

    el('bd-type-toggle').addEventListener('click', () => {
        state.addType = state.addType === 'info' ? 'heading' : 'info';
        const btn = el('bd-type-toggle');
        btn.dataset.type = state.addType;
        btn.textContent = state.addType === 'info' ? 'Note' : 'Heading';
    });

    async function addNode() {
        const input = el('bd-add-input');
        const content = input.value.trim();
        if (!content) return;
        input.value = '';
        const res = await api('create_node', {
            bucket_id: state.currentBucket,
            type: state.addType,
            content,
            pos_x: 0,
            pos_y: 0,
        });
        if (res && res.id) {
            state.nodes.push(res);
            layoutBands();
            renderBoard();
        }
    }
    el('bd-add-btn').addEventListener('click', addNode);
    el('bd-add-input').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') addNode();
    });

    // -- pan & zoom --

    function applyTransform() {
        el('bd-canvas-inner').style.transform = `translate(${state.panX}px, ${state.panY}px) scale(${state.scale})`;
    }

    function initPanZoom() {
        const vp = el('bd-canvas-viewport');
        let panning = false;
        let lastX = 0;
        let lastY = 0;

        vp.addEventListener('pointerdown', (e) => {
            if (e.target.closest('.bd-node')) return;
            panning = true;
            lastX = e.clientX;
            lastY = e.clientY;
            vp.setPointerCapture(e.pointerId);
        });
        vp.addEventListener('pointermove', (e) => {
            if (!panning) return;
            state.panX += e.clientX - lastX;
            state.panY += e.clientY - lastY;
            lastX = e.clientX;
            lastY = e.clientY;
            applyTransform();
        });
        vp.addEventListener('pointerup', () => {
            panning = false;
        });
        vp.addEventListener(
            'wheel',
            (e) => {
                e.preventDefault();
                const delta = -e.deltaY * 0.001;
                state.scale = Math.min(2, Math.max(0.4, state.scale + delta));
                applyTransform();
            },
            { passive: false }
        );
    }

    initPanZoom();
    loadBuckets();
})();
