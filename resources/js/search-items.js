window.addEventListener('DOMContentLoaded', () => {
    const searchInp = document.getElementById('search_category');
    const table = document.querySelector('.searchable-table');

    // **  -- -- -- -- -- -- -- -- -- -- -- **
    if (searchInp && table) {
        const rows = table.querySelectorAll('tbody tr');

        searchInp.addEventListener('input', (e) => {
            const searchValue = e.target.value.trim().toLowerCase();

            // Show all rows if less than 3 characters
            if (searchValue.length < 3) {
                rows.forEach(row => row.classList.remove('hidden'));
                return;
            }

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.classList.toggle('hidden', !text.includes(searchValue));
            });
        });
    }

    // **  -- -- -- -- -- -- -- -- -- -- -- **
    if (document.getElementById('domain-category')) {
        let domainCategory = document.getElementById('domain-category');

        domainCategory.addEventListener('change', function () {
            const selectedCategory = this.value;
            const url = new URL(window.location.href);
            const params = url.searchParams;

            // If category is already same, ignore
            if (params.get('category_id') == selectedCategory) return;

            // // Update or add category_id
            if (selectedCategory) {
                params.set('category_id', selectedCategory);
            }

            // // Reset pagination to page=1 if exists
            if (params.has('page')) params.set('page', 1);

            // // Update URL
            url.search = params.toString();
            window.location.href = url.toString(); // redirect
        });
    }
    if (document.getElementById('article_category')) {
        let article_category = document.getElementById('article_category');

        article_category.addEventListener('change', function () {
            const selectedCategory = this.value;
            const url = new URL(window.location.href);
            const params = url.searchParams;

            // If category is already same, ignore
            if (params.get('category') == selectedCategory) return;

            // // Update or add category_id
            if (selectedCategory) {
                params.set('category', selectedCategory);
            }

            // // Reset pagination to page=1 if exists
            if (params.has('page')) params.set('page', 1);

            // // Update URL
            url.search = params.toString();
            window.location.href = url.toString(); // redirect
        });
    }

    if (document.getElementById('article_langauge')) {
        let article_langauge = document.getElementById('article_langauge');

        article_langauge.addEventListener('change', function () {
            const selectedCategory = this.value;
            const url = new URL(window.location.href);
            const params = url.searchParams;

            // If category is already same, ignore
            if (params.get('language') == selectedCategory) return;

            // // Update or add category_id
            if (selectedCategory) {
                params.set('language', selectedCategory);
            }

            // // Reset pagination to page=1 if exists
            if (params.has('page')) params.set('page', 1);

            // // Update URL
            url.search = params.toString();
            window.location.href = url.toString(); // redirect
        });
    }

});
