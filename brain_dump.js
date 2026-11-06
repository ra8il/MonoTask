(function () {
  "use strict";

  /* =====================================================
       Constants
       ===================================================== */
  const API = "brain_dump_api.php";
  const SVG_NS = "http://www.w3.org/2000/svg";

  const GAP = 14; // spacing between snapped / stacked nodes
  const SNAP_PX = 18; // magnet distance (screen px)
  const REACH_PX = 26; // how far outside a node still counts as "on" it when linking (screen px)
  const ARROW = 9; // arrowhead length
  const GRID = 24; // background dot spacing
  const MIN_K = 0.3;
  const MAX_K = 2;

  const DEFAULT_COLOR = "#8a8a8a";
  const PALETTE = [
    { hex: "#8a8a8a", name: "Grey" },
    { hex: "#00bc00", name: "Green" },
    { hex: "#3b9dff", name: "Blue" },
    { hex: "#ff9f43", name: "Orange" },
    { hex: "#ff4444", name: "Red" },
    { hex: "#a463f2", name: "Purple" },
    { hex: "#00d2c6", name: "Teal" },
    { hex: "#ffd93d", name: "Yellow" },
  ];
  const SIDES = ["top", "right", "bottom", "left"];
  const NORMAL = {
    top: { x: 0, y: -1 },
    right: { x: 1, y: 0 },
    bottom: { x: 0, y: 1 },
    left: { x: -1, y: 0 },
  };

  /* =====================================================
       State + tiny helpers
       ===================================================== */
  const $ = (id) => document.getElementById(id);
  const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
  const plural = (n, one, many) => (n === 1 ? one : many);
  const safeColor = (c) =>
    /^#[0-9a-f]{6}$/i.test(c || "") ? c : DEFAULT_COLOR;

  const view = { x: 0, y: 0, k: 1 };
  const state = { pit: null, nodes: [], conns: [], newColor: DEFAULT_COLOR };
  const nodeEls = new Map(); // node id -> element
  const lineEls = new Map(); // connection id -> svg parts

  const byId = (id) => state.nodes.find((n) => n.id === id);

  const vp = $("bd-canvas-viewport");
  const nodesLayer = $("bd-nodes");
  const linesSvg = $("bd-lines");

  const ro = new ResizeObserver(() => scheduleLines()); // hover-expand etc. changes node size

  async function api(action, params = {}, method = "POST") {
    const body = new URLSearchParams({ action, ...params });
    const opts = { method, credentials: "same-origin" };
    let url = API;
    if (method === "GET") url += "?" + body.toString();
    else opts.body = body;

    let res, data;
    try {
      res = await fetch(url, opts);
      data = await res.json();
    } catch (err) {
      return { error: "Could not reach the server" };
    }
    if (res.status === 401) {
      window.location.href = "login.php";
      return { error: "Signed out" };
    }
    if (!res.ok && !data.error) data.error = "Request failed";
    return data;
  }

  let toastTimer;
  function toast(msg) {
    const t = $("bd-toast");
    t.textContent = msg;
    t.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => t.classList.remove("show"), 3200);
  }

  function formatTime(s) {
    if (!s) return "";
    const d = new Date(String(s).replace(" ", "T"));
    if (isNaN(d)) return "";
    return (
      "Added " +
      d.toLocaleString(undefined, {
        day: "numeric",
        month: "short",
        hour: "2-digit",
        minute: "2-digit",
      })
    );
  }

  const normalizeNode = (r) => ({
    id: Number(r.id),
    heading: r.heading,
    content: r.content || "",
    color: safeColor(r.color),
    x: Number(r.pos_x) || 0,
    y: Number(r.pos_y) || 0,
    created_at: r.created_at,
  });

  const normalizeConn = (r) => ({
    id: Number(r.id),
    from: Number(r.from_node_id),
    fromSide: r.from_side,
    to: Number(r.to_node_id),
    toSide: r.to_side,
  });

  /* =====================================================
       Modals, swatches, confirm
       ===================================================== */
  const openModal = (id) => {
    const m = $(id);
    m.classList.add("open");
    m.setAttribute("aria-hidden", "false");
  };
  const closeModal = (id) => {
    const m = $(id);
    m.classList.remove("open");
    m.setAttribute("aria-hidden", "true");
  };

  document.addEventListener("click", (e) => {
    const b = e.target.closest("[data-cancel]");
    if (!b || b.id === "bd-confirm-cancel") return;
    closeModal(b.closest(".bd-modal").id);
  });
  document.querySelectorAll(".bd-modal").forEach((m) => {
    m.addEventListener("pointerdown", (e) => {
      if (e.target === m) m.querySelector("[data-cancel]").click();
    });
  });
  document.addEventListener("keydown", (e) => {
    if (e.key !== "Escape") return;
    const open = document.querySelector(".bd-modal.open");
    if (open) open.querySelector("[data-cancel]").click();
  });

  function buildSwatches(container, selected, onPick) {
    container.innerHTML = "";
    PALETTE.forEach((c) => {
      const b = document.createElement("button");
      b.type = "button";
      b.className = "bd-swatch" + (c.hex === selected ? " selected" : "");
      b.style.setProperty("--c", c.hex);
      b.title = c.name;
      b.setAttribute("aria-label", c.name);
      b.addEventListener("click", () => {
        container
          .querySelectorAll(".bd-swatch")
          .forEach((s) => s.classList.toggle("selected", s === b));
        onPick(c.hex);
      });
      container.appendChild(b);
    });
  }

  function confirmDialog({ title, message, confirmLabel = "Confirm" }) {
    return new Promise((resolve) => {
      const ok = $("bd-confirm-ok");
      const cancel = $("bd-confirm-cancel");
      $("bd-confirm-title").textContent = title;
      $("bd-confirm-msg").textContent = message;
      ok.textContent = confirmLabel;
      const done = (v) => {
        ok.onclick = null;
        cancel.onclick = null;
        closeModal("bd-modal-confirm");
        resolve(v);
      };
      ok.onclick = () => done(true);
      cancel.onclick = () => done(false);
      openModal("bd-modal-confirm");
      cancel.focus();
    });
  }

  /* =====================================================
       Thought pit list
       ===================================================== */
  async function loadPits() {
    const grid = $("bd-pit-grid");
    const data = await api("list_pits", {}, "GET");
    grid.innerHTML = "";
    if (!Array.isArray(data)) {
      grid.innerHTML =
        '<p class="bd-empty-list">Could not load your thought pits.</p>';
      if (data && data.error) toast(data.error);
      return;
    }
    if (!data.length) {
      grid.innerHTML =
        '<p class="bd-empty-list">No thought pits yet. Create one to start dumping ideas.</p>';
      return;
    }
    data.forEach((p) => {
      const count = Number(p.node_count);
      const card = document.createElement("div");
      card.className = "bd-pit-card";
      card.tabIndex = 0;
      card.setAttribute("role", "button");
      card.style.setProperty("--c", safeColor(p.accent_color));
      card.innerHTML =
        '<button type="button" class="bd-pit-delete" title="Delete thought pit" aria-label="Delete thought pit">&#10005;</button>' +
        '<h3></h3><span class="bd-pit-count"></span>';
      card.querySelector("h3").textContent = p.name;
      card.querySelector(".bd-pit-count").textContent =
        `${count} ${plural(count, "node", "nodes")}`;

      card.addEventListener("click", (e) => {
        if (e.target.closest(".bd-pit-delete")) return;
        openPit(p);
      });
      card.addEventListener("keydown", (e) => {
        if (e.key === "Enter") openPit(p);
      });
      card
        .querySelector(".bd-pit-delete")
        .addEventListener("click", async (e) => {
          e.stopPropagation();
          const ok = await confirmDialog({
            title: `Delete \u201C${p.name}\u201D?`,
            message:
              "This deletes the thought pit together with all of its nodes and connections. It cannot be undone.",
            confirmLabel: "Delete thought pit",
          });
          if (!ok) return;
          const res = await api("delete_pit", { pit_id: p.id });
          if (res.error) return toast(res.error);
          loadPits();
        });
      grid.appendChild(card);
    });
  }

  let pitColor = "#00bc00";
  $("bd-new-pit-btn").addEventListener("click", () => {
    $("bd-pit-name").value = "";
    pitColor = "#00bc00";
    buildSwatches($("bd-pit-swatches"), pitColor, (c) => {
      pitColor = c;
    });
    openModal("bd-modal-pit");
    $("bd-pit-name").focus();
  });
  $("bd-pit-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const name = $("bd-pit-name").value.trim();
    if (!name) return;
    const res = await api("create_pit", { name, accent_color: pitColor });
    if (res.error) return toast(res.error);
    closeModal("bd-modal-pit");
    openPit(res);
  });

  $("bd-back-btn").addEventListener("click", backToList);
  function backToList() {
    state.pit = null;
    resetBoard();
    $("bd-canvas-view").hidden = true;
    $("bd-pit-view").hidden = false;
    loadPits();
  }

  /* =====================================================
       Open a pit / board lifecycle
       ===================================================== */
  async function openPit(p) {
    state.pit = {
      id: Number(p.id),
      name: p.name,
      accent: safeColor(p.accent_color),
    };
    $("bd-pit-view").hidden = true;
    $("bd-canvas-view").hidden = false;
    $("bd-pit-title").textContent = p.name;
    $("bd-pit-title").style.setProperty("--c", state.pit.accent);
    resetBoard();
    updateCounter();
    setView({ k: 1, x: vp.clientWidth / 2 - 100, y: 90 }, false);

    const data = await api("get_pit", { pit_id: p.id }, "GET");
    if (!state.pit || state.pit.id !== Number(p.id)) return; // user navigated away meanwhile
    if (data.error) {
      toast(data.error);
      backToList();
      return;
    }

    state.nodes = data.nodes.map(normalizeNode);
    state.conns = data.connections.map(normalizeConn);
    state.nodes.forEach((n) => {
      mountNode(n);
      placeNode(n);
    });
    syncLines();
    updateCounter();
    fitView(false, 0.55);
    $("bd-add-heading").focus();
  }

  function resetBoard() {
    ro.disconnect();
    nodeEls.clear();
    lineEls.clear();
    nodesLayer.innerHTML = "";
    linesSvg.innerHTML = "";
    showGuides([]);
    state.nodes = [];
    state.conns = [];
  }

  function updateCounter() {
    const nn = state.nodes.length;
    const cc = state.conns.length;
    $("bd-node-count").textContent = nn;
    $("bd-node-label").textContent = plural(nn, "node", "nodes");
    $("bd-conn-count").textContent = cc;
    $("bd-conn-label").textContent = plural(cc, "connection", "connections");
    $("bd-reset").disabled = cc === 0;
    $("bd-tidy").disabled = nn === 0;
    $("bd-flush").disabled = nn === 0;
    $("bd-empty").hidden = nn !== 0;
  }

  /* =====================================================
       Nodes: DOM
       ===================================================== */
  function mountNode(n) {
    const el = document.createElement("div");
    el.className = "bd-node";
    el.dataset.id = n.id;
    el.innerHTML =
      '<div class="bd-tools">' +
      '<button type="button" class="bd-tool" data-act="edit" title="Edit node" aria-label="Edit node">&#9998;</button>' +
      '<button type="button" class="bd-tool" data-act="delete" title="Delete node" aria-label="Delete node">&#10005;</button>' +
      "</div>" +
      '<div class="bd-node-head"></div>' +
      '<div class="bd-node-body"><div class="bd-node-text"></div><div class="bd-node-time"></div></div>' +
      SIDES.map(
        (s) =>
          `<div class="bd-port bd-port-${s}" data-side="${s}" title="Drag to connect"></div>`,
      ).join("");
    nodesLayer.appendChild(el);
    nodeEls.set(n.id, el);
    paintNode(n);
    ro.observe(el);
    return el;
  }

  function paintNode(n) {
    const el = nodeEls.get(n.id);
    if (!el) return;
    const hasContent = n.content.trim() !== "";
    el.style.setProperty("--c", n.color);
    el.classList.toggle("has-content", hasContent);
    el.querySelector(".bd-node-head").textContent = n.heading;
    el.querySelector(".bd-node-text").textContent = hasContent ? n.content : "";
    el.querySelector(".bd-node-time").textContent = formatTime(n.created_at);
  }

  function placeNode(n) {
    const el = nodeEls.get(n.id);
    if (el)
      el.style.transform = `translate(${Math.round(n.x)}px, ${Math.round(n.y)}px)`;
  }

  function sizeOf(n) {
    const el = nodeEls.get(n.id);
    return el ? { w: el.offsetWidth, h: el.offsetHeight } : { w: 150, h: 40 };
  }

  function removeNodeLocal(id) {
    const el = nodeEls.get(id);
    if (el) {
      ro.unobserve(el);
      el.remove();
    }
    nodeEls.delete(id);
    state.nodes = state.nodes.filter((n) => n.id !== id);
    state.conns = state.conns.filter((c) => c.from !== id && c.to !== id);
    syncLines();
    updateCounter();
  }

  function savePositions(list) {
    const payload = list.map((n) => ({
      id: n.id,
      x: Math.round(n.x),
      y: Math.round(n.y),
    }));
    return api("update_positions", { positions: JSON.stringify(payload) }).then(
      (r) => {
        if (r.error) toast("Could not save node positions");
      },
    );
  }

  /* =====================================================
       Default layout: vertical stack
       ===================================================== */
  function overlaps(x, y, w, h, o) {
    const s = sizeOf(o);
    return x < o.x + s.w && x + w > o.x && y < o.y + s.h && y + h > o.y;
  }

  // Below the most recently created node; pushed further down if something is in the way.
  function stackPosition(forNode) {
    const others = state.nodes.filter((o) => o !== forNode);
    if (!others.length) return { x: 0, y: 0 };
    const last = others.reduce((a, b) => (b.id > a.id ? b : a));
    const me = sizeOf(forNode);
    const x = last.x;
    let y = last.y + sizeOf(last).h + GAP;
    let guard = 0;
    let hit;
    while (
      (hit = others.find((o) => overlaps(x, y, me.w, me.h, o))) &&
      guard++ < 300
    ) {
      y = hit.y + sizeOf(hit).h + GAP;
    }
    return { x, y };
  }

  $("bd-add-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    if (!state.pit) return;
    const headingInput = $("bd-add-heading");
    const heading = headingInput.value.trim();
    if (!heading) {
      headingInput.focus();
      return;
    }
    const content = $("bd-add-content").value.trim();

    const btn = $("bd-add-btn");
    btn.disabled = true;
    const res = await api("create_node", {
      pit_id: state.pit.id,
      heading,
      content,
      color: state.newColor,
      pos_x: 0,
      pos_y: 0,
    });
    btn.disabled = false;
    if (res.error) return toast(res.error);

    const n = normalizeNode(res);
    state.nodes.push(n);
    mountNode(n); // measured in the same frame, so there is no flash at 0,0
    const pos = stackPosition(n);
    n.x = pos.x;
    n.y = pos.y;
    placeNode(n);
    savePositions([n]);

    headingInput.value = "";
    $("bd-add-content").value = "";
    headingInput.focus();
    updateCounter();
    revealNode(n);
  });

  buildSwatches($("bd-add-swatches"), state.newColor, (c) => {
    state.newColor = c;
  });

  /* =====================================================
       Node interactions (delegated)
       ===================================================== */
  nodesLayer.addEventListener("pointerdown", (e) => {
    const el = e.target.closest(".bd-node");
    if (!el || e.button !== 0) return;
    const n = byId(Number(el.dataset.id));
    if (!n) return;
    e.stopPropagation();
    stopViewAnim();

    const port = e.target.closest(".bd-port");
    if (port) {
      e.preventDefault();
      startConnect(e, n, el, port.dataset.side);
      return;
    }
    if (e.target.closest(".bd-tools")) return;
    startDrag(e, n, el);
  });

  nodesLayer.addEventListener("click", (e) => {
    const tool = e.target.closest(".bd-tool");
    if (!tool) return;
    const n = byId(Number(tool.closest(".bd-node").dataset.id));
    if (!n) return;
    if (tool.dataset.act === "edit") openEdit(n);
    else deleteNode(n);
  });

  nodesLayer.addEventListener("dblclick", (e) => {
    if (e.target.closest(".bd-tools, .bd-port")) return;
    const el = e.target.closest(".bd-node");
    if (!el) return;
    const n = byId(Number(el.dataset.id));
    if (n) openEdit(n);
  });

  /* ---------- edit / delete ---------- */
  let editingId = null;
  let editColor = DEFAULT_COLOR;

  function openEdit(n) {
    editingId = n.id;
    editColor = n.color;
    $("bd-edit-heading").value = n.heading;
    $("bd-edit-content").value = n.content;
    buildSwatches($("bd-edit-swatches"), n.color, (c) => {
      editColor = c;
    });
    openModal("bd-modal-node");
    $("bd-edit-heading").focus();
  }

  $("bd-edit-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const n = byId(editingId);
    if (!n) return closeModal("bd-modal-node");
    const heading = $("bd-edit-heading").value.trim();
    if (!heading) return;
    const res = await api("update_node", {
      node_id: n.id,
      heading,
      content: $("bd-edit-content").value.trim(),
      color: editColor,
    });
    if (res.error) return toast(res.error);
    n.heading = res.heading;
    n.content = res.content || "";
    n.color = safeColor(res.color);
    paintNode(n);
    updateLines();
    closeModal("bd-modal-node");
  });

  $("bd-edit-delete").addEventListener("click", () => {
    const n = byId(editingId);
    closeModal("bd-modal-node");
    if (n) deleteNode(n);
  });

  async function deleteNode(n) {
    const links = state.conns.filter(
      (c) => c.from === n.id || c.to === n.id,
    ).length;
    const ok = await confirmDialog({
      title: `Delete \u201C${n.heading}\u201D?`,
      message: links
        ? `The ${links} ${plural(links, "connection", "connections")} attached to this node will be removed too.`
        : "This node will be removed from the thought pit.",
      confirmLabel: "Delete node",
    });
    if (!ok) return;
    const res = await api("delete_node", { node_id: n.id });
    if (res.error) return toast(res.error);
    removeNodeLocal(n.id);
  }

  /* =====================================================
       Dragging + magnetic snapping
       ===================================================== */
  function closest(v, list, T) {
    let best = null;
    let bd = T;
    list.forEach((c) => {
      const d = Math.abs(v - c);
      if (d < bd) {
        bd = d;
        best = c;
      }
    });
    return best;
  }

  // Pulls the dragged rect against the closest facing edge of any neighbour and lines up the other axis.
  function magnet(n, w, h, x, y) {
    const T = SNAP_PX / view.k;
    let vy = null;
    let vx = null;

    for (const o of state.nodes) {
      if (o === n) continue;
      const s = sizeOf(o);

      if (x < o.x + s.w + T && x + w > o.x - T) {
        // horizontally related -> stack
        const below = o.y + s.h + GAP;
        const above = o.y - GAP - h;
        const dB = Math.abs(y - below);
        const dA = Math.abs(y - above);
        if (dB < T && (!vy || dB < vy.d))
          vy = { d: dB, v: below, o, s, below: true };
        if (dA < T && (!vy || dA < vy.d))
          vy = { d: dA, v: above, o, s, below: false };
      }
      if (y < o.y + s.h + T && y + h > o.y - T) {
        // vertically related -> dock sideways
        const right = o.x + s.w + GAP;
        const left = o.x - GAP - w;
        const dR = Math.abs(x - right);
        const dL = Math.abs(x - left);
        if (dR < T && (!vx || dR < vx.d))
          vx = { d: dR, v: right, o, s, right: true };
        if (dL < T && (!vx || dL < vx.d))
          vx = { d: dL, v: left, o, s, right: false };
      }
    }

    let nx = x;
    let ny = y;
    if (vy && vx) {
      ny = vy.v;
      nx = vx.v;
    } else if (vy) {
      ny = vy.v;
      const a = closest(
        x,
        [vy.o.x, vy.o.x + vy.s.w - w, vy.o.x + (vy.s.w - w) / 2],
        T,
      );
      if (a !== null) nx = a;
    } else if (vx) {
      nx = vx.v;
      const a = closest(
        y,
        [vx.o.y, vx.o.y + vx.s.h - h, vx.o.y + (vx.s.h - h) / 2],
        T,
      );
      if (a !== null) ny = a;
    }

    const guides = [];
    if (vy) {
      const gx = Math.max(nx, vy.o.x);
      const gx2 = Math.min(nx + w, vy.o.x + vy.s.w);
      guides.push({
        x: gx,
        y: vy.below ? vy.o.y + vy.s.h : ny + h,
        w: Math.max(gx2 - gx, 10),
        h: GAP,
      });
    }
    if (vx) {
      const gy = Math.max(ny, vx.o.y);
      const gy2 = Math.min(ny + h, vx.o.y + vx.s.h);
      guides.push({
        x: vx.right ? vx.o.x + vx.s.w : nx + w,
        y: gy,
        w: GAP,
        h: Math.max(gy2 - gy, 10),
      });
    }
    return { x: Math.round(nx), y: Math.round(ny), guides };
  }

  const guideEls = [0, 1].map(() => {
    const g = document.createElement("div");
    g.className = "bd-guide";
    $("bd-guides").appendChild(g);
    return g;
  });
  function showGuides(list) {
    guideEls.forEach((g, i) => {
      const r = list[i];
      if (!r) {
        g.style.display = "none";
        return;
      }
      g.style.display = "block";
      g.style.transform = `translate(${r.x}px, ${r.y}px)`;
      g.style.width = r.w + "px";
      g.style.height = r.h + "px";
    });
  }

  function startDrag(e, n, el) {
    e.preventDefault();
    try {
      el.setPointerCapture(e.pointerId);
    } catch (_) {
      /* ignore */
    }
    const sx = e.clientX;
    const sy = e.clientY;
    const ox = n.x;
    const oy = n.y;
    let moved = false;

    const move = (ev) => {
      const dx = ev.clientX - sx;
      const dy = ev.clientY - sy;
      if (!moved) {
        if (Math.hypot(dx, dy) < 4) return;
        moved = true;
        el.classList.add("bd-pinned", "bd-dragging");
      }
      let x = ox + dx / view.k;
      let y = oy + dy / view.k;
      if (ev.altKey) {
        showGuides([]);
      } else {
        const m = magnet(n, el.offsetWidth, el.offsetHeight, x, y);
        x = m.x;
        y = m.y;
        showGuides(m.guides);
      }
      n.x = Math.round(x);
      n.y = Math.round(y);
      placeNode(n);
      scheduleLines();
    };
    const end = () => {
      window.removeEventListener("pointermove", move);
      window.removeEventListener("pointerup", end);
      window.removeEventListener("pointercancel", end);
      el.classList.remove("bd-pinned", "bd-dragging");
      showGuides([]);
      if (moved) savePositions([n]);
    };
    window.addEventListener("pointermove", move);
    window.addEventListener("pointerup", end);
    window.addEventListener("pointercancel", end);
  }

  /* =====================================================
       Links (smooth bezier curves, arrow at the target port)
       ===================================================== */
  function portPoint(n, side) {
    const { w, h } = sizeOf(n);
    if (side === "top") return { x: n.x + w / 2, y: n.y };
    if (side === "bottom") return { x: n.x + w / 2, y: n.y + h };
    if (side === "left") return { x: n.x, y: n.y + h / 2 };
    return { x: n.x + w, y: n.y + h / 2 };
  }

  function curve(p0, s0, p1, s1, withArrow) {
    const n0 = NORMAL[s0];
    const n1 = s1 ? NORMAL[s1] : null;
    const end =
      withArrow && n1 ? { x: p1.x + n1.x * ARROW, y: p1.y + n1.y * ARROW } : p1;
    const off = clamp(Math.hypot(end.x - p0.x, end.y - p0.y) * 0.5, 36, 200);
    const c0 = { x: p0.x + n0.x * off, y: p0.y + n0.y * off };
    const c1 = n1 ? { x: end.x + n1.x * off, y: end.y + n1.y * off } : end;

    const d = `M${p0.x},${p0.y} C${c0.x},${c0.y} ${c1.x},${c1.y} ${end.x},${end.y}`;
    let arrowD = "";
    if (withArrow && n1) {
      const px = -n1.y;
      const py = n1.x;
      arrowD = `M${p1.x},${p1.y} L${end.x + px * 5},${end.y + py * 5} L${end.x - px * 5},${end.y - py * 5} Z`;
    }
    const mid = {
      x: (p0.x + 3 * c0.x + 3 * c1.x + end.x) / 8,
      y: (p0.y + 3 * c0.y + 3 * c1.y + end.y) / 8,
    };
    return { d, arrowD, mid };
  }

  function svgEl(name, cls) {
    const e = document.createElementNS(SVG_NS, name);
    if (cls) e.setAttribute("class", cls);
    return e;
  }

  function makeLine(c) {
    const g = svgEl("g", "bd-link");
    const hit = svgEl("path", "bd-link-hit");
    const line = svgEl("path", "bd-link-line");
    const arrow = svgEl("path", "bd-link-arrow");
    const del = svgEl("g", "bd-link-del");
    del.dataset.id = c.id;
    const circle = svgEl("circle");
    circle.setAttribute("r", 9);
    const x = svgEl("path");
    x.setAttribute("d", "M-3.5,-3.5 L3.5,3.5 M3.5,-3.5 L-3.5,3.5");
    const title = svgEl("title");
    title.textContent = "Remove connection";
    del.append(circle, x, title);
    g.append(hit, line, arrow, del);
    linesSvg.appendChild(g);
    lineEls.set(c.id, { g, hit, line, arrow, del });
  }

  function syncLines() {
    const ids = new Set(state.conns.map((c) => c.id));
    for (const [id, parts] of lineEls) {
      if (!ids.has(id)) {
        parts.g.remove();
        lineEls.delete(id);
      }
    }
    state.conns.forEach((c) => {
      if (!lineEls.has(c.id)) makeLine(c);
    });
    updateLines();
  }

  function updateLines() {
    for (const c of state.conns) {
      const parts = lineEls.get(c.id);
      const a = byId(c.from);
      const b = byId(c.to);
      if (!parts || !a || !b) continue;
      const g = curve(
        portPoint(a, c.fromSide),
        c.fromSide,
        portPoint(b, c.toSide),
        c.toSide,
        true,
      );
      parts.hit.setAttribute("d", g.d);
      parts.line.setAttribute("d", g.d);
      parts.arrow.setAttribute("d", g.arrowD);
      parts.del.setAttribute("transform", `translate(${g.mid.x} ${g.mid.y})`);
      parts.g.style.setProperty("--lc", a.color);
    }
  }

  let linesQueued = false;
  function scheduleLines() {
    if (linesQueued) return;
    linesQueued = true;
    requestAnimationFrame(() => {
      linesQueued = false;
      updateLines();
    });
  }

  linesSvg.addEventListener("click", async (e) => {
    const del = e.target.closest(".bd-link-del");
    if (!del) return;
    const id = Number(del.dataset.id);
    const res = await api("delete_connection", { connection_id: id });
    if (res.error) return toast(res.error);
    state.conns = state.conns.filter((c) => c.id !== id);
    syncLines();
    updateCounter();
  });

  /* ---------- drag from a + port to another node ---------- */
  function toCanvas(cx, cy) {
    const r = vp.getBoundingClientRect();
    return {
      x: (cx - r.left - view.x) / view.k,
      y: (cy - r.top - view.y) / view.k,
    };
  }

  // Finds the node under (or just around) the pointer and the port side closest to it.
  function hitTest(p, source) {
    const R = REACH_PX / view.k;
    let best = null;
    for (const o of state.nodes) {
      if (o === source) continue;
      const { w, h } = sizeOf(o);
      if (
        p.x < o.x - R ||
        p.x > o.x + w + R ||
        p.y < o.y - R ||
        p.y > o.y + h + R
      )
        continue;
      let side = "left";
      let bd = Infinity;
      for (const s of SIDES) {
        const q = portPoint(o, s);
        const d = Math.hypot(q.x - p.x, q.y - p.y);
        if (d < bd) {
          bd = d;
          side = s;
        }
      }
      const dc = Math.hypot(o.x + w / 2 - p.x, o.y + h / 2 - p.y);
      if (!best || dc < best.dc) best = { node: o, side, dc };
    }
    return best;
  }

  let hotNodeEl = null;
  let hotPortEl = null;
  function setHot(hit) {
    const el = hit ? nodeEls.get(hit.node.id) : null;
    const port = hit ? el.querySelector(".bd-port-" + hit.side) : null;
    if (hotNodeEl && hotNodeEl !== el) hotNodeEl.classList.remove("bd-hot");
    if (hotPortEl && hotPortEl !== port)
      hotPortEl.classList.remove("bd-port-hot");
    if (el) el.classList.add("bd-hot");
    if (port) port.classList.add("bd-port-hot");
    hotNodeEl = el;
    hotPortEl = port;
  }

  function startConnect(e, n, el, side) {
    try {
      el.setPointerCapture(e.pointerId);
    } catch (_) {
      /* ignore */
    }
    const temp = svgEl("path", "bd-link-temp");
    temp.style.setProperty("--lc", n.color);
    linesSvg.appendChild(temp);
    vp.classList.add("bd-connecting");
    el.classList.add("bd-pinned");
    let hit = null;

    const draw = (ev) => {
      const p = toCanvas(ev.clientX, ev.clientY);
      hit = hitTest(p, n);
      setHot(hit);
      const end = hit ? portPoint(hit.node, hit.side) : p;
      temp.setAttribute(
        "d",
        curve(portPoint(n, side), side, end, hit ? hit.side : null, false).d,
      );
    };
    const finish = (commit) => {
      window.removeEventListener("pointermove", draw);
      window.removeEventListener("pointerup", onUp);
      window.removeEventListener("pointercancel", onCancel);
      window.removeEventListener("keydown", onKey);
      temp.remove();
      vp.classList.remove("bd-connecting");
      el.classList.remove("bd-pinned");
      setHot(null);
      if (commit && hit) createConnection(n, side, hit.node, hit.side);
    };
    const onUp = () => finish(true);
    const onCancel = () => finish(false);
    const onKey = (ev) => {
      if (ev.key === "Escape") finish(false);
    };

    window.addEventListener("pointermove", draw);
    window.addEventListener("pointerup", onUp);
    window.addEventListener("pointercancel", onCancel);
    window.addEventListener("keydown", onKey);
    draw(e);
  }

  async function createConnection(from, fromSide, to, toSide) {
    const dup = state.conns.some(
      (c) =>
        c.from === from.id &&
        c.fromSide === fromSide &&
        c.to === to.id &&
        c.toSide === toSide,
    );
    if (dup) return toast("Those two ports are already connected");
    const res = await api("create_connection", {
      pit_id: state.pit.id,
      from_node_id: from.id,
      from_side: fromSide,
      to_node_id: to.id,
      to_side: toSide,
    });
    if (res.error) return toast(res.error);
    state.conns.push(normalizeConn(res));
    syncLines();
    updateCounter();
  }

  /* =====================================================
       Pan / zoom / fit
       ===================================================== */
  let viewAnim = 0;
  const stopViewAnim = () => cancelAnimationFrame(viewAnim);

  function applyView() {
    $("bd-canvas-inner").style.transform =
      `translate(${view.x}px, ${view.y}px) scale(${view.k})`;
    const s = GRID * view.k;
    vp.style.backgroundSize = `${s}px ${s}px`;
    vp.style.backgroundPosition = `${view.x}px ${view.y}px`;
    $("bd-zoom-label").textContent = Math.round(view.k * 100) + "%";
  }

  function animateView(t, ms = 280) {
    stopViewAnim();
    const from = { x: view.x, y: view.y, k: view.k };
    const t0 = performance.now();
    const step = (now) => {
      const p = clamp((now - t0) / ms, 0, 1);
      const e = 1 - Math.pow(1 - p, 3);
      view.x = from.x + (t.x - from.x) * e;
      view.y = from.y + (t.y - from.y) * e;
      view.k = from.k + (t.k - from.k) * e;
      applyView();
      if (p < 1) viewAnim = requestAnimationFrame(step);
    };
    viewAnim = requestAnimationFrame(step);
  }

  function setView(t, animate) {
    if (animate) {
      animateView(t);
      return;
    }
    stopViewAnim();
    view.x = t.x;
    view.y = t.y;
    view.k = t.k;
    applyView();
  }

  function zoomAt(cx, cy, factor) {
    const k = clamp(view.k * factor, MIN_K, MAX_K);
    const wx = (cx - view.x) / view.k;
    const wy = (cy - view.y) / view.k;
    view.k = k;
    view.x = cx - wx * k;
    view.y = cy - wy * k;
    applyView();
  }

  function contentBox() {
    if (!state.nodes.length) return null;
    let x0 = Infinity,
      y0 = Infinity,
      x1 = -Infinity,
      y1 = -Infinity;
    state.nodes.forEach((n) => {
      const { w, h } = sizeOf(n);
      x0 = Math.min(x0, n.x);
      y0 = Math.min(y0, n.y);
      x1 = Math.max(x1, n.x + w);
      y1 = Math.max(y1, n.y + h);
    });
    return { x: x0, y: y0, w: x1 - x0, h: y1 - y0 };
  }

  function fitView(animate = true, minK = MIN_K) {
    const W = vp.clientWidth;
    const H = vp.clientHeight;
    const PAD = 60;
    const b = contentBox();
    if (!b) {
      setView({ k: 1, x: W / 2 - 100, y: 90 }, animate);
      return;
    }
    const k = clamp(
      Math.min((W - PAD * 2) / b.w, (H - PAD * 2) / b.h),
      minK,
      1,
    );
    const x = (W - b.w * k) / 2 - b.x * k;
    const y =
      b.h * k + PAD * 2 <= H ? (H - b.h * k) / 2 - b.y * k : PAD - b.y * k;
    setView({ k, x, y }, animate);
  }

  function revealNode(n) {
    const W = vp.clientWidth;
    const H = vp.clientHeight;
    const M = 50;
    const { w, h } = sizeOf(n);
    const sx = n.x * view.k + view.x;
    const sy = n.y * view.k + view.y;
    const sw = w * view.k;
    const sh = h * view.k;
    let dx = 0;
    let dy = 0;
    if (sx < M) dx = M - sx;
    else if (sx + sw > W - M) dx = W - M - (sx + sw);
    if (sy < M) dy = M - sy;
    else if (sy + sh > H - M) dy = H - M - (sy + sh);
    if (dx || dy) animateView({ x: view.x + dx, y: view.y + dy, k: view.k });
  }

  vp.addEventListener("pointerdown", (e) => {
    if (e.button !== 0 && e.button !== 1) return;
    if (e.target.closest(".bd-node, .bd-link-del")) return;
    stopViewAnim();
    e.preventDefault();
    let lx = e.clientX;
    let ly = e.clientY;
    vp.classList.add("bd-panning");
    try {
      vp.setPointerCapture(e.pointerId);
    } catch (_) {
      /* ignore */
    }
    const move = (ev) => {
      view.x += ev.clientX - lx;
      view.y += ev.clientY - ly;
      lx = ev.clientX;
      ly = ev.clientY;
      applyView();
    };
    const up = () => {
      vp.classList.remove("bd-panning");
      vp.removeEventListener("pointermove", move);
      vp.removeEventListener("pointerup", up);
      vp.removeEventListener("pointercancel", up);
    };
    vp.addEventListener("pointermove", move);
    vp.addEventListener("pointerup", up);
    vp.addEventListener("pointercancel", up);
  });

  // wheel / two-finger scroll pans; ctrl/cmd + wheel or trackpad pinch zooms at the cursor
  vp.addEventListener(
    "wheel",
    (e) => {
      const body = e.target.closest && e.target.closest(".bd-node-body");
      if (
        body &&
        !(e.ctrlKey || e.metaKey) &&
        body.scrollHeight > body.clientHeight
      )
        return; // let long node content scroll
      e.preventDefault();
      stopViewAnim();
      const r = vp.getBoundingClientRect();
      if (e.ctrlKey || e.metaKey) {
        const d = clamp(e.deltaY, -60, 60);
        zoomAt(e.clientX - r.left, e.clientY - r.top, Math.exp(-d * 0.004));
      } else {
        const swap = e.shiftKey && !e.deltaX;
        view.x -= swap ? e.deltaY : e.deltaX;
        view.y -= swap ? 0 : e.deltaY;
        applyView();
      }
    },
    { passive: false },
  );

  $("bd-zoom-in").addEventListener("click", () => {
    stopViewAnim();
    zoomAt(vp.clientWidth / 2, vp.clientHeight / 2, 1.2);
  });
  $("bd-zoom-out").addEventListener("click", () => {
    stopViewAnim();
    zoomAt(vp.clientWidth / 2, vp.clientHeight / 2, 1 / 1.2);
  });
  $("bd-fit").addEventListener("click", () => fitView(true));

  /* =====================================================
       Status bar actions
       ===================================================== */
  function animatePositions(targets, ms = 320) {
    const from = targets.map((t) => ({ x: t.n.x, y: t.n.y }));
    const t0 = performance.now();
    return new Promise((resolve) => {
      const step = (now) => {
        const p = clamp((now - t0) / ms, 0, 1);
        const e = 1 - Math.pow(1 - p, 3);
        targets.forEach((t, i) => {
          t.n.x = from[i].x + (t.x - from[i].x) * e;
          t.n.y = from[i].y + (t.y - from[i].y) * e;
          placeNode(t.n);
        });
        updateLines();
        if (p < 1) requestAnimationFrame(step);
        else resolve();
      };
      requestAnimationFrame(step);
    });
  }

  // Restack every node into one vertical list, oldest first.
  $("bd-tidy").addEventListener("click", async () => {
    if (!state.nodes.length) return;
    const sorted = [...state.nodes].sort((a, b) => a.id - b.id);
    let y = 0;
    const targets = sorted.map((n) => {
      const t = { n, x: 0, y };
      y += sizeOf(n).h + GAP;
      return t;
    });
    await animatePositions(targets);
    sorted.forEach((n, i) => {
      n.x = targets[i].x;
      n.y = targets[i].y;
      placeNode(n);
    });
    updateLines();
    savePositions(sorted);
    fitView(true, 0.55);
  });

  $("bd-reset").addEventListener("click", async () => {
    const cc = state.conns.length;
    if (!cc) return;
    const ok = await confirmDialog({
      title: "Remove all connections?",
      message: `This removes ${cc} ${plural(cc, "connection", "connections")}. Your nodes stay exactly where they are.`,
      confirmLabel: "Remove connections",
    });
    if (!ok) return;
    const res = await api("reset_connections", { pit_id: state.pit.id });
    if (res.error) return toast(res.error);
    state.conns = [];
    syncLines();
    updateCounter();
  });

  $("bd-flush").addEventListener("click", async () => {
    const nn = state.nodes.length;
    const cc = state.conns.length;
    if (!nn) return;
    const ok = await confirmDialog({
      title: `Delete everything in \u201C${state.pit.name}\u201D?`,
      message:
        `All ${nn} ${plural(nn, "node", "nodes")} and ${cc} ${plural(cc, "connection", "connections")} ` +
        "will be permanently removed. The thought pit itself stays, as a blank canvas.",
      confirmLabel: "Delete everything",
    });
    if (!ok) return;
    const res = await api("clear_pit", { pit_id: state.pit.id });
    if (res.error) return toast(res.error);
    resetBoard();
    updateCounter();
    fitView(true);
    $("bd-add-heading").focus();
  });

  /* =====================================================
       Init
       ===================================================== */
  applyView();
  loadPits();
})();
