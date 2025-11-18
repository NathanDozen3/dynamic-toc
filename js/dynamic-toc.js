document.addEventListener("DOMContentLoaded", () => {
    // Select all tocaccordion items
    const tocaccordionItems = document.querySelectorAll(".tocaccordion-item");

    tocaccordionItems.forEach((item) => {
        const title = item.querySelector(".tocaccordion-title");
        const body = item.querySelector(".tocaccordion-body");
        const icon = item.querySelector(".tocaccordion-icon");

        // Ensure all tocaccordions start closed
        body.style.maxHeight = "0";
        icon.textContent = "+";

        // Add click event listener
        title.addEventListener("click", () => {
            const isOpen = title.classList.contains("active");

            // Close all other tocaccordion items (optional: for exclusive behavior)
            tocaccordionItems.forEach((otherItem) => {
                const otherTitle = otherItem.querySelector(".tocaccordion-title");
                const otherBody = otherItem.querySelector(".tocaccordion-body");
                const otherIcon = otherItem.querySelector(".tocaccordion-icon");

                if (otherTitle !== title) {
                    otherBody.style.maxHeight = "0";
                    otherTitle.classList.remove("active");
                    otherIcon.textContent = "+";
                }
            });

            // Toggle the clicked tocaccordion item
            if (isOpen) {
                body.style.maxHeight = "0"; // Collapse
                title.classList.remove("active");
                icon.textContent = "+";
            } else {
                body.style.maxHeight = `${body.scrollHeight}px`; // Expand
                title.classList.add("active");
                icon.textContent = "-";
            }
        });
    });
});