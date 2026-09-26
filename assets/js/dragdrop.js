// uses SortableJS — add this to todo.php head:
// <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

document.addEventListener("DOMContentLoaded", function () {
  const list = document.getElementById("taskList");
  if (!list) return;

  Sortable.create(list, {
    handle: ".drag-handle",
    animation: 150,
    onEnd: function () {
      // collect new order
      const order = [...list.querySelectorAll(".task-card")].map(
        (card) => card.dataset.id,
      );

      // save to server
      fetch("/Mini_Project/api/reorder.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ order }),
      });
    },
  });
});
