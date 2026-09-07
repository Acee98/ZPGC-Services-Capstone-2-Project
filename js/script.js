function showForm(formId) {
    document.querySelectorAll(".form-box").forEach(function (form) {
        form.classList.remove("active");
    });
    document.getElementById(formId).classList.add("active");
}

window.addEventListener("DOMContentLoaded", function (form) {
    var params = new URLSearchParams(window.location.search);
    if (params.get("form") === "signup") {
        showForm("signup-form");
    }
})