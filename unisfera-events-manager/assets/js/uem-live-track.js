setInterval(function() {
    fetch(uem_ajax_obj.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'uem_track_attendance',
            event_id: uem_ajax_obj.event_id,
            nonce: uem_ajax_obj.nonce
        })
    });
}, 60000); // 1 minut
