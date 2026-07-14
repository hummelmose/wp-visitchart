/**
 * Heartbeat-script - sendes hvert 12 sekund mens brugeren er aktiv på siden
 */
(function () {
    'use strict';

    // Stop al dataindsamling, hvis indstillingen "Udelad indloggede brugere"
    // er slået til og den aktuelle bruger er logget ind i WordPress.
    if ( lstatsData.excludeLoggedIn === '1' && lstatsData.isLoggedIn === '1' ) {
        return;
    }

    function getSessionId() {
        var key = 'lstats_session_id';
        var existing = sessionStorage.getItem(key);
        if (existing) {
            return existing;
        }
        var id = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        sessionStorage.setItem(key, id);
        return id;
    }

    function getReferrer() {
        // Gem referreren fra det første sidebesøg i sessionen, så intern navigation
        // på sitet ikke overskriver den oprindelige trafikkilde
        var key = 'lstats_referrer';
        var existing = sessionStorage.getItem(key);
        if (existing !== null) {
            return existing;
        }
        var ref = document.referrer || '';
        sessionStorage.setItem(key, ref);
        return ref;
    }

    function getDeviceType() {
        // Gem enhedstypen for sessionen, så den ikke skifter midt i et besøg
        // (fx hvis brugeren roterer skærmen eller ændrer vinduesstørrelse)
        var key = 'lstats_device';
        var existing = sessionStorage.getItem(key);
        if (existing) {
            return existing;
        }
        var width = window.innerWidth || document.documentElement.clientWidth;
        var type = 'desktop';
        if (width < 768) {
            type = 'mobile';
        } else if (width < 1024) {
            type = 'tablet';
        }
        sessionStorage.setItem(key, type);
        return type;
    }

    var sessionId = getSessionId();
    var referrer = getReferrer();
    var deviceType = getDeviceType();
    var isActive = true;
    var intervalId = null;

    // One-shot pageview ping – uafhængig af heartbeat-løkken.
    // Fyrer præcis én gang per session per side, uanset hvor kort besøget er.
    // sessionStorage sikrer at genindlæsning eller tab-skift ikke dobbelttæller.
    function sendPageviewPing() {
        if ( ! lstatsData.postId || parseInt( lstatsData.postId, 10 ) <= 0 ) {
            return;
        }
        var key = 'lstats_pv_' + lstatsData.postId;
        try {
            if ( sessionStorage.getItem( key ) ) {
                return;
            }
            sessionStorage.setItem( key, '1' );
        } catch ( e ) {}
        fetch( lstatsData.pageviewUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': lstatsData.nonce,
            },
            body: JSON.stringify( {
                postId: lstatsData.postId,
                sessionId: sessionId,
            } ),
        } ).catch( function () {} );
    }
    sendPageviewPing();

    function sendHeartbeat() {
        if (!isActive) {
            return;
        }

        fetch(lstatsData.restUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': lstatsData.nonce,
            },
            body: JSON.stringify({
                postId: lstatsData.postId,
                sessionId: sessionId,
                url: lstatsData.url,
                referrer: referrer,
                device: deviceType,
            }),
            keepalive: true,
        }).catch(function () {
            // Stille fejl - vi vil ikke spamme konsollen på frontend
        });
    }

    function startHeartbeat() {
        if (intervalId) {
            return;
        }
        sendHeartbeat();
        intervalId = setInterval(sendHeartbeat, 12000);
    }

    function stopHeartbeat() {
        if (intervalId) {
            clearInterval(intervalId);
            intervalId = null;
        }
    }

    // Pause heartbeat når tab ikke er synlig - sparer ressourcer og giver korrekt "live" tal
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            isActive = false;
            stopHeartbeat();
        } else {
            isActive = true;
            startHeartbeat();
        }
    });

    startHeartbeat();
})();
