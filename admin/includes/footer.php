<?php
// admin/includes/footer.php
?>
    <footer class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 border-t border-slate-200 mt-10 text-center">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">
            &copy; <?php echo date('Y'); ?> Portal Survei Pelayanan - <?php echo htmlspecialchars($global_instansi_name); ?>
        </p>
    </footer>

    <?php if (isset($extra_footer)) echo $extra_footer; ?>
</body>
</html>
