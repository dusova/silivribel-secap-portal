
        </div>
        <div class="text-center py-3" style="font-size:.7rem; color:var(--text-tertiary); border-top:1px solid var(--border-light);">
            &copy; <?= date('Y') ?> T.C. Silivri Belediyesi Bilgi İşlem Müdürlüğü
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    var btn = document.getElementById('sidebarCollapseBtn');
    var sidebar = document.getElementById('sidebar');
    if (!btn || !sidebar) return;

    function updateBtn(isCollapsed) {
        btn.style.left = isCollapsed ? '58px' : '';
        btn.querySelector('i').style.transform = isCollapsed ? 'rotate(180deg)' : '';
    }

    var collapsed = localStorage.getItem('sb_collapsed') === '1';
    if (collapsed) {
        sidebar.classList.add('collapsed');
        updateBtn(true);
    }

    btn.addEventListener('click', function(e) {
        e.preventDefault();
        sidebar.classList.toggle('collapsed');
        var isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sb_collapsed', isCollapsed ? '1' : '0');
        updateBtn(isCollapsed);
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<?php if (!empty($extraJs)): ?>
    <?= $extraJs ?>
<?php endif; ?>
</body>
</html>
