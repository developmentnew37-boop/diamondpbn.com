import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('sidebar', () => ({
    sidebarOpen: false
}));

Alpine.data('settingsAccordion', (initial = 'site') => ({
    openSection: initial,
    toggle(section) {
        this.openSection = this.openSection === section ? null : section;
    },
    isOpen(section) {
        return this.openSection === section;
    }
}));

Alpine.data('imagePopupSlider', () => ({
    imagePopup: null,
    currentIndex: 0,
    openSlider(data, startIndex = 0) {
        this.imagePopup = data;
        this.currentIndex = Math.min(startIndex, (data?.images?.length || 1) - 1);
    },
    prev() {
        if (!this.imagePopup?.images?.length) return;
        this.currentIndex = this.currentIndex <= 0 ? this.imagePopup.images.length - 1 : this.currentIndex - 1;
    },
    next() {
        if (!this.imagePopup?.images?.length) return;
        this.currentIndex = this.currentIndex >= this.imagePopup.images.length - 1 ? 0 : this.currentIndex + 1;
    },
}));

Alpine.data('apiTester', () => ({
    apiKey: '',
    tab: 'get',
    loading: false,
    status: null,
    response: null,
    getParams: { search: '', per_page: 50 },
    postParams: { batch_id: 1, chunk_id: 0 },
    deleteUrl: '',
    postBody: `[
  {"url": "https://www.example.com/page1", "keyword": "admin", "nofollow": false},
  {"url": "https://www.example.com/page2", "keyword": "casino", "nofollow": true}
]`,
    get statusClass() {
        if (!this.status) return '';
        if (this.status >= 200 && this.status < 300) return 'text-green-600';
        if (this.status >= 400) return 'text-red-600';
        return 'text-gray-600';
    },
    get responseText() {
        return this.response === null ? '' : (typeof this.response === 'string' ? this.response : JSON.stringify(this.response, null, 2));
    },
    async sendGet() {
        if (!this.apiKey.trim()) return alert('Please enter your API key');
        this.loading = true; this.response = null; this.status = null;
        try {
            const params = new URLSearchParams();
            if (this.getParams.search) params.set('search', this.getParams.search);
            if (this.getParams.per_page) params.set('per_page', this.getParams.per_page);
            const url = '/api/hidden-links' + (params.toString() ? '?' + params.toString() : '');
            const res = await fetch(url, {
                headers: { 'Authorization': 'Bearer ' + this.apiKey.trim(), 'Accept': 'application/json' },
            });
            this.status = res.status;
            this.response = await res.json();
        } catch (e) {
            this.status = 0;
            this.response = e.message;
        }
        this.loading = false;
    },
    async sendPost() {
        if (!this.apiKey.trim()) return alert('Please enter your API key');
        this.loading = true; this.response = null; this.status = null;
        try {
            let payload;
            try { payload = JSON.parse(this.postBody); } catch { return alert('Invalid JSON in payload'); }
            const res = await fetch('/api/hidden-links', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + this.apiKey.trim(),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    payload: payload,
                    batch_id: parseInt(this.postParams.batch_id) || 0,
                    chunk_id: parseInt(this.postParams.chunk_id) || 0,
                }),
            });
            this.status = res.status;
            this.response = await res.json();
        } catch (e) {
            this.status = 0;
            this.response = e.message;
        }
        this.loading = false;
    },
    async sendDelete() {
        if (!this.apiKey.trim()) return alert('Please enter your API key');
        const url = this.deleteUrl.trim();
        if (!url) return alert('Please enter a URL to delete');
        this.loading = true; this.response = null; this.status = null;
        try {
            const res = await fetch('/api/hidden-links/by-url', {
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + this.apiKey.trim(),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ url: url }),
            });
            this.status = res.status;
            this.response = await res.json();
        } catch (e) {
            this.status = 0;
            this.response = e.message;
        }
        this.loading = false;
    },
}));

Alpine.start();


// let data = {
//     playload:[
//         {"url":"https:\/\/www.fcb8casino.com","keyword":"admin","nofollow":false},
//         {"url":"https:\/\/www.Brand-New-Online-Casinos.com","keyword":"admin","nofollow":false},
//         {"url":"https:\/\/www.FanAtticCasino.com","keyword":"admin","nofollow":false}
//     ],
//     batch_id: 1,
//     chunk_id: 1,
// }
