/**
 * Schedule sidebar campaign edit — "Add keyword & Url" normal tab (create-campaign-style boxes).
 * Expands boxes to batch_* arrays aligned with link row order (id ASC).
 */
(function () {
    "use strict";

    function parseIntSafe(v) {
        var n = parseInt(String(v || "").trim(), 10);
        return isNaN(n) ? 0 : n;
    }

    function getTotalLinkCount() {
        var links = window.__ssEditLinksForNormal;
        return Array.isArray(links) ? links.length : 0;
    }

    /**
     * Within one URL block (same order as DB rows), merge consecutive rows that share the same keyword
     * (trimmed equality) into one textarea line with quantity = run length — same UX as manual entry
     * (e.g. "claude" + 10, "grok ai" + 10).
     */
    function collapseConsecutiveKeywords(slice) {
        var lines = [];
        var quantities = [];
        var k = 0;
        while (k < slice.length) {
            var firstKw = slice[k].keyword || "";
            var norm = String(firstKw).trim();
            var runEnd = k + 1;
            while (
                runEnd < slice.length &&
                String(slice[runEnd].keyword || "").trim() === norm
            ) {
                runEnd++;
            }
            lines.push(firstKw);
            quantities.push(String(runEnd - k));
            k = runEnd;
        }
        return {
            keywordsText: lines.join("\n"),
            quantityText: quantities.join("\n"),
        };
    }

    function groupLinksToBoxes(links) {
        var boxes = [];
        var i = 0;
        while (i < links.length) {
            var url = links[i].url || "";
            var j = i;
            while (j < links.length && (links[j].url || "") === url) {
                j++;
            }
            var slice = links.slice(i, j);
            var collapsed = collapseConsecutiveKeywords(slice);
            boxes.push({
                url: url,
                urlQuantity: slice.length,
                keywordsText: collapsed.keywordsText,
                quantityText: collapsed.quantityText,
            });
            i = j;
        }
        return boxes;
    }

    function updateBoxCount(container) {
        var boxes = container.querySelectorAll(".keyword-url-box");
        boxes.forEach(function (box, idx) {
            var bc = box.querySelector(".box-count");
            if (bc) {
                var num = idx + 1;
                bc.textContent = num < 10 ? "0" + num : String(num);
            }
        });
        var totalEl = document.getElementById("ss-total-box-count");
        if (totalEl) {
            totalEl.textContent = String(boxes.length);
        }
    }

    function updateBorders(container) {
        var boxes = container.querySelectorAll(".keyword-url-box");
        boxes.forEach(function (box) {
            box.classList.remove("rounded-b-lg", "rounded-b", "rounded-t-lg");
        });
        if (boxes.length) {
            boxes[0].classList.add("rounded-t-lg");
            boxes[boxes.length - 1].classList.add("rounded-b-lg");
        }
    }

    function updateKeywordQuantity(input, val) {
        var parentBox = input.closest(".keyword-url-box");
        if (!parentBox) {
            return;
        }
        var keywordsArea = parentBox.querySelector(".keywords-area");
        var kQuantityArea = parentBox.querySelector(".keywords-quantity-area");
        if (!kQuantityArea) {
            return;
        }
        if (keywordsArea) {
            var keywordCount = keywordsArea.value.split("\n").filter(function (line) {
                return line.trim() !== "";
            }).length;
            if (keywordCount > 0) {
                var inputVal = parseIntSafe(input.value);
                var keyQuantityArr = [];
                var k;
                if (inputVal > keywordCount) {
                    var base = Math.floor(inputVal / keywordCount);
                    var remainder = inputVal % keywordCount;
                    for (k = 0; k < keywordCount; k++) {
                        keyQuantityArr[k] = base + (k < remainder ? 1 : 0);
                    }
                } else {
                    for (k = 0; k < keywordCount; k++) {
                        keyQuantityArr[k] = k < inputVal ? 1 : 0;
                    }
                }
                kQuantityArea.value = keyQuantityArr.join("\n");
                return;
            }
        }
        kQuantityArea.value = String(val);
    }

    function delegateQuantityAndKeywords(container, totalLinks) {
        container.addEventListener("input", function (e) {
            var t = e.target;
            if (t.classList && t.classList.contains("client-url-quantity")) {
                var cleaned = String(t.value || "").replace(/\D/g, "");
                if (t.value !== cleaned) {
                    t.value = cleaned;
                }
                var all = container.querySelectorAll(".client-url-quantity");
                var currentIndex = Array.prototype.indexOf.call(all, t);
                var usedPost = Array.from(all).reduce(function (sum, num, idx) {
                    if (idx !== currentIndex) {
                        return sum + parseIntSafe(num.value);
                    }
                    return sum;
                }, 0);
                var val = parseIntSafe(t.value);
                var maxAllowed = totalLinks - usedPost;
                if (val > maxAllowed) {
                    alert("URL row count cannot exceed remaining rows: " + maxAllowed);
                    t.value = String(maxAllowed);
                    val = maxAllowed;
                }
                updateKeywordQuantity(t, val);
                return;
            }
            if (t.classList && t.classList.contains("keywords-area")) {
                var text = t.value;
                var lines = text.split("\n").filter(function (line) {
                    return line.trim() !== "";
                });
                var keywordCount = lines.length;
                if (keywordCount === 0) {
                    return;
                }
                var box = t.closest(".keyword-url-box");
                if (!box) {
                    return;
                }
                var qtyInp = box.querySelector(".client-url-quantity");
                var inputNum = parseIntSafe(qtyInp && qtyInp.value);
                var keyArr = [];
                var i;
                if (inputNum > keywordCount) {
                    var base = Math.floor(inputNum / keywordCount);
                    var remainder = inputNum % keywordCount;
                    for (i = 0; i < keywordCount; i++) {
                        keyArr[i] = base + (i < remainder ? 1 : 0);
                    }
                } else {
                    for (i = 0; i < keywordCount; i++) {
                        keyArr[i] = i < inputNum ? 1 : 0;
                    }
                }
                var boxContent = box.querySelector(".keywords-quantity-area");
                if (boxContent) {
                    boxContent.value = keyArr.join("\n");
                }
            }
        });
    }

    function boxTemplate() {
        var div = document.createElement("div");
        div.className =
            "flex w-full bg-orange-100 keyword-url-box edit-kw-url-card relative";
        div.innerHTML =
            '<div class="w-3/5 flex flex-col gap-2 !p-4 !pt-[45px] kw-url-left-col">' +
            '<div class="w-full flex items-center">' +
            '<label class="text-sm flex items-center after:content-[\'*\'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-1/5">Client Url</label>' +
            '<div class="w-4/5 flex items-center justify-between gap-1">' +
            '<input type="text" placeholder="Enter Url" class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-[78%] min-w-0 client-url">' +
            '<input type="text" value="0" class="bg-gray-50 !p-2 text-sm outline-none text-center border border-gray-300 w-1/5 min-w-[2.5rem] client-url-quantity num-inp" inputmode="numeric" autocomplete="off">' +
            "</div></div>" +
            '<div class="w-full flex items-center justify-end">' +
            '<button type="button" class="!p-1 bg-red-600 rounded text-sm cursor-pointer text-white remove-keyword-box">delete</button>' +
            "</div></div>" +
            '<div class="w-2/5 flex flex-col gap-1 !p-4 kw-url-right-col border-l border-orange-200/80">' +
            '<label class="text-sm flex items-center after:content-[\'*\'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Client Keyword</label>' +
            '<div class="w-full flex flex-wrap justify-between keywords-area-parent">' +
            '<div class="w-[78%] flex flex-wrap min-w-0">' +
            '<textarea rows="5" class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none keywords-area"></textarea>' +
            "</div>" +
            '<div class="w-1/5 flex flex-wrap min-w-[2.5rem]">' +
            '<textarea rows="5" class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none text-center keywords-quantity-area focus:border-[var(--primary-color)]"></textarea>' +
            "</div></div></div>" +
            '<div class="box-count flex !px-3 !py-1 text-sm font-semibold bg-[var(--primary-color)] text-white rounded absolute top-3 left-3">01</div>';
        return div;
    }

    function appendBox(container, data) {
        var el = boxTemplate();
        if (data) {
            var u = el.querySelector(".client-url");
            var q = el.querySelector(".client-url-quantity");
            var ka = el.querySelector(".keywords-area");
            var kqa = el.querySelector(".keywords-quantity-area");
            if (u) {
                u.value = data.url || "";
            }
            if (q) {
                q.value = String(data.urlQuantity != null ? data.urlQuantity : 0);
            }
            if (ka) {
                ka.value = data.keywordsText || "";
            }
            if (kqa) {
                kqa.value = data.quantityText || "";
            }
        }
        container.appendChild(el);
        return el;
    }

    function expandBoxesToRows(container, noFollowValue) {
        var keyWordBox = container.querySelectorAll(".keyword-url-box");
        var boxData = [];
        var right = true;

        if (keyWordBox.length === 0) {
            return { ok: false, msg: "At least one URL box is required." };
        }

        var emptyUrl = false;
        var emptyKw = false;
        Array.from(container.querySelectorAll(".client-url")).forEach(function (i) {
            if (!String(i.value || "").trim()) {
                emptyUrl = true;
            }
        });
        Array.from(container.querySelectorAll(".keywords-area")).forEach(function (i) {
            if (!String(i.value || "").trim()) {
                emptyKw = true;
            }
        });
        if (emptyUrl) {
            return { ok: false, msg: "Client URL must not be empty." };
        }
        if (emptyKw) {
            return { ok: false, msg: "Client keyword area must not be empty." };
        }

        var totalLinks = getTotalLinkCount();
        var clientUrlQuantityInp = container.querySelectorAll(".client-url-quantity");
        var totalQty = Array.from(clientUrlQuantityInp).reduce(function (sum, num) {
            return sum + parseIntSafe(num.value);
        }, 0);
        if (totalQty !== totalLinks) {
            return {
                ok: false,
                msg:
                    "The sum of URL row counts must equal the campaign link count (" +
                    totalLinks +
                    "). Current sum: " +
                    totalQty +
                    ".",
            };
        }

        keyWordBox.forEach(function (box) {
            var urlQuantity = parseIntSafe(box.querySelector(".client-url-quantity") && box.querySelector(".client-url-quantity").value);
            var url = (box.querySelector(".client-url") && box.querySelector(".client-url").value) || "";
            var keywords = (box.querySelector(".keywords-area") && box.querySelector(".keywords-area").value)
                .split("\n")
                .filter(function (line) {
                    return line.trim() !== "";
                });
            var keywordsNum = (box.querySelector(".keywords-quantity-area") && box.querySelector(".keywords-quantity-area").value)
                .split("\n")
                .filter(function (line) {
                    return line.trim() !== "";
                });
            var keywordQTotal = keywordsNum.reduce(function (sum, num) {
                return sum + parseIntSafe(num);
            }, 0);
            if (keywordQTotal !== urlQuantity) {
                right = false;
                box.scrollIntoView({ behavior: "smooth", block: "center" });
                var kqa = box.querySelector(".keywords-quantity-area");
                if (kqa) {
                    kqa.focus();
                }
                return;
            }
            var keywordIndex = 0;
            var repeatCount = parseIntSafe(keywordsNum[keywordIndex]);
            var i;
            for (i = 0; i < urlQuantity; i++) {
                boxData.push({
                    url: url.trim(),
                    keyword: (keywords[keywordIndex] || "").trim(),
                    nofollow: noFollowValue,
                });
                repeatCount--;
                if (repeatCount === 0) {
                    keywordIndex++;
                    repeatCount = parseIntSafe(keywordsNum[keywordIndex]);
                }
            }
        });

        if (!right) {
            return { ok: false, msg: "Keyword quantities must add up to each URL row count." };
        }

        return { ok: true, rows: boxData };
    }

    function refreshBoxChrome(container) {
        updateBoxCount(container);
        updateBorders(container);
    }

    document.addEventListener("DOMContentLoaded", function () {
        var container = document.getElementById("ss-keyword-url-container");
        var form = document.getElementById("ss-normal-form");
        var addBtn = document.getElementById("ss-add-more-keyword-url");
        var dyn = document.getElementById("ss-dynamic-batch-fields");
        var submitBtn = document.getElementById("ss-normal-submit");

        if (!container || !form || !dyn) {
            return;
        }

        var links = window.__ssEditLinksForNormal;
        if (!Array.isArray(links) || links.length === 0) {
            return;
        }

        var totalLinks = links.length;
        var orderedIds = links.map(function (r) {
            return r.link_id;
        });

        var boxes = groupLinksToBoxes(links);
        boxes.forEach(function (b) {
            appendBox(container, b);
        });
        delegateQuantityAndKeywords(container, totalLinks);
        refreshBoxChrome(container);

        container.addEventListener("click", function (e) {
            if (!e.target.classList.contains("remove-keyword-box")) {
                return;
            }
            e.preventDefault();
            var box = e.target.closest(".keyword-url-box");
            if (!box) {
                return;
            }
            if (container.querySelectorAll(".keyword-url-box").length <= 1) {
                alert("At least one URL box is required.");
                return;
            }
            box.remove();
            refreshBoxChrome(container);
        });

        if (addBtn) {
            addBtn.addEventListener("click", function (e) {
                e.preventDefault();
                var keywordBoxCount = container.querySelectorAll(".keyword-url-box").length;
                if (keywordBoxCount >= totalLinks) {
                    alert("You cannot add more boxes than link rows (" + totalLinks + ").");
                    return;
                }
                appendBox(container, null);
                refreshBoxChrome(container);
            });
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            dyn.innerHTML = "";

            var expanded = expandBoxesToRows(container, "");
            if (!expanded.ok) {
                alert(expanded.msg || "Validation failed.");
                return;
            }
            if (expanded.rows.length !== orderedIds.length) {
                alert("Internal error: expanded row count mismatch.");
                return;
            }

            var j;
            for (j = 0; j < expanded.rows.length; j++) {
                var row = expanded.rows[j];
                if (!row.url || !row.keyword) {
                    alert("Each row needs a non-empty URL and keyword.");
                    return;
                }
                var hidId = document.createElement("input");
                hidId.type = "hidden";
                hidId.name = "batch_representative_link_id[]";
                hidId.value = String(orderedIds[j]);
                dyn.appendChild(hidId);
                var hidU = document.createElement("input");
                hidU.type = "hidden";
                hidU.name = "batch_url[]";
                hidU.value = row.url;
                dyn.appendChild(hidU);
                var hidK = document.createElement("input");
                hidK.type = "hidden";
                hidK.name = "batch_keyword[]";
                hidK.value = row.keyword;
                dyn.appendChild(hidK);
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = "Saving...";
            }
            form.submit();
        });
    });
})();
