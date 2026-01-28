import { initMultiCheckbox } from "../general";

window.addEventListener('DOMContentLoaded', () => {
    $(document).ready(function () {
        $('#myTable').DataTable({
            pageLength: 100,
            lengthMenu: [
                [10, 50, 100, 250, 500, -1], // -1 means “All”
                [10, 50, 100, 250, 500, "All"] // labels shown in dropdown
            ],
            responsive: true,
            order: [
                [1, 'asc']
            ], // default sort by sno
            columnDefs: [{
                orderable: false,
                targets: [0]
            }, // disable sort for checkbox + action
            {
                className: "!text-center !align-middle",
                targets: [0, 1, 3, 4, 5, 6]
            } // center align checkbox + numeric cols
            ],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
            }
        });
    });

    // -- -- -- -- -- -- -- //
    // #domains-holder | #bulkSelectDomains | .sel-domains | showingSelectCount

    initMultiCheckbox({
        bulkSelector: 'bulkSelectDomains',
        itemClass: 'sel-domains',
        hiddenInput: 'domains-holder',
        counterSelector: '#showingSelectCount'
    })

    // ** -- -- manual text area -- -- ** //

    const textarea = document.getElementById('manualDomains');
    const countDisplay = document.getElementById('manualselectedCount');

    function isValidDomain(domain) {
        return /^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/.test(domain);
        // supports: domain.com , https://domain.com
    }

    function updateDomainCount() {
        let value = textarea.value.trim();
        let lines = value.split(/\n+/).map(x => x.trim()).filter(x => x !== "");

        // Count valid only or total?
        let valid = lines.filter(isValidDomain);
        countDisplay.textContent = valid.length;
    }

    // 🔥 Prevent user from going to next line if previous isn't valid
    textarea.addEventListener('keydown', function (e) {
        if (e.key === "Enter") {
            let lines = textarea.value.trim().split("\n");
            let lastLine = lines[lines.length - 1].trim();

            if (lastLine.length > 0 && !isValidDomain(lastLine)) {
                e.preventDefault();
                alert("Last domain is not a valid URL format!");
                return;
            }
        }
    });

    textarea.addEventListener('input', updateDomainCount);


})