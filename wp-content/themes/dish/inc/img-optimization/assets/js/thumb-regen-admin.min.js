/**
 * Thumbnail Regeneration Admin — JavaScript
 *
 * Drives the one-at-a-time AJAX regeneration loop on the
 * Tools → Regenerate Thumbnails admin page.
 *
 * Mirrors the pattern of webp-admin.js.
 *
 * @package basecamp
 */

(function ($) {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────
    let isProcessing   = false;
    let isPaused       = false;
    let retryCount     = 0;
    const maxRetries   = 3;
    const processDelay = 300; // ms between requests
    const originalTitle = document.title;

    // ── DOM refs ─────────────────────────────────────────────────────────────
    const $startBtn      = $('#regen-start');
    const $pauseBtn      = $('#regen-pause');
    const $resumeBtn     = $('#regen-resume');
    const $progressBar   = $('#regen-progress-bar');
    const $progressText  = $('#regen-progress-text');
    const $currentWrap   = $('#regen-current-processing');
    const $currentName   = $('#regen-current-image-name');
    const $logEntries    = $('#regen-log-entries');

    // ── Init ─────────────────────────────────────────────────────────────────

    /**
     * Initialise event handlers and check for an in-progress session.
     */
    function init() {
        $startBtn.on('click', startProcessing);
        $pauseBtn.on('click', pauseProcessing);
        $resumeBtn.on('click', resumeProcessing);

        checkAutoResume();
    }

    /**
     * Offer to resume if localStorage indicates a session was running.
     */
    function checkAutoResume() {
        const saved = loadState();
        if (saved && saved.isProcessing && !saved.isPaused) {
            setTimeout(function () {
                if (confirm(basecampRegen.strings.resume || 'Resume the regeneration that was in progress?')) {
                    resumeProcessing();
                }
            }, 800);
        }
    }

    // ── Controls ─────────────────────────────────────────────────────────────

    /**
     * Start a new regeneration run.
     */
    function startProcessing() {
        isProcessing = true;
        isPaused     = false;
        retryCount   = 0;

        $startBtn.hide();
        $pauseBtn.show();
        $resumeBtn.hide();
        $currentWrap.show();
        $logEntries.empty();

        saveState();
        processNext();
    }

    /**
     * Pause the current run.
     */
    function pauseProcessing() {
        isPaused = true;

        $pauseBtn.hide();
        $resumeBtn.show();

        addLog('Regeneration paused.', 'paused');
        document.title = '⏸ ' + originalTitle;
        saveState();
    }

    /**
     * Resume a paused run.
     */
    function resumeProcessing() {
        isPaused     = false;
        isProcessing = true;
        retryCount   = 0;

        $startBtn.hide();
        $resumeBtn.hide();
        $pauseBtn.show();
        $currentWrap.show();

        addLog('Regeneration resumed.', 'info');
        saveState();
        processNext();
    }

    // ── AJAX loop ────────────────────────────────────────────────────────────

    /**
     * Request the server to process the next image in the queue.
     */
    function processNext() {
        if (!isProcessing || isPaused) {
            return;
        }

        $currentName.text('Loading…');

        $.ajax({
            url:      basecampRegen.ajaxUrl,
            type:     'POST',
            dataType: 'json',
            timeout:  90000,
            data: {
                action: 'basecamp_regen_process_single',
                nonce:  basecampRegen.nonce
            },
            success: function (response) {
                retryCount = 0;
                if (response.success) {
                    handleSuccess(response.data);
                } else {
                    handleError(response.data);
                }
            },
            error: function (xhr, status, error) {
                if (retryCount < maxRetries) {
                    retryCount++;
                    const delay = 2000 * retryCount;
                    addLog('Server error. Retrying in ' + (delay / 1000) + 's… (attempt ' + retryCount + '/' + maxRetries + ')', 'warning');
                    $currentName.text('Retrying…');
                    setTimeout(processNext, delay);
                } else {
                    const msg = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : status + (error ? ' – ' + error : '');
                    addLog('Server error after ' + maxRetries + ' attempts: ' + msg, 'error');
                    pauseProcessing();
                }
            }
        });
    }

    // ── Response handlers ────────────────────────────────────────────────────

    /**
     * Handle a successful AJAX response.
     *
     * @param {Object} data
     */
    function handleSuccess(data) {
        if (data.status === 'complete') {
            isProcessing = false;

            $currentWrap.hide();
            $pauseBtn.hide();
            $resumeBtn.hide();

            updateProgress(data.progress);
            addLog('Regeneration complete! All images processed.', 'success');

            document.title = '✓ ' + originalTitle;
            clearState();
            return;
        }

        // Per-image result.
        $currentName.text(data.filename || '…');

        if (data.success) {
            addLog('✓ ' + data.message, 'success');
        } else {
            addLog('⚠ ' + data.message, 'error');
        }

        updateProgress(data.progress);
        saveState();

        if (isProcessing && !isPaused) {
            setTimeout(processNext, processDelay);
        }
    }

    /**
     * Handle a JSON error response.
     *
     * @param {Object} data
     */
    function handleError(data) {
        addLog('Error: ' + (data.message || 'Unknown error'), 'error');
        pauseProcessing();
    }

    // ── UI helpers ───────────────────────────────────────────────────────────

    /**
     * Update the progress bar and status text.
     *
     * @param {Object} progress
     */
    function updateProgress(progress) {
        const total     = progress.total     || 0;
        const processed = progress.processed || 0;
        const failed    = progress.failed    || 0;
        const done      = processed + failed;

        if (total > 0) {
            const pct = (done / total) * 100;
            $progressBar.css('width', pct + '%');
            $progressText.html(
                '<strong>Progress:</strong> ' + done + ' of ' + total +
                ' images processed (' + Math.round(pct * 10) / 10 + '%)'
            );

            if (isProcessing && !isPaused) {
                document.title = '(' + Math.round(pct) + '%) ' + originalTitle;
            }
        }
    }

    /**
     * Append a timestamped entry to the processing log.
     *
     * @param {string} message
     * @param {string} type  success | error | info | warning | paused
     */
    function addLog(message, type) {
        const icons = { success: '✓', error: '⚠', info: 'ℹ', warning: '⚠', paused: '⏸' };
        const time  = new Date().toLocaleTimeString();
        const icon  = icons[type] || '•';

        const $entry = $('<li>')
            .addClass('log-entry log-' + type)
            .html('<span class="log-time">[' + time + ']</span> <span class="log-icon">' + icon + '</span> ' + message);

        $logEntries.prepend($entry);
        $('#regen-processing-log').scrollTop(0);
    }

    // ── localStorage state ───────────────────────────────────────────────────

    const STATE_KEY = 'basecampRegenState';

    function saveState() {
        localStorage.setItem(STATE_KEY, JSON.stringify({
            isProcessing: isProcessing,
            isPaused:     isPaused,
            timestamp:    Date.now()
        }));
    }

    function loadState() {
        try {
            const raw = localStorage.getItem(STATE_KEY);
            if (!raw) { return null; }
            const state = JSON.parse(raw);
            // Discard stale state (> 1 hour old).
            if (Date.now() - (state.timestamp || 0) > 3600000) { return null; }
            return state;
        } catch (e) {
            return null;
        }
    }

    function clearState() {
        localStorage.removeItem(STATE_KEY);
    }

    // ── Boot ─────────────────────────────────────────────────────────────────

    $(document).ready(init);

}(jQuery));
