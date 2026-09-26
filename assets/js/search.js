const searchInput = document.getElementById("searchInput");
const taskList = document.getElementById("taskList");
let searchTimer = null;

searchInput.addEventListener("input", function () {
  clearTimeout(searchTimer);
  const q = this.value.trim();

  if (q === "") {
    // show all tasks again
    document
      .querySelectorAll(".task-card")
      .forEach((t) => (t.style.display = "flex"));
    return;
  }

  // debounce 300ms
  searchTimer = setTimeout(() => {
    document.querySelectorAll(".task-card").forEach((card) => {
      const name = card.querySelector(".task-name").textContent.toLowerCase();
      card.style.display = name.includes(q.toLowerCase()) ? "flex" : "none";
    });
  }, 300);
});
