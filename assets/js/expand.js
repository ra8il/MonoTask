function toggleExpand(taskId) {
  const expanded = document.getElementById(`expand-${taskId}`);
  const arrow = document.getElementById(`arrow-${taskId}`);
  const isOpen = expanded.style.display !== "none";

  // close all others first
  document
    .querySelectorAll(".task-expanded")
    .forEach((el) => (el.style.display = "none"));
  document
    .querySelectorAll(".expand-arrow")
    .forEach((el) => (el.textContent = "▼"));

  // toggle clicked one
  if (!isOpen) {
    expanded.style.display = "block";
    arrow.textContent = "▲";
  }
}
