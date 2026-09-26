// =====================
// EXPAND / COLLAPSE
// =====================
function toggleExpand(taskId) {
  const expanded = document.getElementById("expand-" + taskId);
  const arrow = document.getElementById("arrow-" + taskId);
  const isOpen = expanded.style.display === "block";

  document
    .querySelectorAll(".task-expanded")
    .forEach((el) => (el.style.display = "none"));
  document
    .querySelectorAll(".expand-arrow")
    .forEach((el) => (el.textContent = "▼"));

  if (!isOpen) {
    expanded.style.display = "block";
    arrow.textContent = "▲";
  }
}

// =====================
// SEARCH
// =====================
const searchInput = document.getElementById("searchInput");
if (searchInput) {
  let timer = null;
  searchInput.addEventListener("input", function () {
    clearTimeout(timer);
    const q = this.value.trim().toLowerCase();
    timer = setTimeout(() => {
      document.querySelectorAll(".task-card").forEach((card) => {
        const name = card.querySelector(".task-name-text");
        if (!name) return;
        const matches = name.textContent.toLowerCase().includes(q);
        card.style.display = q === "" || matches ? "flex" : "none";
      });
    }, 250);
  });
}

// =====================
// SORT
// =====================
function sortTasks(by) {
  const list = document.getElementById("taskList");
  if (!list) return;
  const cards = [...list.querySelectorAll(".task-card")];
  const order = { critical: 0, high: 1, medium: 2, low: 3 };

  cards.sort((a, b) => {
    if (by === "priority") {
      const pa = a.dataset.priority || "medium";
      const pb = b.dataset.priority || "medium";
      return (order[pa] ?? 2) - (order[pb] ?? 2);
    }
    if (by === "az") {
      const na = a.querySelector(".task-name-text")?.textContent.trim() || "";
      const nb = b.querySelector(".task-name-text")?.textContent.trim() || "";
      return na.localeCompare(nb);
    }
    if (by === "due_date") {
      const da = a.dataset.due || "zzz";
      const db = b.dataset.due || "zzz";
      return da.localeCompare(db);
    }
    return 0;
  });

  cards.forEach((c) => list.appendChild(c));
}

// =====================
// TOGGLE SECTIONS
// =====================
function toggleSection(listId, arrowId) {
  const list = document.getElementById(listId);
  const arrow = document.getElementById(arrowId);
  if (!list || !arrow) return;
  const open = list.style.display !== "none";
  list.style.display = open ? "none" : "flex";
  arrow.textContent = open ? "▶" : "▼";
}

// =====================
// DRAG AND DROP
// =====================
document.addEventListener("DOMContentLoaded", function () {
  const list = document.getElementById("taskList");
  if (!list || typeof Sortable === "undefined") return;

  Sortable.create(list, {
    handle: ".drag-handle",
    animation: 150,
    onEnd: function () {
      const order = [...list.querySelectorAll(".task-card")].map(
        (card) => card.dataset.id,
      );

      fetch("/Mini_Project/api/reorder.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ order: order }),
      });
    },
  });
});

// =====================
// TOAST
// =====================
function showToast(message, type) {
  type = type || "success";
  const toast = document.createElement("div");
  toast.className = "toast toast-" + type;
  toast.textContent = message;
  document.body.appendChild(toast);
  setTimeout(function () {
    toast.classList.add("show");
  }, 10);
  setTimeout(function () {
    toast.classList.remove("show");
    setTimeout(function () {
      toast.remove();
    }, 300);
  }, 3000);
}

// =====================
// RECURRING FORM
// =====================
function toggleRecurring(checkbox) {
  const panel = document.getElementById("recurringPanel");
  if (!panel) return;
  panel.classList.toggle("visible", checkbox.checked);
  if (checkbox.checked) updatePreview();
}

function onTypeChange(type) {
  ["rowWeekly", "rowMonthly", "rowCustom"].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.classList.remove("visible");
  });
  var target = document.getElementById(
    type === "weekly"
      ? "rowWeekly"
      : type === "monthly"
        ? "rowMonthly"
        : type === "custom"
          ? "rowCustom"
          : "",
  );
  if (target) target.classList.add("visible");
  updatePreview();
}

function updatePreview() {
  var typeEl = document.getElementById("recurringType");
  var timeEl = document.getElementById("recurringTime");
  if (!typeEl || !timeEl) return;

  var type = typeEl.value;
  var time = timeEl.value;
  var parts = time.split(":");
  var hour = parseInt(parts[0]);
  var min = parts[1];
  var ampm = hour >= 12 ? "PM" : "AM";
  var h12 = hour % 12 || 12;
  var tStr = h12 + ":" + min + " " + ampm;

  var days = [
    "Sunday",
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday",
  ];
  var text = "";

  if (type === "daily") {
    text = "Every day at " + tStr;
  } else if (type === "weekly") {
    var dowEl = document.querySelector("[name=day_of_week]");
    var dow = dowEl ? dowEl.value : 1;
    text = "Every " + days[dow] + " at " + tStr;
  } else if (type === "monthly") {
    var domEl = document.querySelector("[name=day_of_month]");
    var dom = domEl ? domEl.value : 1;
    text = "Every month on the " + dom + " at " + tStr;
  } else if (type === "custom") {
    var cdEl = document.querySelector("[name=custom_days]");
    var cd = cdEl ? cdEl.value : 1;
    text = "Every " + cd + " days at " + tStr;
  }

  var preview = document.getElementById("nextDuePreview");
  if (preview) {
    preview.textContent = "✅ " + text;
    preview.classList.add("visible");
  }
}
