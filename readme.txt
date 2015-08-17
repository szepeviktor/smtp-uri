=== SMTP URI and logging ===
Contributors: szepe.viktor
Donate link: https://szepe.net/wp-donate/
Tags: email, mail, send, smtp, starttls, tls, gmail, mandrill, hotmail, outlook
Requires at least: 4.0
Tested up to: 4.3
Stable tag: 0.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

SMTP settings for WordPress and error logging.

== Description ==

Using SMTP protocol to transfer emails ensures solid operations.
It is very easy to set up SMTP. You can find the settings for this plugin at the bottom of WordPress admin menu Options / General.

You should get your SMTP settings from your ISP, hosting provider, webmaster, email provider etc.

Encryption types (protocols) are as follows:

* For encrypted connection (STARTTLS on submission port) start your SMTP URI with `smtpTLS://` - the default port is 587.
* For fully SSL encrypted connection (SMTPS) start it with `smtps://` - the default port is 465.
* For unencrypted connection (plain SMTP) start it with `smtp://` - the default port is 25. This is **not recommended** for non-local servers.

Using every option SMTP URI formally looks like:

`
smtpTLS://USERNAME:PASSWORD@HOST:PORT
`

Thus encryption type and `://` and user name and `:` and password and `@` and mail server name and `:` and port number.

**WARNING!** Use [URL-encoded](http://meyerweb.com/eric/tools/dencoder/) strings.

You can find the settings for this plugin at the bottom of WordPress admin Options / General.

You may define your SMTP URI in `wp-config.php`:

`
define( 'SMTP_URI', 'smtpTLS://USERNAME:PASSWORD@HOST:PORT' );
`

To set `From:` name and `From:` address use
[WP Mail From II plugin](https://wordpress.org/plugins/wp-mailfrom-ii/).

= SMTP error logging =

SMTP communication errors are logged in PHP error.log and - if
[Sucuri Scanner](https://wordpress.org/plugins/sucuri-scanner/)
plugin is available - are sent to Sucuri and can be viewed in its Alert Logs panel.

= TODO =

* Option to skip newsletters: ALO Newsletter, Newsletter, Mailpoet.
* Video on installing and setting up this plugin.
* Remove `smpt_uri` option on uninstallation (I hope you won't uninstall it)

= Usage examples =

Unauthenticated local SMTP server on port 25

`
smtp://localhost
`

Unauthenticated local SMTP server on submission port

`
smtpTLS://localhost
`

Authenticated connection to localhost on port 25

`
smtp://john.doe:Secretpwd1@localhost
`

"@" sign in the username

`
smtps://john.doe%40@gmail.com:Secretpwd1@smtp.gmail.com
`

Gmail example

`
smtps://your.address%40gmail.com:Gmail_password@smtp.gmail.com
`

Unauthenticated SMTP server on a custom port

`
smtpTLS://mail.server.net:2525
`

Mandrill example

`
smtpTLS://REGISTERED%40EMAIL:API-KEY@smtp.mandrillapp.com
`

Development goes on on [GitHub](https://github.com/szepeviktor/smtp-uri).

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload `smtp-uri.php` to the `/wp-content/plugins/svn-updater/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= Is it possible to hack in this plugin? =

You may uncomment debugging and automatic Bcc.

`
// Turn on SMTP debugging
$mail->SMTPDebug = 4;
$mail->Debugoutput = 'error_log';

// Bcc admin email
$mail->addBCC( get_bloginfo( 'admin_email' ) );
`

== Changelog ==

= 0.4.2 =
* Initial release on WordPress.org

= 0.4.1 =
* Releases up to 0.4.1 are available on [GitHub](https://github.com/szepeviktor/wordpress-plugin-construction)
