(function (global) {
    function parseD(s) {
        if (!s || typeof s !== 'string') return null;
        var p = s.trim().split('-');
        return p.length === 3
            ? new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10))
            : null;
    }

    function fmtD(d) {
        var y = d.getFullYear();
        var m = ('0' + (d.getMonth() + 1)).slice(-2);
        var day = ('0' + d.getDate()).slice(-2);
        return y + '-' + m + '-' + day;
    }

    function fmtDisplay(ymd) {
        var d = typeof ymd === 'string' ? parseD(ymd) : ymd;
        if (!d) return ymd || '';
        var day = ('0' + d.getDate()).slice(-2);
        var m = ('0' + (d.getMonth() + 1)).slice(-2);
        return day + '-' + m + '-' + d.getFullYear();
    }

    function startDateFromOffset(offset, todayStr) {
        var today = todayStr ? parseD(todayStr) : new Date();
        if (!today) today = new Date();
        today.setHours(0, 0, 0, 0);
        var days = Math.max(0, parseInt(offset, 10) || 0);
        today.setDate(today.getDate() - days);
        return fmtD(today);
    }

    function buildPerDayRows(fromStr, total, perDay) {
        total = parseInt(total, 10);
        perDay = parseInt(perDay, 10);
        var from = parseD(fromStr);
        if (!from || total < 1 || perDay < 1) return [];

        var remaining = total;
        var rows = [];
        var cur = new Date(from.getTime());
        while (remaining > 0) {
            var qty = Math.min(perDay, remaining);
            rows.push({ date: fmtD(cur), quantity: qty });
            remaining -= qty;
            cur.setDate(cur.getDate() + 1);
        }
        return rows;
    }

    function bind(opts) {
        var generateBtn = opts.generateBtn;
        var perDayBtn = opts.perDayBtn;
        var fromInp = opts.fromInp;
        var toInp = opts.toInp;
        var qtyInp = opts.qtyInp;
        var distDiv = opts.distDiv;
        var tbody = opts.tbody;
        var sumEl = opts.sumEl;
        var dateQuantitiesInp = opts.dateQuantitiesInp;
        var qtyClass = opts.qtyClass;
        var perDayInp = opts.perDayInp;
        var offsetInp = opts.offsetInp;
        var form = opts.form;

        if (!generateBtn || generateBtn._schDateBound) return;
        if (!fromInp || !toInp || !distDiv || !tbody || !sumEl || !dateQuantitiesInp) return;

        function updateSum() {
            var inputs = tbody.querySelectorAll('.' + qtyClass);
            var sum = 0;
            var arr = [];
            inputs.forEach(function (inp) {
                var v = parseInt(inp.value, 10) || 0;
                sum += v;
                arr.push({ date: inp.getAttribute('data-date'), quantity: v });
            });
            sumEl.textContent = sum;
            dateQuantitiesInp.value = JSON.stringify(arr);
            if (qtyInp) qtyInp.value = sum;
        }

        function renderRows(rows, defaultQty) {
            distDiv.style.display = 'block';
            tbody.innerHTML = '';
            rows.forEach(function (row) {
                var qty = typeof row.quantity === 'number' ? row.quantity : defaultQty;
                var tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';
                tr.innerHTML =
                    '<td class="border !px-2 !py-2">' +
                    fmtDisplay(row.date) +
                    '</td><td class="border !px-2 !py-2"><input type="number" min="0" class="' +
                    qtyClass +
                    ' border border-gray-300 rounded !px-2 !py-1 w-24" data-date="' +
                    row.date +
                    '" value="' +
                    qty +
                    '"></td>';
                tbody.appendChild(tr);
            });
            tbody.querySelectorAll('.' + qtyClass).forEach(function (inp) {
                inp.addEventListener('input', updateSum);
                inp.addEventListener('change', updateSum);
            });
            updateSum();
        }

        function buildRange() {
            var fromStr = (fromInp.value || '').trim();
            var toStr = (toInp.value || '').trim();
            if (!fromStr || !toStr) {
                alert('Please select both From date and To date.');
                return;
            }
            var from = parseD(fromStr);
            var to = parseD(toStr);
            if (!from || !to) {
                alert('Invalid date format.');
                return;
            }
            if (to < from) {
                alert('To date must be on or after From date.');
                return;
            }
            var rows = [];
            var cur = new Date(from.getTime());
            while (cur <= to) {
                rows.push({ date: fmtD(cur), quantity: 0 });
                cur.setDate(cur.getDate() + 1);
            }
            renderRows(rows, 0);
        }

        function buildPerDay() {
            var total = parseInt((qtyInp && qtyInp.value) ? qtyInp.value : '0', 10) || 0;
            var perDay = parseInt((perDayInp && perDayInp.value) ? perDayInp.value : '0', 10) || 0;
            var offset = parseInt((offsetInp && offsetInp.value) ? offsetInp.value : '0', 10) || 0;
            if (total < 1) {
                alert('Please enter quantity (at least 1).');
                return;
            }
            if (perDay < 1) {
                alert('Please enter per day (at least 1).');
                return;
            }
            if (offset < 0) {
                alert('Offset cannot be negative.');
                return;
            }
            var fromStr = startDateFromOffset(offset);
            var rows = buildPerDayRows(fromStr, total, perDay);
            if (!rows.length) {
                alert('Could not build the date table.');
                return;
            }
            fromInp.value = rows[0].date;
            toInp.removeAttribute('min');
            if (fromInp.value) {
                toInp.setAttribute('min', fromInp.value);
            }
            toInp.value = rows[rows.length - 1].date;
            renderRows(rows);
        }

        generateBtn.addEventListener('click', buildRange);
        generateBtn._schDateBound = true;
        if (perDayBtn) perDayBtn.addEventListener('click', buildPerDay);

        function syncToMin() {
            var v = (fromInp.value || '').trim();
            if (v) toInp.setAttribute('min', v);
        }
        fromInp.addEventListener('change', syncToMin);
        fromInp.addEventListener('input', syncToMin);
        syncToMin();

        if (form) {
            form.addEventListener('submit', function () {
                var rows = tbody.querySelectorAll('.' + qtyClass);
                if (rows.length) {
                    var arr = [];
                    rows.forEach(function (inp) {
                        arr.push({
                            date: inp.getAttribute('data-date'),
                            quantity: parseInt(inp.value, 10) || 0,
                        });
                    });
                    dateQuantitiesInp.value = JSON.stringify(arr);
                    if (qtyInp) {
                        qtyInp.value = arr.reduce(function (s, r) {
                            return s + r.quantity;
                        }, 0);
                    }
                }
            });
        }
    }

    global.ScheduleDateDistribution = {
        parseD: parseD,
        fmtD: fmtD,
        fmtDisplay: fmtDisplay,
        startDateFromOffset: startDateFromOffset,
        buildPerDayRows: buildPerDayRows,
        bind: bind,
    };
})(window);
