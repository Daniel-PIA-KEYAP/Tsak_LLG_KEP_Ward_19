/**
 * QR Code Generator and Management
 * KEP Ward 19 Registration System
 *
 * Requires: qrcode.js (https://github.com/soldair/node-qrcode)
 * CDN: https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js
 */

(function (global) {
    'use strict';

    // -------------------------------------------------------------------------
    // QRCodeManager: public API
    // -------------------------------------------------------------------------
    var QRCodeManager = {

        /**
         * Generate a QR code and render it into a container element.
         *
         * @param {string|HTMLElement} container  - CSS selector or DOM element
         * @param {Object}             userData   - { email, reg_id, token, timestamp, verified }
         * @param {Object}             [options]  - Optional QRCode render options
         * @returns {Promise<void>}
         */
        generate: function (container, userData, options) {
            var el = _resolveElement(container);
            if (!el) {
                return Promise.reject(new Error('QRCodeManager.generate: container not found'));
            }

            var payload = _buildPayload(userData);
            var encodedData = JSON.stringify(payload);

            var defaultOptions = {
                width: 256,
                margin: 2,
                color: { dark: '#000000', light: '#ffffff' },
                errorCorrectionLevel: 'M'
            };
            var renderOptions = _mergeOptions(defaultOptions, options || {});

            // Clear previous content
            el.innerHTML = '';

            return global.QRCode.toCanvas(encodedData, renderOptions)
                .then(function (canvas) {
                    el.appendChild(canvas);
                    return canvas;
                });
        },

        /**
         * Render a QR code as a data-URL PNG and set it as the src of an <img>.
         *
         * @param {string|HTMLElement} imgElement - CSS selector or <img> element
         * @param {Object}             userData
         * @param {Object}             [options]
         * @returns {Promise<string>}  Resolves with the data URL
         */
        generateAsImage: function (imgElement, userData, options) {
            var el = _resolveElement(imgElement);
            if (!el) {
                return Promise.reject(new Error('QRCodeManager.generateAsImage: element not found'));
            }

            var payload = _buildPayload(userData);
            var encodedData = JSON.stringify(payload);

            var defaultOptions = {
                width: 256,
                margin: 2,
                color: { dark: '#000000', light: '#ffffff' },
                errorCorrectionLevel: 'M'
            };
            var renderOptions = _mergeOptions(defaultOptions, options || {});

            return global.QRCode.toDataURL(encodedData, renderOptions)
                .then(function (dataUrl) {
                    el.src = dataUrl;
                    return dataUrl;
                });
        },

        /**
         * Download the QR code canvas as a PNG file.
         *
         * @param {string|HTMLElement} container  - Container holding the <canvas>
         * @param {string}             [filename] - Download filename (default: kep-qr-code.png)
         */
        download: function (container, filename) {
            var el = _resolveElement(container);
            if (!el) { return; }

            var canvas = el.querySelector('canvas');
            if (!canvas) {
                // Try container itself if it is a canvas
                if (el.tagName && el.tagName.toLowerCase() === 'canvas') {
                    canvas = el;
                } else {
                    console.warn('QRCodeManager.download: no canvas found in container');
                    return;
                }
            }

            var link = document.createElement('a');
            link.download = filename || 'kep-qr-code.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        },

        /**
         * Trigger the browser print dialog scoped to the QR code element.
         * A temporary printable frame is created and removed after printing.
         *
         * @param {string|HTMLElement} container - Container holding the QR canvas
         * @param {string}             [title]   - Optional title shown above the QR code
         */
        print: function (container, title) {
            var el = _resolveElement(container);
            if (!el) { return; }

            var canvas = el.querySelector('canvas');
            if (!canvas) { return; }

            var dataUrl = canvas.toDataURL('image/png');
            var heading = title ? '<h2 style="font-family:sans-serif;text-align:center;">' + _escapeHtml(title) + '</h2>' : '';

            var iframe = document.createElement('iframe');
            iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:0;height:0;border:none;';
            document.body.appendChild(iframe);

            var doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write(
                '<!DOCTYPE html><html><head><title>QR Code</title>' +
                '<style>body{margin:20px;text-align:center;}img{max-width:280px;}</style>' +
                '</head><body>' + heading +
                '<img src="' + dataUrl + '" alt="QR Code"/>' +
                '</body></html>'
            );
            doc.close();

            iframe.contentWindow.focus();
            iframe.contentWindow.print();

            // Remove the iframe shortly after
            setTimeout(function () {
                document.body.removeChild(iframe);
            }, 1000);
        },

        /**
         * Generate a QR code from server-side data via the generate-qr-code API.
         * Renders the result into the given container.
         *
         * @param {string|HTMLElement} container
         * @param {Object}             requestData  - Payload sent to the API
         * @param {Object}             [options]    - QRCode render options
         * @returns {Promise<Object>}  Resolves with the API response JSON
         */
        generateFromServer: function (container, requestData, options) {
            return _apiFetch('api/generate-qr-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(requestData)
            })
                .then(function (data) {
                    if (!data.success) {
                        return Promise.reject(new Error(data.message || 'QR generation failed'));
                    }
                    return QRCodeManager.generate(container, data.qr_data, options)
                        .then(function () { return data; });
                });
        },

        /**
         * Validate a QR code token with the server.
         *
         * @param {string} token
         * @returns {Promise<Object>}
         */
        validate: function (token) {
            return _apiFetch('api/validate-qr-code.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: token })
            });
        },

        /**
         * Perform a QR-code-based login.
         *
         * @param {string} token
         * @returns {Promise<Object>}
         */
        login: function (token) {
            return _apiFetch('api/qr-login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: token })
            });
        },

        /**
         * Retrieve user profile data by QR token.
         *
         * @param {string} token
         * @returns {Promise<Object>}
         */
        getUserByToken: function (token) {
            return _apiFetch('api/get-user-by-qr.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: token })
            });
        },

        /**
         * Parse and decode a QR code JSON payload string.
         *
         * @param {string} raw - Raw string scanned from a QR code
         * @returns {Object|null}
         */
        parsePayload: function (raw) {
            try {
                var obj = JSON.parse(raw);
                if (!obj || !obj.token) { return null; }
                return obj;
            } catch (e) {
                return null;
            }
        },

        /**
         * Pre-fill an email field on the page using data from a URL query parameter.
         * Expected URL format: login.php?qr=<base64-encoded-JSON>
         *
         * @param {string|HTMLElement} emailField - Target email <input> element
         */
        prefillEmailFromUrl: function (emailField) {
            var el = _resolveElement(emailField);
            if (!el) { return; }

            var params = new URLSearchParams(global.location.search);
            var raw = params.get('qr');
            if (!raw) { return; }

            try {
                var decoded = atob(raw);
                var data = JSON.parse(decoded);
                if (data && data.email) {
                    el.value = data.email;
                }
            } catch (e) {
                console.warn('QRCodeManager.prefillEmailFromUrl: could not decode QR param', e);
            }
        }
    };

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Build a standardised QR payload from user data.
     * Adds a timestamp if not already present.
     */
    function _buildPayload(userData) {
        return {
            email:     userData.email     || '',
            reg_id:    userData.reg_id    || '',
            token:     userData.token     || '',
            timestamp: userData.timestamp || Math.floor(Date.now() / 1000),
            verified:  userData.verified  !== undefined ? !!userData.verified : false
        };
    }

    /** Resolve a CSS selector or DOM element reference */
    function _resolveElement(ref) {
        if (!ref) { return null; }
        if (typeof ref === 'string') {
            return document.querySelector(ref);
        }
        return ref;
    }

    /** Shallow merge of two plain objects */
    function _mergeOptions(defaults, overrides) {
        var result = {};
        Object.keys(defaults).forEach(function (k) { result[k] = defaults[k]; });
        Object.keys(overrides).forEach(function (k) { result[k] = overrides[k]; });
        return result;
    }

    /** Minimal HTML entity escaper */
    function _escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /** Fetch wrapper that always parses the JSON response body */
    function _apiFetch(url, init) {
        return fetch(url, init)
            .then(function (res) { return res.json(); });
    }

    // -------------------------------------------------------------------------
    // Expose to global scope
    // -------------------------------------------------------------------------
    global.QRCodeManager = QRCodeManager;

}(typeof window !== 'undefined' ? window : this));
