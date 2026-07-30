# Pecodex Security API Architecture Analysis

Based on the graphify BFS traversal, the `Pecodex_Security_API` (Community 1) acts as a central hub bridging multiple other functional communities within the plugin. Specifically, it connects to **Community 25** (Hardening) and **Community 4** (Notifications and Vulnerabilities) through various REST API / AJAX handler methods.

## Connection to Community 25 (Hardening)
Community 25 handles security hardening features like `.htaccess` rules. The `Pecodex_Security_API` connects to it via the `ajax_toggle_security_tweak` method.
*   **Bridge Method:** `ajax_toggle_security_tweak()`
*   **Target Class/Method:** `Pecodex_Hardening::update_htaccess_rules()`
*   **Mechanism:** When an administrator toggles a security tweak in the dashboard, the API handles the request, saves the updated settings to the `pmc_security_tweaks` option, and then directly invokes `Pecodex_Hardening::update_htaccess_rules()` to apply the new `.htaccess` hardening rules based on the user's input.

## Connection to Community 4 (Notifications & Vulnerabilities)
Community 4 encapsulates monitoring, alerts, and vulnerability data, mainly through the `Pecodex_Notifications` and `Pecodex_Vulnerabilities` classes. `Pecodex_Security_API` acts as a bridge in two main ways:
*   **Bridge Method 1:** `ajax_send_test_notifications()`
    *   **Target Class/Method:** `Pecodex_Notifications::send_notification()`
    *   **Mechanism:** When the user requests to send a test notification from the settings panel, the API constructs a series of mock events (like firewall lockouts or malware detection) and loops through them, calling `Pecodex_Notifications::send_notification()` for each to dispatch test emails.
*   **Bridge Method 2:** `ajax_security_dashboard_data()` (or similar main data provider)
    *   **Target Class/Method:** `Pecodex_Vulnerabilities::get_cached_vulnerabilities()`
    *   **Mechanism:** When aggregating data for the security dashboard frontend, the API fetches the latest cached vulnerability data by calling `Pecodex_Vulnerabilities::get_cached_vulnerabilities()` and includes it in the JSON payload sent to the client.

## Summary
The `Pecodex_Security_API` serves as the primary controller translating HTTP/AJAX requests from the user interface into backend actions (like hardening rules in Community 25 or triggering notifications in Community 4) and aggregating data from backend services (like vulnerabilities in Community 4) to be consumed by the frontend.
