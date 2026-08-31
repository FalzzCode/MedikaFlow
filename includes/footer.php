        </main>
        <footer class="app-footer">
            <span><?= e(app_brand_name()) ?></span>
            <span>·</span>
            <span>Data klinik lebih mudah dipahami.</span>
        </footer>
    </div>
</div>
<div class="confirm-dialog-layer" data-confirm-dialog hidden>
    <div class="confirm-dialog-backdrop" data-confirm-cancel></div>
    <section class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message" tabindex="-1">
        <div class="confirm-dialog-topline"></div>
        <div class="confirm-dialog-head">
            <span class="confirm-dialog-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.8 20h18.4L12 3Z"></path><path d="M12 9v5"></path><path d="M12 17h.01"></path></svg>
            </span>
            <div class="confirm-dialog-heading">
                <span class="confirm-dialog-kicker">TINDAKAN PERLU KONFIRMASI</span>
                <h2 id="confirm-dialog-title">Konfirmasi tindakan</h2>
            </div>
            <button class="confirm-dialog-close" type="button" data-confirm-cancel aria-label="Tutup konfirmasi">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m18 6-12 12"></path><path d="m6 6 12 12"></path></svg>
            </button>
        </div>
        <p class="confirm-dialog-message" id="confirm-dialog-message"></p>
        <div class="confirm-dialog-note">
            <span class="confirm-dialog-note-icon" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.8 20h18.4L12 3Z"></path><path d="M12 9v5"></path><path d="M12 17h.01"></path></svg>
            </span>
            <span class="confirm-dialog-note-text">Periksa kembali pilihanmu. Data yang sudah diproses mungkin tidak dapat dikembalikan.</span>
        </div>
        <div class="confirm-dialog-actions">
            <button class="button button-secondary" type="button" data-confirm-cancel>Batalkan</button>
            <button class="button button-danger confirm-dialog-accept" type="button" data-confirm-accept>Ya, lanjutkan</button>
        </div>
    </section>
</div>
<script>
    window.APP_BASE_URL = <?= json_encode(base_url()) ?>;
</script>
<script src="<?= e(base_url('assets/js/app.js?v=20260830-8')) ?>"></script>
</body>
</html>
