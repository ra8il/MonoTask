(function () {
  "use strict";

  var SUBTASK_ACTIONS = [
    "subtasks/add.php",
    "subtasks/toggle.php",
    "subtasks/delete.php",
  ];

  function isSubtaskForm(form) {
    var action = form.getAttribute("action") || "";
    return SUBTASK_ACTIONS.some(function (a) {
      return action.indexOf(a) !== -1;
    });
  }

  document.addEventListener("submit", function (e) {
    var form = e.target;
    if (!form || form.tagName !== "FORM" || !isSubtaskForm(form)) return;

    var card = form.closest(".task-card");
    if (!card) return;

    e.preventDefault();

    // adding an empty subtask: do nothing
    var textInput = form.querySelector(".subtask-input");
    if (textInput && textInput.value.trim() === "") return;

    var taskId = card.getAttribute("data-id");
    var wasOpen =
      card.querySelector(".task-expanded").style.display === "block";
    var wasAdding = !!textInput;
    var buttons = form.querySelectorAll("button");
    buttons.forEach(function (b) {
      b.disabled = true;
    });

    fetch(form.action, {
      method: "POST",
      body: new FormData(form),
      credentials: "same-origin",
    })
      .then(function (res) {
        // session expired: the server redirected us to the login page
        if (res.url.indexOf("login.php") !== -1) {
          window.location.href = res.url;
          return null;
        }
        return res.text();
      })
      .then(function (html) {
        if (html === null) return;

        var doc = new DOMParser().parseFromString(html, "text/html");
        var fresh = doc.querySelector('.task-card[data-id="' + taskId + '"]');
        if (!fresh) {
          window.location.reload();
          return;
        }

        var imported = document.importNode(fresh, true);
        card.replaceWith(imported);

        if (wasOpen) {
          imported.querySelector(".task-expanded").style.display = "block";
          var arrow = imported.querySelector(".expand-arrow");
          if (arrow) arrow.textContent = "▲";
        }

        // after adding, put the cursor back in the box for the next subtask
        if (wasAdding) {
          var next = imported.querySelector(".subtask-input");
          if (next) next.focus();
        }

        // keep the live search filter applied
        var search = document.getElementById("searchInput");
        if (search && search.value) search.dispatchEvent(new Event("input"));
      })
      .catch(function () {
        window.location.reload();
      });
  });
})();
