<script src="{{ asset('frontend/assets/js/jquery.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/popper.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/bootstrap.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/ion.rangeSlider.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/slick.js') }}"></script>
<script src="{{ asset('frontend/assets/js/slider-bg.js') }}"></script>
<script src="{{ asset('frontend/assets/js/lightbox.js') }}"></script>
<script src="{{ asset('frontend/assets/js/smoothproducts.js') }}"></script>
<script src="{{ asset('frontend/assets/js/snackbar.min.js') }}"></script>
<script src="{{ asset('frontend/assets/js/jQuery.style.switcher.js') }}"></script>
<script src="{{ asset('frontend/assets/js/custom.js') }}"></script>

<script>
    (function () {
        var backgrounds = {
            success: '#2e7d32',
            error: '#c62828',
            warning: '#ef6c00',
            info: '#1565c0'
        };

        window.storefrontToast = function (message, type, duration) {
            if (!message) {
                return;
            }

            var toastType = (type || 'info').toLowerCase();
            var timeout = Number(duration || 4500);

            if (window.Snackbar && typeof window.Snackbar.show === 'function') {
                window.Snackbar.show({
                    text: String(message),
                    pos: 'top-right',
                    duration: timeout,
                    showAction: false,
                    backgroundColor: backgrounds[toastType] || backgrounds.info,
                });

                return;
            }

            if (window.console && typeof window.console.warn === 'function') {
                window.console.warn('[Toast]', message);
            }
        };

        var flushSeededToasts = function () {
            var seedNodes = document.querySelectorAll('[data-storefront-toast]');

            seedNodes.forEach(function (node) {
                var type = node.getAttribute('data-storefront-toast') || 'info';
                var message = (node.textContent || '').trim();

                if (message) {
                    window.storefrontToast(message, type);
                }

                node.remove();
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', flushSeededToasts);
        } else {
            flushSeededToasts();
        }
    })();
</script>

@stack('scripts')
