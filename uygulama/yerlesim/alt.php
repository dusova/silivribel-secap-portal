
        </div>
        <div class="text-center py-3" style="font-size:.7rem; color:var(--yazi-ucuncul); border-top:1px solid var(--kenar-acik);">
            &copy; <?= date('Y') ?> T.C. Silivri Belediyesi Bilgi İşlem Müdürlüğü
        </div>
    </div>
</div>

<script src="<?= $B ?>/varliklar/kutuphaneler/bootstrap/bootstrap.bundle.min.js"></script>
<script>
(function() {
    var btn = document.getElementById('kenarDaraltBtn');
    var sidebar = document.getElementById('kenar-cubugu');
    if (!btn || !sidebar) return;

    function updateBtn(isCollapsed) {
        btn.style.left = isCollapsed ? '58px' : '';
        btn.querySelector('i').style.transform = isCollapsed ? 'rotate(180deg)' : '';
    }

    var collapsed = localStorage.getItem('sb_collapsed') === '1';
    if (collapsed) {
        sidebar.classList.add('daraltilmis');
        updateBtn(true);
    }

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        sidebar.classList.toggle('daraltilmis');
        var isCollapsed = sidebar.classList.contains('daraltilmis');
        localStorage.setItem('sb_collapsed', isCollapsed ? '1' : '0');
        updateBtn(isCollapsed);
    });
})();
</script>
<script src="<?= $B ?>/varliklar/kutuphaneler/chartjs/chart.umd.min.js"></script>
<?php if (Auth::isLoggedIn()): ?>
<script>
(function() {
    const bell = document.getElementById('notifBell');
    if (!bell) return;
    const countEl  = bell.querySelector('[data-notif-count]');
    const listEl   = bell.querySelector('[data-notif-list]');
    const pollUrl  = <?= json_encode(BASE_PATH . '/bildirimler?json=1') ?>;
    const pageUrl  = <?= json_encode(BASE_PATH . '/bildirimler') ?>;
    const badgeEls = document.querySelectorAll('[data-notif-badge]');

    function escapeHtml(str) {
        return String(str == null ? '' : str).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[m]));
    }

    function safeHref(link) {
        if (!link) return pageUrl;
        const s = String(link);
        if (s.charAt(0) === '/' && s.charAt(1) !== '/') return escapeHtml(s);
        return pageUrl;
    }

    function renderItems(items) {
        if (!items || items.length === 0) {
            listEl.innerHTML = '<div class="text-center text-muted small py-3"><i class="bi bi-inbox me-1"></i>Yeni bildirim yok.</div>';
            return;
        }
        listEl.innerHTML = items.map(function(n) {
            const bg   = n.is_read ? '' : 'bg-primary-subtle';
            const href = safeHref(n.link);
            return ''
                + '<a href="' + href + '" class="d-flex gap-2 align-items-start text-decoration-none text-reset px-3 py-2 border-bottom ' + bg + '">'
                +   '<span class="badge bg-' + escapeHtml(n.type_cls) + '-subtle text-' + escapeHtml(n.type_cls) + ' mt-1">'
                +     '<i class="bi ' + escapeHtml(n.icon) + '"></i>'
                +   '</span>'
                +   '<div class="flex-grow-1" style="min-width:0;">'
                +     '<div class="fw-semibold small text-truncate">' + escapeHtml(n.title) + '</div>'
                +     '<div class="text-muted small text-truncate">' + escapeHtml(n.message) + '</div>'
                +     '<div class="text-muted" style="font-size:.7rem;">' + escapeHtml(n.created_at) + '</div>'
                +   '</div>'
                + '</a>';
        }).join('');
    }

    function setCount(n) {
        const v = parseInt(n, 10) || 0;
        if (countEl) {
            countEl.textContent = v > 99 ? '99+' : String(v);
            countEl.style.display = v > 0 ? '' : 'none';
        }
        badgeEls.forEach(function(el) {
            el.textContent = String(v);
            el.style.display = v > 0 ? '' : 'none';
        });
    }

    function poll() {
        fetch(pollUrl, { credentials: 'same-origin', cache: 'no-store' })
            .then(r => r.ok ? r.json() : null)
            .then(data => {
                if (!data) return;
                setCount(data.unread);
                renderItems(data.items);
            })
            .catch(() => {});
    }

    poll();
    setInterval(poll, 30000);
})();
</script>
<?php endif; ?>
<?php if (!empty($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>
</body>
</html>
