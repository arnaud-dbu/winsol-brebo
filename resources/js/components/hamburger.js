const HAMBURGER_SELECTOR = "[data-hamburger]";

function syncAriaExpanded(button, isOpen) {
    button.setAttribute("aria-expanded", isOpen ? "true" : "false");
}

function toggleHamburger(button) {
    const isOpen = button.classList.toggle("is-open");
    syncAriaExpanded(button, isOpen);
}

export function initHamburgers(root = document) {
    root.querySelectorAll(HAMBURGER_SELECTOR).forEach((button) => {
        syncAriaExpanded(button, button.classList.contains("is-open"));

        button.addEventListener("click", () => {
            toggleHamburger(button);
        });
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initHamburgers());
} else {
    initHamburgers();
}
