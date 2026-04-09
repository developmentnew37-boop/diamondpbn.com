/**
 * Multi-level keyword editor (create flow parity). Configure via window.__editMultiKeywordConfig:
 * { postCount, initial: { boxes }, ids?: { wrap, container, form, holder, totalSpan, submitBtn, addMore, noFollow } }
 * Legacy: __editCampaignPostCount / __editCampaignMultiInitial still work when config omitted.
 */
(function () {
    var cfg = window.__editMultiKeywordConfig || {};

    function id(key, def) {
        return cfg.ids && cfg.ids[key] ? cfg.ids[key] : def;
    }

    var postCount = parseInt(
        String(
            cfg.postCount != null
                ? cfg.postCount
                : window.__editCampaignPostCount || "0"
        ),
        10
    );
    if (isNaN(postCount)) {
        postCount = 0;
    }

    var initial = cfg.initial || window.__editCampaignMultiInitial || { boxes: [] };

    var wrap = document.getElementById(id("wrap", "edit-multi-key-url-box-parent"));
    var container = document.getElementById(id("container", "edit-multi-level-keyword-url-container"));
    var form = document.getElementById(id("form", "edit-multi-keyword-form"));
    var holder = document.getElementById(id("holder", "editKeywordsDataHolder"));
    var totalSpan = document.getElementById(id("totalSpan", "edit-multi-total-box-count"));
    var submitBtn = document.getElementById(id("submitBtn", "edit-multi-keyword-submit"));
    var addMoreSelector = "#" + id("addMore", "edit-add-more-multi-keyword-Url");

    if (!wrap || !form || !holder) {
        return;
    }

    function allowOnlyNumbers(nodes) {
        if (!nodes || !nodes.length) {
            return;
        }
        Array.from(nodes).forEach(function (inp) {
            inp.addEventListener("input", function () {
                this.value = this.value.replace(/[^0-9]/g, "");
            });
        });
    }

    function pad2(n) {
        return String(n).padStart(2, "0");
    }

    function updateMultiBoxLabels() {
        var counts = wrap.querySelectorAll(".multi-box-count");
        Array.from(counts).forEach(function (el, indx) {
            el.textContent = pad2(indx + 1);
        });
        if (totalSpan) {
            totalSpan.textContent = String(counts.length);
        }
    }

    function rowHtml(numStr, urlVal, kwVal) {
        return (
            '<div class="w-full flex flex-col gap-2 md:flex-row mf:gap-0 md:justify-between relative !px-10 !py-1 multi-keyword-url-row">' +
            '<span class="multi-keyword-url-count cursor-move flex items-center justify-center absolute top-1/2 -translate-y-1/2 left-2 text-white bg-black w-6 h-6 !p-1 text-[12px] rounded">' +
            numStr +
            "</span>" +
            '<div class="w-full md:w-[49.5%] flex">' +
            '<input type="text" placeholder="Enter Url" class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-url-inp" value="' +
            escapeAttr(urlVal) +
            '">' +
            "</div>" +
            '<div class="w-full md:w-[49.5%] flex">' +
            '<input type="text" placeholder="Enter Keyword" class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-keyowrd-inp" value="' +
            escapeAttr(kwVal) +
            '">' +
            "</div>" +
            '<button type="button" class="remove-multi-keyword-url-row w-6 h-6 bg-red-600 rounded-full text-white flex items-center justify-center absolute top-1/2 -translate-y-1/2 right-2 cursor-pointer">' +
            '<span class="material-symbols-outlined !text-sm">close</span>' +
            "</button>" +
            "</div>"
        );
    }

    function escapeAttr(s) {
        if (!s) {
            return "";
        }
        return String(s)
            .replace(/&/g, "&amp;")
            .replace(/"/g, "&quot;")
            .replace(/</g, "&lt;");
    }

    function boxInnerHtml(paddedBoxNum, quantity, media, rows) {
        var rowsHtml = "";
        for (var r = 0; r < rows.length; r++) {
            rowsHtml += rowHtml(pad2(r + 1), rows[r].url || "", rows[r].keyword || "");
        }
        return (
            '<div class="w-full flex items-center !px-4 !py-2 !pr-10 bg-[var(--primary-color)] justify-end cursor-pointer relative multi-keyword-url-collapser">' +
            '<div class="w-1/2 flex items-center">' +
            '<span class="multi-box-count flex !px-3 !py-1 text-sm font-semibold text-white rounded">' +
            paddedBoxNum +
            "</span></div>" +
            '<div class="w-1/2 flex gap-2 justify-end items-center">' +
            '<label class="text-white">Apply for</label>' +
            '<input type="text" value="' +
            escapeAttr(String(quantity)) +
            '" class="bg-gray-50 !p-2 text-sm outline-none text-center border border-gray-300 w-1/5 multi-keyword-url-box-quantity num-inp">' +
            "</div>" +
            '<span class="material-symbols-outlined text-gray-100 absolute top-1/2 -translate-y-1/2 right-2 duration-500 transition-all arrow-rotate">keyboard_arrow_up</span>' +
            "</div>" +
            '<div class="w-full flex flex-col duration-300 bg-orange-100 transition-all !pb-2 multi-keyword-url-accordion">' +
            '<div class="w-full flex flex-col gap-2 items-center !px-4 !py-1">' +
            '<label class="text-sm flex items-center after:content-[\'*\'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full">Media Link</label>' +
            '<input type="text" placeholder="Enter Url" class="bg-gray-50 !p-4 text-sm outline-none border border-gray-300 w-full multiple-box-media-link" value="' +
            escapeAttr(media || "") +
            '">' +
            "</div>" +
            '<div class="w-full flex items-center !px-4 !py-2">' +
            '<div class="w-1/2 flex"><label class="text-sm flex items-center after:content-[\'*\'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full">Add Multiple Urls &amp; Keywords</label></div>' +
            '<div class="w-1/2 flex gap-2 justify-end items-center">' +
            '<button type="button" class="w-6 h-6 cursor-pointer bg-green-500 flex items-center text-white outline-none add-multipe-box justify-center rounded">' +
            '<span class="material-symbols-outlined !text-sm">add</span></button></div></div>' +
            '<div class="w-full flex flex-col gap-1 max-h-[120px] overflow-hidden overflow-y-auto multi-url-keyword-attachment-box">' +
            rowsHtml +
            "</div>" +
            '<div class="w-full flex flex-col items-start !px-4 !py-1 !mt-2">' +
            '<button type="button" class="!p-1 bg-red-600 rounded text-sm cursor-pointer text-white remove-multi-keyword-box">delete</button>' +
            "</div></div>"
        );
    }

    function initSortableOnAttachment(fieldsAttachParent) {
        if (!fieldsAttachParent || fieldsAttachParent.sortableInitialized) {
            return;
        }
        if (typeof Sortable === "undefined") {
            return;
        }
        new Sortable(fieldsAttachParent, {
            animation: 150,
            handle: ".multi-keyword-url-count",
            ghostClass: "sortable-ghost",
            onEnd: function () {
                fieldsAttachParent.querySelectorAll(".multi-keyword-url-row").forEach(function (row, index) {
                    var countSpan = row.querySelector(".multi-keyword-url-count");
                    if (countSpan) {
                        countSpan.textContent = pad2(index + 1);
                    }
                });
            },
        });
        fieldsAttachParent.sortableInitialized = true;
    }

    function hydrate() {
        wrap.innerHTML = "";
        if (postCount === 0) {
            return;
        }
        var boxes =
            initial.boxes && initial.boxes.length > 0
                ? initial.boxes
                : [
                      {
                          quantity: postCount,
                          media: "",
                          rows: [{ url: "", keyword: "" }],
                      },
                  ];

        boxes.forEach(function (b, idx) {
            var div = document.createElement("div");
            div.className = "flex w-full flex-col multi-keyword-url-box relative";
            var qty = parseInt(String(b.quantity || 0), 10);
            if (isNaN(qty) || qty < 0) {
                qty = 0;
            }
            var rows = b.rows && b.rows.length ? b.rows : [{ url: "", keyword: "" }];
            div.innerHTML = boxInnerHtml(pad2(idx + 1), qty, b.media || "", rows);
            wrap.appendChild(div);
            var attach = div.querySelector(".multi-url-keyword-attachment-box");
            initSortableOnAttachment(attach);
        });

        updateMultiBoxLabels();
        allowOnlyNumbers(wrap.querySelectorAll(".multi-keyword-url-box-quantity"));
    }

    hydrate();

    wrap.addEventListener("click", function (e) {
        if (e.target.closest("input") || e.target.tagName === "INPUT") {
            return;
        }

        if (e.target.closest(".add-multipe-box")) {
            var btn = e.target.closest(".add-multipe-box");
            var parent = btn.closest(".multi-keyword-url-box");
            var fieldsAttachParent = parent.querySelector(".multi-url-keyword-attachment-box");
            var spanCount = fieldsAttachParent.querySelectorAll(".multi-keyword-url-count").length + 1;
            if (spanCount > 5) {
                alert("you can not add more than 5 keywords & urls in a post");
                return;
            }
            var paddedNumber = pad2(spanCount);
            var div = document.createElement("div");
            div.className =
                "w-full flex flex-col gap-2 md:flex-row mf:gap-0 md:justify-between relative !px-10 !py-1 multi-keyword-url-row";
            div.innerHTML =
                '<span class="multi-keyword-url-count cursor-move flex items-center justify-center absolute top-1/2 -translate-y-1/2 left-2 text-white bg-black w-6 h-6 !p-1 text-[12px] rounded">' +
                paddedNumber +
                "</span>" +
                '<div class="w-full md:w-[49.5%] flex"><input type="text" placeholder="Enter Url" class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-url-inp"></div>' +
                '<div class="w-full md:w-[49.5%] flex"><input type="text" placeholder="Enter Keyword" class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-keyowrd-inp"></div>' +
                '<button type="button" class="remove-multi-keyword-url-row w-6 h-6 bg-red-600 rounded-full text-white flex items-center justify-center absolute top-1/2 -translate-y-1/2 right-2 cursor-pointer">' +
                '<span class="material-symbols-outlined !text-sm">close</span></button>';
            fieldsAttachParent.appendChild(div);
            div.scrollIntoView({ behavior: "smooth", block: "end" });
            initSortableOnAttachment(fieldsAttachParent);
        }

        if (e.target.closest(".remove-multi-keyword-url-row")) {
            var btn = e.target.closest(".remove-multi-keyword-url-row");
            var row = btn.closest(".multi-keyword-url-row");
            var fieldsAttachParent = row.closest(".multi-url-keyword-attachment-box");
            row.remove();
            fieldsAttachParent.querySelectorAll(".multi-keyword-url-row").forEach(function (r, index) {
                var countSpan = r.querySelector(".multi-keyword-url-count");
                if (countSpan) {
                    countSpan.textContent = pad2(index + 1);
                }
            });
        }

        if (e.target.closest(".multi-keyword-url-collapser")) {
            var collapser = e.target.closest(".multi-keyword-url-collapser");
            var parent = collapser.closest(".multi-keyword-url-box");
            var accordionDiv = parent.querySelector(".multi-keyword-url-accordion");
            collapser.querySelector(".arrow-rotate").classList.toggle("rotate-180");
            if (accordionDiv.classList.contains("flex")) {
                accordionDiv.classList.add("opacity-0");
                setTimeout(function () {
                    accordionDiv.classList.replace("flex", "hidden");
                }, 500);
            } else if (accordionDiv.classList.contains("hidden")) {
                accordionDiv.classList.replace("hidden", "flex");
                setTimeout(function () {
                    accordionDiv.classList.remove("opacity-0");
                }, 100);
                accordionDiv.scrollIntoView({ behavior: "smooth", block: "center" });
            }
        }

        if (e.target.closest(".remove-multi-keyword-box")) {
            var btn = e.target.closest(".remove-multi-keyword-box");
            var parent = btn.closest(".multi-keyword-url-box");
            parent.remove();
            updateMultiBoxLabels();
        }
    });

    wrap.addEventListener(
        "input",
        function (e) {
            var current = e.target;
            if (!current.classList.contains("multi-keyword-url-box-quantity")) {
                return;
            }
            if (current.value.trim() === "" || isNaN(parseInt(current.value, 10))) {
                current.value = "0";
            }
            var currentValue = parseInt(current.value, 10);
            var allInputs = Array.from(wrap.querySelectorAll(".multi-keyword-url-box-quantity"));
            var sumOther = allInputs
                .filter(function (inp) {
                    return inp !== current;
                })
                .reduce(function (t, inp) {
                    return t + parseInt(inp.value || "0", 10);
                }, 0);
            if (sumOther + currentValue > postCount) {
                alert("Total exceeds allowed limit (" + postCount + ")");
                current.value = String(Math.max(0, postCount - sumOther));
            }
        },
        true
    );

    if (container) {
        container.addEventListener("click", function (e) {
            if (!e.target.closest(addMoreSelector)) {
                return;
            }
            var span = wrap.querySelectorAll(".multi-box-count").length + 1;
            if (span > postCount) {
                alert("boxes cannot exceed the post Quantity");
                return;
            }
            var paddedNumber = pad2(span);
            if (totalSpan) {
                totalSpan.textContent = paddedNumber;
            }
            var div = document.createElement("div");
            div.className = "flex w-full flex-col multi-keyword-url-box relative";
            div.innerHTML = boxInnerHtml(paddedNumber, "0", "", [{ url: "", keyword: "" }]);
            wrap.appendChild(div);
            var attach = div.querySelector(".multi-url-keyword-attachment-box");
            initSortableOnAttachment(attach);
            allowOnlyNumbers(div.querySelectorAll(".multi-keyword-url-box-quantity"));
            updateMultiBoxLabels();
            div.scrollIntoView({ behavior: "smooth", block: "end" });
        });
    }

    form.addEventListener("submit", function (e) {
        if (postCount === 0) {
            e.preventDefault();
            return;
        }

        var noFollowEl = document.getElementById(id("noFollow", "edit-no-follow"));
        var noFollow = noFollowEl && noFollowEl.checked;
        var multiKeyWordUrlBoxes = wrap.getElementsByClassName("multi-keyword-url-box");
        var multiData = [];
        var keyUrlValidation = [];

        Array.from(multiKeyWordUrlBoxes).forEach(function (item, indx) {
            var singleBoxData = {};
            singleBoxData.media = (item.querySelector(".multiple-box-media-link") || {}).value || "";
            var urlInpVal = Array.from(item.querySelectorAll(".multi-url-inp")).map(function (el) {
                return el.value.trim();
            });
            var keywordInpVal = Array.from(item.querySelectorAll(".multi-keyowrd-inp")).map(function (el) {
                return el.value.trim();
            });
            if (urlInpVal.indexOf("") !== -1 || keywordInpVal.indexOf("") !== -1) {
                keyUrlValidation.push(indx + 1);
            }
            singleBoxData.url = urlInpVal;
            singleBoxData.keyword = keywordInpVal;
            var qtyInp = item.querySelector(".multi-keyword-url-box-quantity");
            var quantity = parseInt((qtyInp && qtyInp.value) || "0", 10) || 0;
            singleBoxData.nofollow = noFollow;
            for (var z = 0; z < quantity; z++) {
                multiData.push(singleBoxData);
            }
        });

        if (keyUrlValidation.length > 0) {
            e.preventDefault();
            alert(
                "the " +
                    (keyUrlValidation.length > 1 ? "boxes " : "box ") +
                    keyUrlValidation.join(",") +
                    " have empty urls & keywords"
            );
            return;
        }
        if (multiData.length !== postCount) {
            e.preventDefault();
            alert("please add proper quantity in boxes or add boxes according to Post Quantity");
            return;
        }

        holder.value = JSON.stringify(multiData);
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    });
})();
