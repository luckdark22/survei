<?php
// admin/includes/footer.php
?>
            </div> <!-- End Main Content Scroll Area -->

            <footer class="px-8 py-8 border-t border-slate-200 mt-auto bg-white/50">
                <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">
                        &copy; <?php echo date('Y'); ?> Portal Survei Pelayanan - <?php echo htmlspecialchars($global_instansi_name); ?>
                    </p>
                    <div class="flex items-center gap-4 text-[10px] font-black text-slate-300 uppercase tracking-widest">
                        <span>Admin v2.0</span>
                        <div class="w-1 h-1 bg-slate-200 rounded-full"></div>
                        <span>Secure Access</span>
                    </div>
                </div>
            </footer>

        </div> <!-- End Content Utility + Area Wrapper -->
    </div> <!-- End Outer Sidebar + Content Flex -->

    <?php if (isset($extra_footer)) echo $extra_footer; ?>
</body>
</html>
