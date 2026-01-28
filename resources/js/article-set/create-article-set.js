window.addEventListener('DOMContentLoaded', () => {


    // proceed btns for different pop with specific functionaly
    let initArticleSet = document.getElementById('proceed_article_set');

    // ================= Initing Articke  =================

    if (initArticleSet) {
        let title = document.getElementById('article_set_title');
        let language = document.getElementById('article_set_language');
        let loader = document.querySelector('.loader');
        initArticleSet.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopImmediatePropagation();
            if (title.value.trim() == '') {
                alert("please enter the set title");
                return;
            }
            if (language.value == '') {
                alert("please select the language from the following option");
                return;
            }
            console.log("hello working till here");
            try {

                let url = 'http://127.0.0.1:8000/api/admin/article/set/initialize';

                loader.classList.remove('hidden');

                let api = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        name: title.value.trim(),
                        language: language.value
                    })
                });

                let res = await api.json();
                console.log(res)
                loader.classList.add('hidden');

                if (res.status) {
                    alert(`${res.message}`);
                    window.location = `http://127.0.0.1:8000/admin/article/set/options?id=${res.data.id}`;
                    title.value = language.value = '';

                } else {
                    alert(`${res.message}`);
                }

                console.log(res);


            } catch (error) {
                loader.classList.add('hidden');
                console.log(error, "something went wrong during fetching")
            }

        })
    }
})