function setSelectedNavItem(item) {
    if (!item) {
        return;
    }
    const navList = item.closest(".nav-list");
    if (!navList) {
        return;
    }
    navList.querySelectorAll(".nav-list-item").forEach(function (navItem) {
        navItem.classList.remove("selected");
    });
    item.classList.add("selected");
    
    const navId = item.getAttribute("data-nav");
    if (navId) {
        document.body.setAttribute("data-page", navId);
    } else {
        document.body.removeAttribute("data-page");
    }
}

function initNavSelection() {
    const navList = document.querySelector(".nav-list");
    if (!navList) {
        return;
    }
    const selectedItem = navList.querySelector(".nav-list-item.selected") || navList.querySelector('[data-nav="dashboard"]') || navList.querySelector(".nav-list-item:first-child");

    setSelectedNavItem(selectedItem);
}

function initNavClickSelection() {
    const navList = document.querySelector(".nav-list");
    if (!navList) {
        return;
    }
    navList.querySelectorAll(".nav-list-item").forEach(function (item) {
        const link = item.querySelector(".nav-link");
        if (!link) {
            return;
        }
        link.addEventListener("click", function(event) {
            const href = link.getAttribute("href") || "";
            if (href && href !== "#") {
                return;
            }
            event.preventDefault();
            setSelectedNavItem(item);
        });
    });
}
function initSidebarCollapse() {
    const mainHead = document.querySelector(".main-head");
    const showcaseToggler = document.querySelector(".showcase-toggler");
    if (!mainHead) {
        return;
    }
    mainHead.classList.add("active");
    if (!showcaseToggler) {
        return
    }
    showcaseToggler.addEventListener("click", function() {
        mainHead.classList.remove("active");
    });
    mainHead.addEventListener("mouseleave", function() {
        mainHead.classList.add("active");
    });
    mainHead.addEventListener("mouseenter", function() {
        mainHead.classList.remove("active");
    });
}
document.addEventListener("DOMContentLoaded", function() {
    initNavSelection();
    initNavClickSelection();
    initSidebarCollapse();
})