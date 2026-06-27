/* ============================================================
   PPMIS – Main JavaScript
   ============================================================ */

// ── Sidebar Toggle ──
(function () {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (toggle && sidebar) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
        // Close sidebar when clicking outside
        document.addEventListener('click', (e) => {
            if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }
})();

// ── Toast Notification System ──
const Toast = {
    container: null,

    init() {
        this.container = document.querySelector('.toast-container');
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            document.body.appendChild(this.container);
        }
    },

    show(message, type = 'info', duration = 3500) {
        if (!this.container) this.init();
        const icons = { info: 'bi-info-circle', success: 'bi-check-circle', error: 'bi-x-circle' };
        const el = document.createElement('div');
        el.className = `toast-ppmis ${type}`;
        el.innerHTML = `<i class="bi ${icons[type] || icons.info}"></i><span>${message}</span>`;
        this.container.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(100%)';
            el.style.transition = '0.3s ease';
            setTimeout(() => el.remove(), 300);
        }, duration);
    }
};

// ── Generic File Upload Handler ──
function initDocUpload(docType, inputId, previewBtnId, rowId) {
    const input = document.getElementById(inputId);
    const row   = rowId ? document.getElementById(rowId) : null;

    if (!input) return;

    input.addEventListener('change', function () {
        if (!this.files || !this.files[0]) return;

        const file = this.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!allowedTypes.includes(file.type)) {
            Toast.show('Invalid file type. Use JPG, PNG, or PDF.', 'error');
            this.value = '';
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            Toast.show('File exceeds 10MB limit.', 'error');
            this.value = '';
            return;
        }

        if (row) {
            row.classList.add('has-file');
            const statusIcon = row.querySelector('.doc-status-icon');
            if (statusIcon) statusIcon.style.display = 'block';
        }

        Toast.show(`${file.name} selected.`, 'success');
    });
}

// ── Preview Modal ──
function previewFile(filePath, fileName) {
    const overlay = document.createElement('div');
    overlay.className = 'img-modal-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-label', 'File Preview');

    const isPdf = (fileName || filePath || '').toLowerCase().endsWith('.pdf');
    const content = isPdf
        ? `<embed src="${filePath}" type="application/pdf" width="800" height="600" />`
        : `<img src="${filePath}" alt="Preview" />`;

    overlay.innerHTML = `
        <div class="img-modal-body">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                <strong style="font-size:14px;">${fileName || 'Preview'}</strong>
                <button onclick="this.closest('.img-modal-overlay').remove()"
                    style="border:none;background:none;font-size:24px;cursor:pointer;color:#999;">&times;</button>
            </div>
            ${content}
        </div>`;

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.remove();
    });

    document.body.appendChild(overlay);
}

