(function () {
    var notice = document.getElementById('bnomics-review-notice');
    if (!notice) {
        return;
    }

    notice.addEventListener('click', function (event) {
        // The links carry data-action. The WordPress close (x) button has none, so it counts as "later".
        var target = event.target.closest('[data-action], .notice-dismiss');
        if (!target) {
            return;
        }
        if (target.getAttribute('href') === '#') {
            event.preventDefault();
        }

        var data = new URLSearchParams({
            action: 'blockonomics_review',
            review_action: target.dataset.action || 'later',
            nonce: bnomicsReview.nonce
        });
        fetch(bnomicsReview.ajaxurl, { method: 'POST', body: data });
        notice.style.display = 'none';
    });
})();