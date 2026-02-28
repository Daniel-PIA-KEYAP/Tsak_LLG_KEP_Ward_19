/**
 * QR Code Generator - Client-side QR code generation and management
 * Uses qrcode.js library (loaded via CDN)
 */
const QRCodeGenerator = (() => {
    /**
     * Generate a QR code and render it in a container element.
     * @param {string} containerId - ID of the element to render into
     * @param {object} data - Data to encode
     * @param {object} options - QR options (size, etc.)
     */
    function generate(containerId, data, options = {}) {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = '';

        const text = typeof data === 'string' ? data : JSON.stringify(data);
        const size = options.size || 200;

        if (typeof QRCode !== 'undefined') {
            new QRCode(container, {
                text,
                width:  size,
                height: size,
                colorDark:  '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        } else {
            // Fallback: show data as text
            container.textContent = text;
        }
    }

    /**
     * Generate a registration QR payload.
     */
    function buildRegistrationPayload(email, registrationId, token) {
        return {
            type:  'kep_registration',
            email,
            id:    registrationId,
            token,
            ts:    Date.now(),
            ver:   1
        };
    }

    /**
     * Download the rendered QR code canvas as a PNG.
     */
    function download(containerId, filename = 'kep-registration-qr.png') {
        const container = document.getElementById(containerId);
        if (!container) return;
        const canvas = container.querySelector('canvas');
        if (canvas) {
            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/png');
            link.click();
            return;
        }
        const img = container.querySelector('img');
        if (img) {
            const link = document.createElement('a');
            link.download = filename;
            link.href = img.src;
            link.click();
        }
    }

    /**
     * Print the QR code.
     */
    function print(containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const canvas = container.querySelector('canvas');
        const img    = container.querySelector('img');
        const src = canvas ? canvas.toDataURL('image/png') : (img ? img.src : null);
        if (!src) return;

        const win = window.open('', '_blank');
        win.document.write(`
            <!DOCTYPE html><html><head><title>Print QR Code</title>
            <style>body{text-align:center;padding:40px;}img{max-width:300px;}</style>
            </head><body>
            <h2>KEP Registration QR Code</h2>
            <img src="${src}" alt="QR Code">
            <p>Scan this QR code to access your registration data.</p>
            <script>window.onload=function(){window.print();}<\/script>
            </body></html>
        `);
        win.document.close();
    }

    /**
     * Initialise QR display on the confirmation page.
     */
    function initConfirmationPage() {
        const dataEl = document.getElementById('qr-payload');
        if (!dataEl) return;
        try {
            const payload = JSON.parse(dataEl.dataset.payload || '{}');
            generate('qr-code-display', payload);
        } catch { /* ignore */ }
    }

    /**
     * Start camera-based QR scanner (uses jsQR if available).
     */
    function startScanner(videoId, canvasId, onResult) {
        const video  = document.getElementById(videoId);
        const canvas = document.getElementById(canvasId);
        if (!video || !canvas || typeof jsQR === 'undefined') return;

        const ctx = canvas.getContext('2d');
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(stream => {
                video.srcObject = stream;
                video.setAttribute('playsinline', true);
                video.play();
                requestAnimationFrame(tick);
            })
            .catch(() => {
                if (typeof NotificationSystem !== 'undefined')
                    NotificationSystem.error('Unable to access camera. Please use file upload instead.');
            });

        function tick() {
            if (video.readyState !== video.HAVE_ENOUGH_DATA) {
                requestAnimationFrame(tick);
                return;
            }
            canvas.height = video.videoHeight;
            canvas.width  = video.videoWidth;
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            const code = jsQR(imageData.data, imageData.width, imageData.height);
            if (code) {
                onResult(code.data);
                return; // stop scanning after first result
            }
            requestAnimationFrame(tick);
        }
    }

    /**
     * Scan QR code from an uploaded image file.
     */
    function scanFromFile(file, onResult) {
        if (typeof jsQR === 'undefined') return;
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width  = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                const imageData = ctx.getImageData(0, 0, img.width, img.height);
                const code = jsQR(imageData.data, imageData.width, imageData.height);
                onResult(code ? code.data : null);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }

    return { generate, buildRegistrationPayload, download, print, initConfirmationPage, startScanner, scanFromFile };
})();

if (typeof module !== 'undefined') module.exports = QRCodeGenerator;
