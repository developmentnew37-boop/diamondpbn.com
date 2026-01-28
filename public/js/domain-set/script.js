import { initPopup, initMultiCheckbox } from "../general.js";



window.addEventListener('DOMContentLoaded', () => {

    console.log("something updated")

    // ** -------------- ** //

    const popParent = document.querySelector('.set-parent-div');
    const createDomainSet = document.getElementById('make_domain_set');
    const setOverlay = document.querySelector('.set-overlay');
    const popBox = document.querySelector('.pop-box');
    const closeBtn = document.querySelector('.domain-set-close'); // if you have multiple close buttons

    createDomainSet.addEventListener('click', (e) => {
        initPopup(popParent, setOverlay, popBox, closeBtn);
    });


    // ** -------------- ** //

    let title = document.getElementById('title');
    let category = document.getElementById('set-domain-category');
    let createSet = document.getElementById('create-set');
    let loader = document.querySelector('.loader');

    console.log(category);

    createSet.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopImmediatePropagation();
        let name = title.value.trim();
        let cat = category.value;

        if (name == '') {
            alert("Domain set name/title is required!");
            return;
        }
        console.log(cat);
        if (cat == '' || isNaN(cat)) {
            alert("please select set category properly! ");
            return;
        }

        try {
            let url = '/api/admin/domain/set/initialize';

            loader.classList.remove('hidden');
            let api = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    name: name,
                    category: cat
                })
            });

            let res = await api.json();
            loader.classList.add('hidden');

            if (res.status) {
                alert(`${res.message}`);
                window.location = `/admin/domains/set/create?id=${res.data.id}`;
                name = cat = '';

            } else {
                alert(`${res.message}`);
            }

            console.log(res);


        } catch (error) {
            loader.classList.add('hidden');
            console.log(error, "something went wrong during fetching")
        }

    });

    // ** -------------- ** //

    initMultiCheckbox({
        bulkSelector: 'bulkSetSelector',
        itemClass: 'multiCheckBox',
        hiddenInput: 'valHolders'
    });


})