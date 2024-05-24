<?php

if (!defined('GOOGLE_ANALYTICS_ID')) {
    define('GOOGLE_ANALYTICS_ID', 'G-ANALYTICS-1234');
}

if (!defined('GOOGLE_ADS_ID')) {
    define('GOOGLE_ADS_ID', 'G-ADS-1234');
}

function get_current_active_user() {
    $user = new \stdClass();
    $user->consents = new \stdClass();
    $user->consents->analytics = true;
    $user->consents->marketing = false;
    $user->consents->necessary = true;
    $user->consents->preferences = true;

    $user->id = hash('sha256', "COOL-USER-ID-1234");

    return $user;
}

?>

<script>
    class GoogleAnalytics {
        anonymize() {
            console.log('GoogleAnalytics: Anonymizing data');
        }
        set_source_id(id) {
            console.log('GoogleAnalytics: ' + id);
        }
        enable() {
            console.log('GoogleAnalytics: Enabled');
        }
    }

    class GoogleAds {
        anonymize() {
            console.log('GoogleAnalytics: Anonymizing data');
        }
        set_source_id(id) {
            console.log('GoogleAnalytics: ' + id);
        }
        enable() {
            console.log('GoogleAnalytics: Enabled');
        }
    }
</script>