// ── PDC Modal ──
const PDCModal = {
    data: [], // array of {file, date, amount}

    open(projectId) {
        const existing = document.getElementById('pdcModalOverlay');
        if (existing) existing.remove();

        // Build up to 36 PDC slots (show 4 minimum, can add more)
        const overlay = document.createElement('div');
        overlay.className = 'pdc-modal-overlay';
        overlay.id = 'pdcModalOverlay';

        overlay.innerHTML = `
            <div class="pdc-modal" role="dialog" aria-label="PDC Mass Submission">
                <div class="pdc-modal-title">PDC Mass Submission</div>
                <div id="pdcCardList"></div>
                <div style="display:flex;gap:10px;margin-top:8px;">
                    <button class="btn-upload" style="flex:1;" onclick="PDCModal.addCard()">
                        <i class="bi bi-plus-circle"></i> Add PDC
                    </button>
                    <button class="btn-ppmis-primary" style="flex:1;" onclick="PDCModal.submit(${projectId})">
                        <i class="bi bi-check-lg"></i> Done
                    </button>
                </div>
            </div>`;

        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
        document.body.appendChild(overlay);

        // Add initial 4 cards
        PDCModal.data = [];
        for (let i = 1; i <= 36; i++) PDCModal.addCard();
    },

    addCard() {
        const list  = document.getElementById('pdcCardList');
        const index = list.children.length + 1;
        const card  = document.createElement('div');
        card.className = 'pdc-card';
        card.dataset.index = index;
        card.innerHTML = `
            <div class="pdc-card-header">
                <span class="pdc-card-title">PDC ${index}</span>
                <div style="display:flex;gap:6px;">
                    <button class="btn-upload" onclick="PDCModal.triggerFile(${index})">
                        <i class="bi bi-upload"></i> Insert Image
                    </button>
                    <button class="btn-preview" onclick="PDCModal.previewCard(${index})">
                        <i class="bi bi-eye"></i> Preview
                    </button>
                </div>
            </div>
            <input type="file" id="pdcFile_${index}" accept="image/*,.pdf" style="display:none;"
                   onchange="PDCModal.fileSelected(${index}, this)">
            <div class="pdc-inputs">
                <div class="pdc-input-group">
                    <label>Date:</label>
                    <input type="date" id="pdcDate_${index}" oninput="PDCModal.calcAdjusted(${index})" />
                </div>
                <div class="pdc-input-group">
                    <label>Amount:</label>
                    <input type="number" id="pdcAmt_${index}" placeholder="0.00" step="0.01" min="0" />
                </div>
            </div>
            <div id="pdcAdj_${index}" style="font-size:11px;color:#fff;margin-top:6px;display:none;">
                Adjusted date (end of month): <strong id="pdcAdjVal_${index}"></strong>
            </div>`;
        list.appendChild(card);
    },

    triggerFile(index) {
        document.getElementById(`pdcFile_${index}`)?.click();
    },

    fileSelected(index, input) {
        if (input.files && input.files[0]) {
            Toast.show(`PDC ${index}: ${input.files[0].name} selected.`, 'success');
        }
    },

    calcAdjusted(index) {
        const dateVal = document.getElementById(`pdcDate_${index}`)?.value;
        if (!dateVal) return;
        // End of month adjustment
        const d = new Date(dateVal);
        const lastDay = new Date(d.getFullYear(), d.getMonth() + 1, 0);
        const adj = lastDay.toISOString().split('T')[0];
        const adjDiv = document.getElementById(`pdcAdj_${index}`);
        const adjVal = document.getElementById(`pdcAdjVal_${index}`);
        if (adjDiv) adjDiv.style.display = 'block';
        if (adjVal) adjVal.textContent = adj;
    },

    previewCard(index) {
        const file = document.getElementById(`pdcFile_${index}`)?.files?.[0];
        if (!file) { Toast.show('No file selected for PDC ' + index, 'error'); return; }
        const url = URL.createObjectURL(file);
        previewFile(url, file.name);
    },

    submit(projectId) {
        const list  = document.getElementById('pdcCardList');
        const cards = list.querySelectorAll('.pdc-card');
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name=csrf_token]')?.value || '');
        formData.append('project_id', projectId);

        let valid = 0;
        cards.forEach((card, idx) => {
            const i = idx + 1;
            const file = document.getElementById(`pdcFile_${i}`)?.files?.[0];
            const date = document.getElementById(`pdcDate_${i}`)?.value;
            const amt  = document.getElementById(`pdcAmt_${i}`)?.value;
            if (file && date && amt) {
                formData.append(`pdc_file_${i}`, file);
                formData.append(`pdc_date_${i}`, date);
                formData.append(`pdc_amount_${i}`, amt);
                valid++;
            }
        });

        if (valid === 0) {
            Toast.show('Please fill in at least one PDC with file, date, and amount.', 'error');
            return;
        }

        fetch(window.APP_BASE + '/api/submit_pdcs.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Toast.show(`${valid} PDC(s) submitted successfully!`, 'success');
                    document.getElementById('pdcModalOverlay')?.remove();
                    // Mark PDCs row as done
                    const pdcRow = document.getElementById('doc-row-pdc');
                    if (pdcRow) pdcRow.classList.add('has-file');
                    // Reload after short delay
                    setTimeout(() => location.reload(), 1500);
                } else {
                    Toast.show(data.error || 'Submission failed.', 'error');
                }
            })
            .catch(() => Toast.show('Network error. Please try again.', 'error'));
    }
};

// ── Refund Row: Notify & Done ──
function notifyRefund(refundId, btn) {
    fetch(window.APP_BASE + '/api/refund_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'notify',
            refund_id: refundId,
            csrf_token: document.querySelector('[name=csrf_token]')?.value
        })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            btn.classList.add('notified');
            btn.textContent = 'Notified';
            btn.disabled = true;
            Toast.show('Notification sent.', 'success');
        } else {
            Toast.show(data.error || 'Failed.', 'error');
        }
    });
}

function markRefundDone(refundId, checkBtn) {
    fetch(window.APP_BASE + '/api/refund_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'done',
            refund_id: refundId,
            csrf_token: document.querySelector('[name=csrf_token]')?.value
        })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            checkBtn.classList.add('checked');
            checkBtn.innerHTML = '<i class="bi bi-check-lg"></i>';
            const row = checkBtn.closest('.refund-row');
            if (row) row.classList.add('is-done');
            Toast.show('Marked as done.', 'success');
        } else {
            Toast.show(data.error || 'Failed.', 'error');
        }
    });
}

// ── Search Filter ──
function initSearch(inputId, tableBodyId) {
    const input = document.getElementById(inputId);
    const tbody = document.getElementById(tableBodyId);
    if (!input || !tbody) return;

    input.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        Array.from(tbody.rows).forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(q) ? '' : 'none';
        });
    });
}

// ── Dashboard Doughnut Chart ──
function initRefundChart(paid, pending) {
    const canvas = document.getElementById('refundChart');
    if (!canvas) return;
    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: ['Paid', 'Pending'],
            datasets: [{
                data: [paid, pending],
                backgroundColor: ['#0F4C81', '#E87722'],
                borderWidth: 0,
                hoverOffset: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { font: { size: 12 }, padding: 12, boxWidth: 14 }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => ` ${ctx.label}: ${ctx.parsed}%`
                    }
                }
            }
        }
    });
}
