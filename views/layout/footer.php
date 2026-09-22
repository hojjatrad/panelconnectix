        </div> <!-- End p-6 space-y-6 -->
    </main>

    <!-- Global Scripts -->
    <script>
        function copyToClipboard(text, btnElement) {
            navigator.clipboard.writeText(text).then(function() {
                const originalHtml = btnElement.innerHTML;
                btnElement.innerHTML = '<i class="fa-solid fa-check text-emerald-400"></i> کپی شد!';
                setTimeout(() => {
                    btnElement.innerHTML = originalHtml;
                }, 2000);
            }).catch(function(err) {
                alert('خطا در کپی: ' + err);
            });
        }
    </script>
</body>
</html>
