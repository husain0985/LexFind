<?php
/**
 * LexFind - Outgoing email settings.
 *
 * Emails are sent through the Brevo (formerly Sendinblue) transactional
 * email API over HTTPS, using PHP's built-in cURL extension — no Composer
 * package required, which matters on free shared hosts (e.g. InfinityFree)
 * that don't give you SSH/Composer access and often block outbound SMTP
 * ports (587/465) on their free tier.
 
 */
define('BREVO_API_KEY', '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');
define('BREVO_SENDER_EMAIL', 'husain77202@gmail.com'); // must be a verified sender in your Brevo account
define('BREVO_SENDER_NAME', 'LexFind.');