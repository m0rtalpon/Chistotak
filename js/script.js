document.addEventListener("DOMContentLoaded", () => {
    const header = document.querySelector("header");
    const serviceSelect = document.getElementById("service");
    const contactSection = document.getElementById("contact");
    const firstInput = document.getElementById("clientName");

    const updateHeaderHeight = () => {
        if (!header) return;

        document.documentElement.style.setProperty(
            "--header-height",
            `${Math.ceil(header.getBoundingClientRect().height)}px`
        );
    };

    updateHeaderHeight();
    window.addEventListener("resize", updateHeaderHeight);

    if (header && "ResizeObserver" in window) {
        new ResizeObserver(updateHeaderHeight).observe(header);
    }

    const revealElements = document.querySelectorAll([
        ".hero-text",
        ".hero-image",
        ".services h2",
        ".service-card",
        ".advantages h2",
        ".advantages-grid .item",
        ".reviews h2",
        ".reviews > .container > div",
        ".contact h2",
        ".contact form",
        ".footer-grid > *",
        ".copyright",
    ].join(","));

    revealElements.forEach((element, index) => {
        element.classList.add("reveal");
        element.style.setProperty("--reveal-delay", `${(index % 4) * 70}ms`);
    });

    if ("IntersectionObserver" in window) {
        const revealObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;

                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            });
        }, {
            threshold:0.12,
            rootMargin:"0px 0px -32px",
        });

        revealElements.forEach((element) => revealObserver.observe(element));
    } else {
        revealElements.forEach((element) => element.classList.add("is-visible"));
    }

    if (!serviceSelect) return;

    document.querySelectorAll(".service-card").forEach((card) => {
        card.addEventListener("click", (event) => {
            const serviceValue = card.dataset.service;

            if (!serviceValue) return;

            event.preventDefault();

            serviceSelect.value = serviceValue;
            serviceSelect.dispatchEvent(new Event("change", { bubbles: true }));

            if (contactSection) {
                contactSection.scrollIntoView({ behavior: "smooth", block: "start" });
            }

            window.setTimeout(() => {
                if (firstInput) {
                    firstInput.focus({ preventScroll: true });
                }
            }, 350);
        });
    });
});
