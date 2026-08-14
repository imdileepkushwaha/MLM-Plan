<?php
echo '</main></div></div>';
?>
<script src="../assets/js/admin.js?v=<?= (int) @filemtime(__DIR__ . '/../../assets/js/admin.js') ?>"></script>
<script>
(function () {
    var btn = document.getElementById('menuToggle');
    var sb = document.getElementById('sidebar');
    if (btn && sb) {
        btn.addEventListener('click', function () {
            sb.classList.toggle('open');
            document.body.classList.toggle('sidebar-open');
        });
    }
})();
</script>
</body>
</html>
