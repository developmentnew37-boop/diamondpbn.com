// Sidebar toggle functionality
const sidebar = document.getElementById("sidebar");
const toggleBtn = document.getElementById("toggleBtn");
const sidebarOverlay = document.getElementById("sidebarOverlay");
const submenu = document.getElementsByClassName("submenu");
const allMenuItems = document.querySelectorAll(".menu-item");
const sideMenuBtn = document.getElementsByClassName("side-menu-btn");
const navbar = document.querySelector(".navbar");
let isHovering = false;
let hoverTimeout;

// it is just for collapsing the sub menu when on hover
function collapseSubMenu() {
    Array.from(submenu).forEach((x) => {
        x.classList.contains("open") ? x.classList.remove("open") : "";
    });
    allMenuItems.forEach((y) => {
        y.classList.contains("expanded") ? y.classList.remove("expanded") : "";
    });
}

// Toggle sidebar on button click
toggleBtn.addEventListener("click", () => {
    collapseSubMenu();
    if (window.innerWidth <= 768) {
        // Mobile: slide in/out
        sidebar.classList.toggle("mobile-open");
        sidebarOverlay.classList.toggle("active");
    } else {
        // Desktop: collapse/expand
        sidebar.classList.toggle("collapsed");
        navbar.classList.toggle("nav-toggle");
    }
});

// Close sidebar when clicking overlay (mobile)
sidebarOverlay.addEventListener("click", () => {
    sidebar.classList.remove("mobile-open");
    sidebarOverlay.classList.remove("active");
});

// Hover to expand collapsed sidebar (desktop only)
sidebar.addEventListener("mouseenter", () => {
    if (window.innerWidth > 768 && sidebar.classList.contains("collapsed")) {
        isHovering = true;
        clearTimeout(hoverTimeout);
        hoverTimeout = setTimeout(() => {
            if (isHovering) {
                sidebar.classList.remove("collapsed");
                navbar.classList.remove("nav-toggle");
            }
        }, 300); // Delay before expanding
    }
});

sidebar.addEventListener("mouseleave", () => {
    if (window.innerWidth > 768) {
        isHovering = false;
        clearTimeout(hoverTimeout);
        // Don't auto-collapse, let user click toggle button
    }
});

// Submenu toggle
function toggleSubmenu(element) {
    const submenu = element.nextElementSibling;
    // const allMenuItems = document.querySelectorAll(".menu-item");
    const allSubmenus = document.querySelectorAll(".submenu");

    // Close other submenus
    allMenuItems.forEach((item) => {
        if (item !== element) {
            item.classList.remove("expanded");
        }
    });

    allSubmenus.forEach((sub) => {
        if (sub !== submenu) {
            sub.classList.remove("open");
        }
    });

    // Toggle current submenu
    element.classList.toggle("expanded");
    if (submenu) {
        submenu.classList.toggle("open");
    }
}
// onclick="toggleSubmenu(this)"
Array.from(sideMenuBtn).forEach((item, idx) => {
    item.addEventListener("click", function () {
        toggleSubmenu(this);
    });
});
// User dropdown toggle
const userDropdown = document.getElementById("userDropdown");

userDropdown.addEventListener("click", (e) => {
    e.stopPropagation();
    userDropdown.classList.toggle("active");
});

// Close dropdown when clicking outside
document.addEventListener("click", (e) => {
    if (!userDropdown.contains(e.target)) {
        userDropdown.classList.remove("active");
    }
});

// Prevent dropdown from closing when clicking inside
userDropdown.querySelector(".dropdown-menu").addEventListener("click", (e) => {
    e.stopPropagation();
});

// Handle window resize
let resizeTimer;
window.addEventListener("resize", () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove("mobile-open");
            sidebarOverlay.classList.remove("active");
        } else {
            // On mobile, ensure sidebar is not in collapsed state
            sidebar.classList.remove("collapsed");
        }
    }, 250);
});

// Active menu item
const menuItems = document.querySelectorAll(".menu-item:not([onclick])");
menuItems.forEach((item) => {
    item.addEventListener("click", function (e) {
        // Don't prevent default for links
        menuItems.forEach((mi) => mi.classList.remove("active"));
        this.classList.add("active");
    });
});

// Submenu items
const submenuItems = document.querySelectorAll(".submenu-item");
submenuItems.forEach((item) => {
    item.addEventListener("click", function (e) {
        // e.preventDefault();
        submenuItems.forEach((si) => (si.style.fontWeight = "normal"));
        this.style.fontWeight = "600";
    });
});

// Auto-hide cus__success / cus__error alerts after 5 seconds
(function () {
    const AUTO_HIDE_MS = 5000;
    function hideCusAlerts() {
        const main = document.querySelector(".main-content");
        if (!main) return;
        const alerts = main.querySelectorAll(
            [
                '.js-cus-alert',
                '[role="alert"]',
                'div.text-sm.rounded.bg-green-100.text-green-700',
                'div.text-sm.rounded.bg-red-100.text-red-700',
            ].join(", ")
        );
        alerts.forEach(function (el) {
            setTimeout(function () {
                el.style.transition = "opacity 0.3s ease";
                el.style.opacity = "0";
                setTimeout(function () {
                    el.remove();
                }, 300);
            }, AUTO_HIDE_MS);
        });
    }
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", hideCusAlerts);
    } else {
        hideCusAlerts();
    }
})();
