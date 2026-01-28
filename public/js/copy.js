window.addEventListener('DOMContentLoaded', () => {
    let copyLinks = document.getElementsByClassName('copy-link');

    Array.from(copyLinks).forEach((item) => {
        item.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();

            // get link
            let link = item.getAttribute('data-report');
            if (!link) return;

            try {
                // ✅ copy to clipboard
                await navigator.clipboard.writeText(link);

                // ✅ optional feedback
                // alert('Copied')
                // console.log(link);
                item.innerHTML = `<span class="material-symbols-outlined !text-sm text-white">check</span>`;
                setTimeout(() => {
                    item.innerHTML = `<span class="material-symbols-outlined !text-sm text-white">content_copy</span>`;
                }, 1500);

            } catch (err) {
                console.error('Clipboard copy failed:', err);
                alert('Failed to copy link');
            }
        });
    });
});
