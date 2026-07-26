const MOBILE_NAV_SELECTOR = "[data-mobile-nav]";
const FOCUSABLE_SELECTOR =
    'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

function getFocusableElements(container) {
    return Array.from(container.querySelectorAll(FOCUSABLE_SELECTOR)).filter(
        (element) => {
            return (
                !element.hasAttribute("disabled") &&
                element.getAttribute("aria-hidden") !== "true"
            );
        },
    );
}

function setExpanded(button, expanded) {
    button.setAttribute("aria-expanded", expanded ? "true" : "false");
}

function setMenuState(root, trigger, panel, isOpen) {
    const openLabel = trigger.dataset.labelOpen || "Open menu";
    const closeLabel = trigger.dataset.labelClose || "Close menu";

    root.classList.toggle("is-open", isOpen);
    panel.setAttribute("aria-hidden", isOpen ? "false" : "true");
    panel.inert = !isOpen;
    trigger.setAttribute("aria-label", isOpen ? closeLabel : openLabel);

    if (isOpen) {
        document.body.classList.add("mobile-nav-open");
        trigger.classList.add("is-open");
        setExpanded(trigger, true);
        return;
    }

    document.body.classList.remove("mobile-nav-open");
    trigger.classList.remove("is-open");
    setExpanded(trigger, false);
}

function trapFocus(event, panel) {
    if (event.key !== "Tab") {
        return;
    }

    const focusableElements = getFocusableElements(panel);
    if (focusableElements.length === 0) {
        event.preventDefault();
        panel.focus();
        return;
    }

    const first = focusableElements[0];
    const last = focusableElements[focusableElements.length - 1];
    const current = document.activeElement;

    if (!panel.contains(current)) {
        event.preventDefault();
        first.focus();
        return;
    }

    if (!event.shiftKey && current === last) {
        event.preventDefault();
        first.focus();
    }

    if (event.shiftKey && current === first) {
        event.preventDefault();
        last.focus();
    }
}

function initMobileNavigationItem(root) {
    const trigger = root.querySelector(
        '[data-hamburger][data-target="mobile-navigation-panel"]',
    );
    const panel = root.querySelector("[data-mobile-nav-panel]");
    const overlay = root.querySelector("[data-mobile-nav-overlay]");

    if (!trigger || !panel || !overlay) {
        return;
    }

    let previousFocusedElement = null;

    setMenuState(root, trigger, panel, false);

    function openMenu() {
        previousFocusedElement = document.activeElement;
        setMenuState(root, trigger, panel, true);

        const focusableElements = getFocusableElements(panel);
        const firstFocusable = focusableElements[0] || panel;
        firstFocusable.focus();
    }

    function closeMenu({ restoreFocus } = { restoreFocus: true }) {
        setMenuState(root, trigger, panel, false);

        if (restoreFocus && previousFocusedElement instanceof HTMLElement) {
            previousFocusedElement.focus();
        } else if (restoreFocus) {
            trigger.focus();
        }
    }

    trigger.addEventListener("click", () => {
        queueMicrotask(() => {
            const shouldOpen = trigger.classList.contains("is-open");

            if (shouldOpen) {
                openMenu();
                return;
            }

            closeMenu();
        });
    });

    overlay.addEventListener("click", () => {
        closeMenu();
    });

    panel.addEventListener("click", (event) => {
        const link = event.target.closest("a[href]");
        if (!link) {
            return;
        }

        closeMenu({ restoreFocus: false });
    });

    document.addEventListener("keydown", (event) => {
        if (!root.classList.contains("is-open")) {
            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            closeMenu();
            return;
        }

        trapFocus(event, panel);
    });

    window.addEventListener("resize", () => {
        if (!root.classList.contains("is-open")) {
            return;
        }

        if (window.matchMedia("(min-width: 48rem)").matches) {
            closeMenu({ restoreFocus: false });
        }
    });
}

export function initMobileNavigation(root = document) {
    root.querySelectorAll(MOBILE_NAV_SELECTOR).forEach((mobileNav) => {
        initMobileNavigationItem(mobileNav);
    });
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => initMobileNavigation());
} else {
    initMobileNavigation();
}
