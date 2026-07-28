document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("orderForm");
    const statusBox = document.getElementById("formStatus");

    if (!form) return;

    const setStatus = (message, type = "") => {
        if (!statusBox) return;

        statusBox.textContent = message;
        statusBox.className = `form-status ${type}`.trim();
    };

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        const button = form.querySelector('button[type="submit"]');
        const initialButtonText = button ? button.textContent : "";

        setStatus("");

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (button) {
            button.disabled = true;
            button.textContent = "Відправляємо...";
        }

        try {
            const response = await fetch(form.action, {
                method: "POST",
                headers: {
                    "Accept": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: new FormData(form),
            });

            const contentType = response.headers.get("content-type") || "";

            if (!contentType.includes("application/json")) {
                throw new Error("Сервер повернув не JSON-відповідь.");
            }

            const answer = await response.json();
            const message = answer.message || "Відповідь сервера отримана.";

            if (!response.ok || !answer.status) {
                setStatus(message, "is-error");
                return;
            }

            form.reset();
            setStatus(message, "is-success");
        } catch (error) {
            setStatus("Помилка з'єднання. Перевірте, що сайт відкрито через PHP-сервер.", "is-error");
            console.error(error);
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = initialButtonText;
            }
        }
    });
});
