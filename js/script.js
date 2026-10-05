function showForm(formId) {
    document.querySelectorAll(".form-box").forEach(function (form) {
        form.classList.remove("active");
    });
    var target = document.getElementById(formId);
    if (target) {
        target.classList.add("active");
    }
}

window.addEventListener("DOMContentLoaded", function () {
    var params = new URLSearchParams(window.location.search);
    var form = params.get("form");
    if (form === "signup") {
        showForm("signup-form");
    } else if (form === "login") {
        showForm("login-form");
    }
});
