<?php
defined('ABSPATH') || exit;
$sec = get_site_option('znc_security_settings', []);
?>
<div class="wrap znc-admin-wrap">
<h1>Net Cart &mdash; Security</h1>

<h2>HMAC Authentication</h2>
<table class="form-table">
<tr><th>HMAC Secret</th><td>
<code id="znc-hmac-display"><?php echo esc_html(substr($sec['hmac_secret'] ?? 'Not generated', 0, 20) . '...'); ?></code>
<button type="button" id="znc-regenerate-secret" class="button">Regenerate Secret</button>
<p class="description">Generated: <?php echo esc_html($sec['hmac_generated_at'] ?? 'Never'); ?></p>
</td></tr>
</table>

<h2>Security Settings</h2>
<form id="znc-security-form">
<table class="form-table">
<tr><th>Clock Skew (seconds)</th><td><input type="number" name="clock_skew" value="<?php echo esc_attr($sec['clock_skew'] ?? 300); ?>"></td></tr>
<tr><th>Rate Limit (req/min)</th><td><input type="number" name="rate_limit" value="<?php echo esc_attr($sec['rate_limit'] ?? 60); ?>"></td></tr>
<tr><th>IP Whitelist</th><td><textarea name="ip_whitelist" rows="4" cols="40"><?php echo esc_textarea($sec['ip_whitelist'] ?? ''); ?></textarea><p class="description">One IP per line</p></td></tr>
</table>
<p><button type="submit" class="button button-primary">Save Security Settings</button></p>
</form>
</div>
